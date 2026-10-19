<?php

namespace App\Http\Controllers;

use App\Models\CalendarOverride;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A user's own changes to automatic calendar items: rename, move, note, hide, mark done, remind. They change only that
 * user's calendar, never the event or job profile behind the item; deleting the override resets the item.
 * A null title or starts_at means "as the placement data says".
 */
class CalendarOverrideController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $overrides = CalendarOverride::query()->where('user_id', $request->user()->id)->get();

        return response()->json(['overrides' => $overrides->map(fn (CalendarOverride $o) => $this->item($o))->values()]);
    }

    /** Partial: only the fields sent change. */
    public function upsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'regex:/^(event|deadline|round|visit):\d+$/'],
            'label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'hidden' => ['sometimes', 'boolean'],
            'done' => ['sometimes', 'boolean'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'link' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:#^/#'],
            'remind_minutes' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:40320'],
        ]);

        $override = CalendarOverride::firstOrNew(['user_id' => $request->user()->id, 'item_key' => $validated['key']]);
        $override->type ??= explode(':', $validated['key'])[0];
        if (array_key_exists('done', $validated)) {
            $override->done_at = $validated['done'] ? now() : null;
        }
        $override->fill(collect($validated)->except(['key', 'done'])->all());
        if ($override->isDirty(['starts_at', 'remind_minutes'])) {
            $override->reminded_at = null;
        }
        $override->save();

        return response()->json(['override' => $this->item($override)]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate(['key' => ['required', 'string', 'max:40']]);
        CalendarOverride::query()->where('user_id', $request->user()->id)->where('item_key', $validated['key'])->delete();

        return response()->json(['deleted' => true]);
    }

    private function item(CalendarOverride $o): array
    {
        return [
            'key' => $o->item_key,
            'type' => $o->type,
            'hidden' => (bool) $o->hidden,
            'done' => $o->done_at !== null,
            'title' => $o->title,
            'at' => $o->starts_at,
            'label' => $o->label,
            'note' => $o->note,
            'link' => $o->link,
            'remind_minutes' => $o->remind_minutes,
        ];
    }
}
