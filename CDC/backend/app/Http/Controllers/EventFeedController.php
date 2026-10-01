<?php

namespace App\Http\Controllers;

use App\Models\CampusEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only event lists for students (their audience) and companies (their own events).
 */
class EventFeedController extends Controller
{
    public function student(Request $request): JsonResponse
    {
        $student = $request->user()->studentProfile;
        abort_if(! $student, 404, 'Student profile not found.');

        $events = CampusEvent::query()
            ->with('company:id,name,logo_path')
            ->whereNotNull('published_at')
            ->where('starts_at', '>=', now()->subDays(60))
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (CampusEvent $e) => $e->isVisibleTo($student))
            ->values();

        return response()->json(['events' => $events->map(fn (CampusEvent $e) => $this->payload($e))]);
    }

    public function company(Request $request): JsonResponse
    {
        $events = CampusEvent::query()
            ->where('company_id', $request->user()->company_id)
            ->whereNotNull('published_at')
            ->orderByDesc('starts_at')
            ->get();

        return response()->json(['events' => $events->map(fn (CampusEvent $e) => $this->payload($e))]);
    }

    private function payload(CampusEvent $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'event_type' => $event->event_type,
            'starts_at' => $event->starts_at,
            'venue' => $event->venue,
            'meeting_link' => $event->meeting_link,
            'description' => $event->description,
            'company' => $event->company ? ['name' => $event->company->name, 'logo_url' => $event->company->logo_url] : null,
        ];
    }
}
