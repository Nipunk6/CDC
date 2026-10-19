<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyContactChange;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PlacementCycle;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Company History (owner decisions 2026-10-20, P3-D55):
 * - a company "came" in an academic year when one of its job profiles was opened for students in a placement of that
 *   year (cancelled job profiles are listed but do not count); accepted forms never opened are shown separately;
 * - the academic year is July–June, from the placement's start date ("2026-27");
 * - contacts: the company's current contacts, the HR contact saved on each job profile when it was opened, and a log of
 *   every contact change from 20-10-2026 on (earlier contacts were overwritten and are not recoverable).
 */
class CompanyHistoryService
{
    /** Company columns that make up "contacts" (HR + Head of Talent + points of contact). */
    public const CONTACT_FIELDS = ['hr_name', 'hr_designation', 'hr_email', 'hr_phone', 'hr_alt_phone', 'head_talent_contact', 'primary_contact', 'secondary_contact'];

    /** @return array{name: ?string, designation: ?string, email: ?string, phone: ?string, alt_phone: ?string} */
    public static function contactSnapshot(?Company $company): array
    {
        return [
            'name' => $company?->hr_name,
            'designation' => $company?->hr_designation,
            'email' => $company?->hr_email,
            'phone' => $company?->hr_phone,
            'alt_phone' => $company?->hr_alt_phone,
        ];
    }

    /** Every current contact of the company, plus its recruiter login accounts. */
    public static function contacts(Company $company): array
    {
        $person = fn ($value) => is_array($value) && array_filter($value) ? [
            'name' => $value['name'] ?? null, 'designation' => $value['designation'] ?? null, 'email' => $value['email'] ?? null,
            'phone' => $value['mobile'] ?? $value['phone'] ?? null, 'alt_phone' => $value['landline'] ?? null,
        ] : null;

        return [
            'hr' => self::contactSnapshot($company),
            'head_talent' => $person($company->head_talent_contact),
            'primary' => $person($company->primary_contact),
            'secondary' => $person($company->secondary_contact),
            'accounts' => $company->users()->where('role', 'company')->orderBy('name')->get(['id', 'name', 'email', 'is_active'])
                ->map->only(['id', 'name', 'email', 'is_active'])->values()->all(),
        ];
    }

    /** @return array<string, mixed> the company's contact columns, for recordChange() */
    public static function contactValues(Company $company): array
    {
        return $company->only(self::CONTACT_FIELDS);
    }

    /** Log a contact change when one of the contact columns differs from $before. Returns whether one was logged. */
    public function recordChange(Company $company, array $before, ?User $actor, string $source): bool
    {
        $company->refresh();
        $after = self::contactValues($company);
        $changedBefore = [];
        $changedAfter = [];
        foreach (self::CONTACT_FIELDS as $field) {
            if (json_encode($before[$field] ?? null) !== json_encode($after[$field] ?? null)) {
                $changedBefore[$field] = $before[$field] ?? null;
                $changedAfter[$field] = $after[$field] ?? null;
            }
        }
        if ($changedAfter === []) {
            return false;
        }

        CompanyContactChange::create([
            'company_id' => $company->id, 'changed_by' => $actor?->id, 'source' => $source,
            'before' => $changedBefore, 'after' => $changedAfter,
        ]);

        return true;
    }

    /** July–June academic year of a placement, e.g. 2026-07-01 → "2026-27", 2027-01-10 → "2026-27". */
    public static function academicYear(?PlacementCycle $cycle): string
    {
        $start = $cycle?->starts_on;
        if (! $start) {
            return 'Unknown';
        }
        $year = (int) $start->format('Y') - ((int) $start->format('n') < 7 ? 1 : 0);

        return sprintf('%d-%02d', $year, ($year + 1) % 100);
    }

    /**
     * One row per company with its visits (job profiles opened, not cancelled), newest year first.
     *
     * @param  array{year?: ?string, type?: ?string, status?: ?string, q?: ?string}  $filters
     * @return array{companies: list<array<string, mixed>>, years: list<string>}
     */
    public function overview(array $filters = []): array
    {
        $postings = $this->postings()->where('status', '!=', 'cancelled')->get();
        $byCompany = $postings->groupBy(fn (JobPosting $p) => $p->postable?->company_id);
        $acceptedNotOpened = $this->acceptedNotOpened()->countBy('company_id');
        $offers = Offer::query()->selectRaw('company_id, count(*) as total')->groupBy('company_id')->pluck('total', 'company_id');

        $years = $postings->map(fn (JobPosting $p) => self::academicYear($p->placementCycle))->unique()->sortDesc()->values();
        $year = $filters['year'] ?? null;
        $type = $filters['type'] ?? null;
        $status = $filters['status'] ?? null;
        $term = mb_strtolower(trim((string) ($filters['q'] ?? '')));

        // "Did not return" compares with the chosen year, or the latest year anyone came.
        $compareYear = $year ?? $years->first();

        $companies = Company::query()->orderBy('name')->get()->map(function (Company $company) use ($byCompany, $acceptedNotOpened, $offers) {
            /** @var Collection<int, JobPosting> $mine */
            $mine = $byCompany->get($company->id, collect());
            $visits = $mine->groupBy(fn (JobPosting $p) => self::academicYear($p->placementCycle))->sortKeysDesc()
                ->map(fn (Collection $list, string $y) => [
                    'year' => $y,
                    'placements' => $list->map(fn (JobPosting $p) => $p->placementCycle?->only(['id', 'name', 'type']))->unique('id')->values()->all(),
                    'types' => $list->map(fn (JobPosting $p) => $p->placementCycle?->type)->filter()->unique()->values()->all(),
                ])->values();

            return [
                'id' => $company->id,
                'name' => $company->name,
                'sector' => $company->sector,
                'logo_url' => $company->logo_url,
                'visits' => $visits->all(),
                'first_year' => $visits->last()['year'] ?? null,
                'last_year' => $visits->first()['year'] ?? null,
                'job_profiles' => $mine->count(),
                'applicants' => (int) $mine->sum('applicants_count'),
                'offers' => (int) ($offers[$company->id] ?? 0),
                'accepted_not_opened' => (int) ($acceptedNotOpened[$company->id] ?? 0),
                'hr' => self::contactSnapshot($company),
            ];
        })->filter(function (array $row) use ($year, $type, $status, $term, $compareYear) {
            if ($term !== '' && ! str_contains(mb_strtolower($row['name'].' '.$row['hr']['name'].' '.$row['hr']['email']), $term)) {
                return false;
            }
            // Only the visits of the chosen kind (Full-Time / Internship) count for the year filters.
            $visits = collect($row['visits'])->when($type, fn ($v) => $v->filter(fn ($visit) => in_array($type, $visit['types'], true)));

            return match ($status) {
                // Came in an earlier year than the one compared with, but not in it.
                'not_returned' => $compareYear !== null
                    && $visits->contains(fn ($v) => strcmp($v['year'], $compareYear) < 0)
                    && ! $visits->contains('year', $compareYear),
                'never' => collect($row['visits'])->isEmpty(),
                // Default: everyone, narrowed to those who came in the chosen year and/or with the chosen kind.
                default => ($year === null && $type === null) || ($year === null ? $visits->isNotEmpty() : $visits->contains('year', $year)),
            };
        })->values();

        return ['companies' => $companies->all(), 'years' => $years->all()];
    }

    /** The company's history by academic year → placement → job profile, its contacts and their change log. */
    public function detail(Company $company): array
    {
        $postings = $this->postings()
            ->whereHasMorph('postable', [Jnf::class, Inf::class], fn ($q) => $q->where('company_id', $company->id))
            ->withCount(['offers', 'rounds', 'rounds as completed_rounds_count' => fn ($q) => $q->where('status', 'completed')])
            ->get();

        $years = $postings->groupBy(fn (JobPosting $p) => self::academicYear($p->placementCycle))->sortKeysDesc()
            ->map(fn (Collection $list, string $year) => [
                'year' => $year,
                'counted' => $list->contains(fn (JobPosting $p) => $p->status !== 'cancelled'),
                'placements' => $list->groupBy('placement_cycle_id')->map(fn (Collection $group) => [
                    'placement_cycle' => $group->first()->placementCycle?->only(['id', 'name', 'type', 'status']),
                    'job_profiles' => $group->sortBy('floated_at')->map(fn (JobPosting $p) => $this->jobProfile($p))->values()->all(),
                ])->values()->all(),
            ])->values();

        return [
            'company' => $company->only(['id', 'name', 'sector', 'industry', 'website']) + ['logo_url' => $company->logo_url],
            'summary' => [
                'years' => $years->where('counted', true)->pluck('year')->values()->all(),
                'job_profiles' => $postings->where('status', '!=', 'cancelled')->count(),
                'applicants' => (int) $postings->where('status', '!=', 'cancelled')->sum('applicants_count'),
                'offers' => (int) $postings->sum('offers_count'),
            ],
            'history' => $years->all(),
            'accepted_not_opened' => $this->acceptedNotOpened($company->id)->map(fn ($f) => [
                'form_type' => $f->form_type, 'id' => $f->id, 'title' => $f->title, 'accepted_at' => $f->updated_at,
            ])->values()->all(),
            'contacts' => self::contacts($company),
            'contact_changes' => CompanyContactChange::query()->where('company_id', $company->id)->with('changedBy:id,name')->latest('id')->limit(100)->get()
                ->map(fn (CompanyContactChange $c) => [
                    'at' => $c->created_at, 'source' => $c->source, 'by' => $c->changedBy?->name, 'before' => $c->before, 'after' => $c->after,
                ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function jobProfile(JobPosting $p): array
    {
        $signatory = $p->formData()['signatory'] ?? [];

        return [
            'id' => $p->id,
            'title' => $p->title(),
            'form_type' => $p->formType(),
            'type' => $p->postingType(),
            'offer_type' => $p->offerType(),
            'offer_label' => Offer::LABELS[$p->offerType()] ?? $p->offerType(),
            'status' => $p->status,
            'opened_at' => $p->floated_at,
            'application_deadline' => $p->application_deadline,
            'compensation' => $p->compensationFor(null),
            'applicants' => (int) $p->applicants_count,
            'offers' => (int) $p->offers_count,
            'stages' => (int) $p->rounds_count,
            'stages_completed' => (int) $p->completed_rounds_count,
            'signatory' => is_array($signatory) ? array_filter(['name' => $signatory['name'] ?? null, 'designation' => $signatory['designation'] ?? null]) : [],
            'hr_at_opening' => $p->hr_snapshot, // null for job profiles opened before 20-10-2026
        ];
    }

    private function postings()
    {
        return JobPosting::query()
            ->whereNotNull('floated_at')
            ->where(fn ($q) => $q->whereNull('scheduled_open_at')->orWhere('scheduled_open_at', '<=', now()))
            ->with(['placementCycle:id,name,type,status,starts_on', 'postable'])
            ->withCount(['applications as applicants_count' => fn ($q) => $q->where('status', 'applied')]);
    }

    /** Accepted JNFs / INFs never opened as a job profile. */
    private function acceptedNotOpened(?int $companyId = null): Collection
    {
        $opened = fn (string $class) => JobPosting::query()->where('postable_type', $class)->select('postable_id');
        $jnfs = Jnf::query()->where('status', 'accepted')->whereNotIn('id', $opened(Jnf::class))->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->get(['id', 'company_id', 'job_title', 'updated_at'])->map(fn ($f) => (object) ['form_type' => 'jnf', 'id' => $f->id, 'company_id' => $f->company_id, 'title' => $f->job_title, 'updated_at' => $f->updated_at]);
        $infs = Inf::query()->where('status', 'accepted')->whereNotIn('id', $opened(Inf::class))->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->get(['id', 'company_id', 'internship_title', 'updated_at'])->map(fn ($f) => (object) ['form_type' => 'inf', 'id' => $f->id, 'company_id' => $f->company_id, 'title' => $f->internship_title, 'updated_at' => $f->updated_at]);

        return $jnfs->concat($infs)->values();
    }
}
