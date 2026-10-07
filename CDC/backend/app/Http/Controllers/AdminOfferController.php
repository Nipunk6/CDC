<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PlacementBlock;
use App\Services\AuditService;
use App\Services\OfferEditService;
use App\Services\SpreadsheetImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Shortlist for Offer, after publishing (Superset parity S2, B2-8): edit an announced offer (CTC Offered, CTC Interval,
 * currency, offer type), revoke it, or upload CTCs for a whole job profile. Admin only; every write is audited.
 */
class AdminOfferController extends Controller
{
    private const CURRENCIES = ['INR', 'USD', 'EUR', 'GBP', 'SGD', 'AED', 'JPY'];

    public function __construct(
        private readonly OfferEditService $offers,
        private readonly AuditService $audit,
        private readonly SpreadsheetImportService $spreadsheets
    ) {
    }

    public function preview(Request $request, Offer $offer): JsonResponse
    {
        $validated = $request->validate([
            'offer_type' => ['required', Rule::in(Offer::TYPES)],
            'reapply_blocking' => ['nullable', 'boolean'],
            'apply_blocking' => ['nullable', 'boolean'],
        ]);

        return response()->json($this->offers->preview($offer->load(['studentProfile', 'placementCycle']), $validated['offer_type'], $this->blockingMode($request)));
    }

    public function update(Request $request, Offer $offer): JsonResponse
    {
        $validated = $request->validate([
            'offer_type' => ['sometimes', Rule::in(Offer::TYPES)],
            'ctc_annual' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000000'],
            'stipend_monthly' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000'],
            'currency' => ['sometimes', 'string', Rule::in(self::CURRENCIES)],
            'reapply_blocking' => ['nullable', 'boolean'],
            'apply_blocking' => ['nullable', 'boolean'],
        ]);

        $changes = array_intersect_key($validated, array_flip(['offer_type', 'ctc_annual', 'stipend_monthly', 'currency']));
        foreach (['ctc_annual', 'stipend_monthly'] as $key) {
            if (isset($changes[$key])) {
                $changes[$key] = (int) $changes[$key];
            }
        }
        if ($changes === [] || $this->unchanged($offer, $changes)) {
            return response()->json(['message' => 'Nothing changed.'], 422);
        }

        $mode = $this->blockingMode($request);
        $result = $this->offers->update($offer->load(['studentProfile', 'placementCycle']), $changes, $mode, $request->user());

        $this->auditResult($request, $result['offer'], $result['before'], $result['after'] + ['blocking' => $mode], $result, 'edit');
        $this->notifyChange($result['offer'], $result['before'], $result['after']);

        return response()->json([
            'message' => 'Offer updated.'.($result['created']->isNotEmpty() || $result['lifted']->isNotEmpty()
                ? sprintf(' %d block(s) lifted, %d created.', $result['lifted']->count(), $result['created']->count())
                : ''),
            'offer' => $result['offer']->fresh()->toArray() + ['label' => Offer::LABELS[$result['offer']->offer_type] ?? $result['offer']->offer_type],
        ]);
    }

    public function revoke(Request $request, Offer $offer): JsonResponse
    {
        $validated = $request->validate([
            'confirm' => ['required', 'accepted'],
            'remark' => ['required', 'string', 'max:500'],
        ], [
            'confirm.accepted' => 'Confirm that you want to revoke this offer.',
            'remark.required' => 'Give a reason for revoking this offer.',
        ]);

        $offer->load(['studentProfile', 'company:id,name', 'jobPosting.postable']);
        $company = $offer->company?->name ?? 'the company';
        $title = $offer->jobPosting?->title() ?? 'the job profile';

        $result = $this->offers->revoke($offer, $request->user());

        $this->audit->log($request, 'offer.revoke', $offer, $result['before'], [
            'remark' => $validated['remark'],
            'blocks_lifted' => $result['lifted']->pluck('id')->all(),
            'placed_elsewhere_flags_cleared' => $result['flags_cleared'],
        ]);
        foreach ($result['lifted'] as $block) {
            $this->audit->log($request, 'block.remove', $block, ['active' => true], ['active' => false, 'via' => 'offer.revoke']);
        }

        $this->offers->notifyStudent($result['before'], "Offer from {$company} revoked", array_values(array_filter([
            "The CDC has revoked your offer for {$title} at {$company}.",
            'Reason: '.$validated['remark'],
            $result['lifted']->isNotEmpty() ? 'The placement block that came with this offer has been lifted.' : null,
        ])), 'warning');

        return response()->json([
            'message' => sprintf('Offer revoked. %d block(s) lifted, %d placed-elsewhere flag(s) cleared. The student has been notified.', $result['lifted']->count(), $result['flags_cleared']),
        ]);
    }

    /**
     * Upload CTCs (roll number, CTC, interval YEAR|MONTH, currency) for this job profile's offers, with a dry run.
     */
    public function uploadCtcs(Request $request, JobPosting $jobPosting): JsonResponse
    {
        $request->validate([
            'file' => array_merge(['required'], SpreadsheetImportService::UPLOAD_RULES),
            'dry_run' => ['nullable', 'boolean'],
        ]);
        $dryRun = $request->boolean('dry_run');

        try {
            $rows = $this->spreadsheets->rows($request->file('file'));
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $offers = Offer::query()->where('job_posting_id', $jobPosting->id)->with('studentProfile:id,roll_no,full_name')->get()->keyBy(fn (Offer $o) => $o->studentProfile->roll_no);
        $errors = [];
        $planned = [];
        $seen = [];

        foreach ($rows as $line => $cells) {
            $roll = strtoupper(trim((string) ($cells[0] ?? '')));
            if ($roll === '' || in_array(preg_replace('/[^A-Z]/', '', $roll), ['ROLLNO', 'ROLLNUMBER'], true)) {
                continue;
            }
            $amountRaw = str_replace([',', ' '], '', trim((string) ($cells[1] ?? '')));
            $interval = strtoupper(trim((string) ($cells[2] ?? 'YEAR'))) ?: 'YEAR';
            $currency = strtoupper(trim((string) ($cells[3] ?? ''))) ?: null;

            $offer = $offers->get($roll);
            $problem = match (true) {
                isset($seen[$roll]) => 'Listed twice in the file.',
                ! $offer => 'No offer for this roll number on this job profile.',
                ! preg_match('/^\d+(\.\d+)?$/', $amountRaw) => 'CTC must be a number.',
                ! in_array($interval, ['YEAR', 'MONTH'], true) => 'CTC Interval must be YEAR or MONTH.',
                $currency !== null && ! in_array($currency, self::CURRENCIES, true) => 'Unknown currency.',
                default => null,
            };
            if ($problem) {
                $errors[] = ['row' => $line, 'roll_no' => $roll, 'reason' => $problem];

                continue;
            }
            $seen[$roll] = true;

            $amount = (int) round((float) $amountRaw);
            $changes = $interval === 'YEAR' ? ['ctc_annual' => $amount] : ['stipend_monthly' => $amount];
            if ($currency) {
                $changes['currency'] = $currency;
            }
            if ($this->unchanged($offer, $changes)) {
                continue;
            }
            $planned[] = ['offer' => $offer, 'changes' => $changes, 'roll_no' => $roll, 'name' => $offer->studentProfile->full_name];
        }

        $preview = array_map(fn (array $p) => [
            'roll_no' => $p['roll_no'],
            'name' => $p['name'],
            'before' => $p['offer']->only(array_keys($p['changes'])),
            'after' => $p['changes'],
        ], $planned);

        if ($dryRun) {
            return response()->json([
                'message' => sprintf('%d offer(s) would change. %d row(s) have problems.', count($planned), count($errors)),
                'dry_run' => true,
                'changes' => $preview,
                'errors' => $errors,
            ]);
        }

        foreach ($planned as $p) {
            $result = $this->offers->update($p['offer']->load(['studentProfile', 'placementCycle']), $p['changes'], OfferEditService::BLOCKS_KEEP, $request->user());
            $this->auditResult($request, $result['offer'], $result['before'], $result['after'], $result, 'upload');
            $this->notifyChange($result['offer'], $result['before'], $result['after']);
        }

        return response()->json([
            'message' => sprintf('%d offer(s) updated. %d row(s) skipped with problems.', count($planned), count($errors)),
            'dry_run' => false,
            'changes' => $preview,
            'errors' => $errors,
        ]);
    }

    /**
     * What a change of offer type does to blocks (L7, D91). By default the new type's blocking rules apply, but a block
     * the admin lifted by hand never comes back; `reapply_blocking: true` brings it back too and `reapply_blocking: false`
     * leaves every block as it is. The older `apply_blocking` key is still read: false leaves blocks, true is the default.
     */
    private function blockingMode(Request $request): string
    {
        if ($request->input('reapply_blocking') !== null) {
            return $request->boolean('reapply_blocking') ? OfferEditService::BLOCKS_REAPPLY : OfferEditService::BLOCKS_KEEP;
        }
        if ($request->input('apply_blocking') !== null && ! $request->boolean('apply_blocking')) {
            return OfferEditService::BLOCKS_KEEP;
        }

        return OfferEditService::BLOCKS_DEFAULT;
    }

    /**
     * Strict per-field comparison, so an empty CTC changed to 0 is a change (L7).
     *
     * @param  array<string, mixed>  $changes
     */
    private function unchanged(Offer $offer, array $changes): bool
    {
        foreach ($changes as $key => $value) {
            if ($offer->getAttribute($key) !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $result
     */
    private function auditResult(Request $request, Offer $offer, array $before, array $after, array $result, string $source): void
    {
        $this->audit->log($request, 'offer.update', $offer, $before, $after + [
            'source' => $source,
            'blocks_lifted' => $result['lifted']->pluck('id')->all(),
            'blocks_created' => $result['created']->pluck('id')->all(),
            'placed_elsewhere_flags_cleared' => $result['flags_cleared'],
            'placed_elsewhere_flags_set' => $result['flags_set'],
        ]);
        foreach ($result['lifted'] as $block) {
            $this->audit->log($request, 'block.remove', $block, ['active' => true], ['active' => false, 'via' => 'offer.update']);
        }
        foreach ($result['created'] as $block) {
            /** @var PlacementBlock $block */
            $this->audit->log($request, 'block.create', $block, null, $block->only(['student_profile_id', 'placement_cycle_id', 'scope', 'reason', 'offer_id']) + ['via' => 'offer.update']);
        }
    }

    /**
     * The student hears about a change of offer type or CTC (in-app + personal mail).
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function notifyChange(Offer $offer, array $before, array $after): void
    {
        $typeChanged = $before['offer_type'] !== $after['offer_type'];
        $moneyChanged = $before['ctc_annual'] !== $after['ctc_annual'] || $before['stipend_monthly'] !== $after['stipend_monthly'] || $before['currency'] !== $after['currency'];
        if (! $typeChanged && ! $moneyChanged) {
            return;
        }

        $offer->loadMissing(['company:id,name', 'jobPosting.postable']);
        $company = $offer->company?->name ?? 'the company';
        $lines = ["The CDC has updated your offer for {$offer->jobPosting?->title()} at {$company}."];
        if ($typeChanged) {
            $lines[] = 'Offer type: '.(Offer::LABELS[$after['offer_type']] ?? $after['offer_type']).' (was '.(Offer::LABELS[$before['offer_type']] ?? $before['offer_type']).').';
        }
        if ($moneyChanged) {
            $lines[] = 'CTC Offered: '.OfferEditService::compensationText($after['ctc_annual'], $after['stipend_monthly'], $after['currency'])
                .' (was '.OfferEditService::compensationText($before['ctc_annual'], $before['stipend_monthly'], $before['currency']).').';
        }

        $this->offers->notifyStudent($offer, "Your offer from {$company} was updated", $lines);
    }
}
