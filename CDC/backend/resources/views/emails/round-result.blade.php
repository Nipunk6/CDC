@extends('emails.layouts.portal')

@section('title', 'Round result')

@section('content')
    <p>{{ $name ? 'Hello '.$name : 'Dear Candidate' }},</p>
    @if ($outcome === 'selected')
        <p>Congratulations! You have been {{ $addendum ? 'added to the list of candidates' : 'selected' }} in <strong>{{ $roundName }}</strong> for <strong>{{ $title }}</strong> at <strong>{{ $companyName }}</strong>.</p>
        @if ($nextRound)
            <p>Next round: <strong>{{ $nextRound }}</strong>. Watch the portal and your email for the schedule.</p>
        @endif
    @elseif ($outcome === 'waitlisted')
        <p>You have been placed on the waitlist after <strong>{{ $roundName }}</strong> for <strong>{{ $title }}</strong> at <strong>{{ $companyName }}</strong>. We will write to you if a place opens up.</p>
    @else
        <p>Thank you for taking part in the selection process for <strong>{{ $title }}</strong> at <strong>{{ $companyName }}</strong>. Unfortunately you have not been shortlisted after <strong>{{ $roundName }}</strong>.</p>
        <p>Keep going — there are more opportunities on the portal.</p>
    @endif
    @include('emails.partials.button', ['url' => rtrim((string) config('app.frontend_url'), '/').'/student/applications', 'label' => 'My Applications'])
@endsection
