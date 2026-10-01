<?php

namespace App\Http\Controllers;

use App\Jobs\SendEventAnnouncements;
use App\Models\CampusEvent;
use App\Services\AuditService;
use App\Services\MailDispatchService;
use App\Support\Ist;
use App\Support\ProgrammeCatalogue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Announce-only events (spec Q7 / M8.1). Publishing sends E6 to the audience.
 */
class AdminEventController extends Controller
{
    private const TRACKED = ['title', 'event_type', 'company_id', 'starts_at', 'venue', 'meeting_link', 'description', 'audience_type', 'audience_filter', 'published_at'];

    public function __construct(
        private readonly AuditService $audit,
        private readonly MailDispatchService $mail
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'when' => ['nullable', 'in:upcoming,past'],
        ]);

        $query = CampusEvent::query()->with(['company:id,name', 'createdBy:id,name']);

        if (($validated['when'] ?? null) === 'past') {
            $query->where('starts_at', '<', now())->orderByDesc('starts_at');
        } elseif (($validated['when'] ?? null) === 'upcoming') {
            $query->where('starts_at', '>=', now())->orderBy('starts_at');
        } else {
            $query->orderByDesc('starts_at');
        }

        return response()->json([
            'events' => $query->limit(500)->get()->map(fn (CampusEvent $e) => $e->toArray() + [
                'audience_count' => $e->audienceQuery()->count(),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);

        $event = CampusEvent::create($validated + ['created_by' => $request->user()->id]);
        $this->audit->log($request, 'event.create', $event, null, $event->only(self::TRACKED));

        return response()->json(['message' => 'Event saved as a draft. Publish it to notify students.', 'event' => $event->load('company:id,name')], 201);
    }

    public function update(Request $request, CampusEvent $campusEvent): JsonResponse
    {
        $validated = $this->validated($request);

        $before = $campusEvent->only(self::TRACKED);
        $campusEvent->update($validated);
        $this->audit->log($request, 'event.update', $campusEvent, $before, $campusEvent->only(self::TRACKED));

        return response()->json(['message' => 'Event updated.', 'event' => $campusEvent->fresh()->load('company:id,name')]);
    }

    public function destroy(Request $request, CampusEvent $campusEvent): JsonResponse
    {
        $before = $campusEvent->only(self::TRACKED);
        $campusEvent->delete();
        $this->audit->log($request, 'event.delete', null, $before + ['id' => $campusEvent->id], null);

        return response()->json(['message' => 'Event deleted.']);
    }

    public function publish(Request $request, CampusEvent $campusEvent): JsonResponse
    {
        if ($campusEvent->published_at) {
            return response()->json(['message' => 'This event has already been published.'], 422);
        }

        $campusEvent->update(['published_at' => now()]);
        $audience = $campusEvent->audienceQuery()->count();
        $this->audit->log($request, 'event.publish', $campusEvent, ['published_at' => null], ['published_at' => $campusEvent->published_at->toIso8601String(), 'audience' => $audience]);

        if ($this->mail->mode() === 'sync') {
            SendEventAnnouncements::dispatchSync($campusEvent->id);
        } else {
            SendEventAnnouncements::dispatch($campusEvent->id);
        }

        return response()->json([
            'message' => "Event published. {$audience} student(s) are being notified.",
            'event' => $campusEvent->fresh()->load('company:id,name'),
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'event_type' => ['required', 'in:ppt,workshop,webinar,other'],
            'starts_at' => ['required', 'date'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'venue' => ['nullable', 'string', 'max:255'],
            'meeting_link' => ['nullable', 'url', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'audience_type' => ['nullable', 'in:all,branches,posting_applicants'],
            'audience_filter' => ['nullable', 'array'],
            'audience_filter.branches' => ['required_if:audience_type,branches', 'array'],
            'audience_filter.branches.*.programme' => ['required', 'string'],
            'audience_filter.branches.*.branch' => ['nullable', 'string'],
            'audience_filter.job_posting_id' => ['required_if:audience_type,posting_applicants', 'integer', 'exists:job_postings,id'],
        ], [
            'audience_filter.branches.required_if' => 'Pick at least one programme or branch.',
            'audience_filter.job_posting_id.required_if' => 'Pick the posting whose applicants should see this event.',
        ]);

        $validated['audience_type'] ??= 'all';
        $validated['starts_at'] = Ist::parse($validated['starts_at']); // IST unless it carries an offset (QA F-005)

        foreach ($validated['audience_filter']['branches'] ?? [] as $pair) {
            $ok = empty($pair['branch'])
                ? ProgrammeCatalogue::hasProgramme($pair['programme'])
                : ProgrammeCatalogue::has($pair['programme'], $pair['branch']);
            abort_unless($ok, 422, sprintf('"%s" is not in the programme catalogue.', ($pair['branch'] ?? '') ?: $pair['programme']));
        }

        $validated['audience_filter'] = match ($validated['audience_type']) {
            'branches' => ['branches' => array_values($validated['audience_filter']['branches'])],
            'posting_applicants' => ['job_posting_id' => (int) $validated['audience_filter']['job_posting_id']],
            default => null,
        };

        return $validated;
    }
}
