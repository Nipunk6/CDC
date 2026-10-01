@extends('emails.layouts.portal')

@section('title', $title)

@section('content')
    <p>Dear Students,</p>
    <p>The CDC has announced a {{ strtolower($typeLabel) }}{{ $companyName ? ' by '.$companyName : '' }}.</p>
    <table role="presentation" cellpadding="6" style="border-collapse:collapse;margin:12px 0;">
        <tr><td style="color:#6b7280;">Event</td><td><strong>{{ $title }}</strong></td></tr>
        <tr><td style="color:#6b7280;">When</td><td><strong>{{ $when }}</strong></td></tr>
        @if ($venue)
            <tr><td style="color:#6b7280;">Venue</td><td>{{ $venue }}</td></tr>
        @endif
        @if ($meetingLink)
            <tr><td style="color:#6b7280;">Join</td><td><a href="{{ $meetingLink }}">{{ $meetingLink }}</a></td></tr>
        @endif
    </table>
    @if ($description)
        <p style="white-space:pre-wrap;">{{ $description }}</p>
    @endif
    @include('emails.partials.button', ['url' => rtrim((string) config('app.frontend_url'), '/').'/student/events', 'label' => 'View Events'])
@endsection
