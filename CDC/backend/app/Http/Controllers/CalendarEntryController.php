<?php

namespace App\Http\Controllers;

use App\Models\CalendarEntry;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Personal calendar entries for every role. Each user reads and changes only their own; the calendar shows them
 * beside the automatic items from CalendarController.
 */
class CalendarEntryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        // Same IST month window as CalendarController.
        $start = CarbonImmutable::createFromFormat('Y-m-d', ($validated['month'] ?? now('Asia/Kolkata')->format('Y-m')).'-01', 'Asia/Kolkata')->startOfDay();

        $entries = CalendarEntry::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('starts_at', [$start->utc(), $start->endOfMonth()->utc()])
            ->orderBy('starts_at')
            ->get();

        return response()->json(['items' => $entries->map(fn (CalendarEntry $e) => $this->item($e))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $entry = $request->user()->calendarEntries()->create($this->attributes($this->validated($request, false)));

        return response()->json(['item' => $this->item($entry)], 201);
    }

    /** Partial: dragging sends only `starts_at`, marking done only `done`. */
    public function update(Request $request, CalendarEntry $calendarEntry): JsonResponse
    {
        $this->authorizeOwner($request, $calendarEntry);
        $calendarEntry->fill($this->attributes($this->validated($request, true)));
        // A new time or reminder is reminded afresh.
        if ($calendarEntry->isDirty(['starts_at', 'remind_minutes'])) {
            $calendarEntry->reminded_at = null;
        }
        $calendarEntry->save();

        return response()->json(['item' => $this->item($calendarEntry)]);
    }

    public function destroy(Request $request, CalendarEntry $calendarEntry): JsonResponse
    {
        $this->authorizeOwner($request, $calendarEntry);
        $calendarEntry->delete();

        // A JSON body, not 204: the portal's api helpers parse every response.
        return response()->json(['deleted' => true]);
    }

    private function validated(Request $request, bool $partial): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$required, 'string', 'max:150'],
            'starts_at' => [$required, 'date'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'color' => ['sometimes', 'nullable', Rule::in(CalendarEntry::COLORS)],
            'remind_minutes' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:40320'],
            'done' => ['sometimes', 'boolean'],
        ]);
    }

    private function attributes(array $validated): array
    {
        if (array_key_exists('done', $validated)) {
            $validated['done_at'] = $validated['done'] ? now() : null;
            unset($validated['done']);
        }

        return $validated;
    }

    // Someone else's entry answers 404, so ids reveal nothing.
    private function authorizeOwner(Request $request, CalendarEntry $entry): void
    {
        abort_if((int) $entry->user_id !== (int) $request->user()->id, 404);
    }

    private function item(CalendarEntry $e): array
    {
        return [
            'id' => $e->id,
            'type' => 'personal',
            'at' => $e->starts_at,
            'title' => $e->title,
            'subtitle' => '',
            'note' => $e->note,
            'color' => $e->color,
            'done' => $e->done_at !== null,
            'remind_minutes' => $e->remind_minutes,
            'draft' => false,
            'link' => null,
        ];
    }
}
