@extends('emails.layouts.portal')

@section('title', $subjectLine)

@section('content')
    <p>Hello {{ $name }},</p>
    @if ($kind === 'assigned')
        <p>The CDC has selected you as a <strong>SPOC</strong> (single point of contact) for this job profile:</p>
    @elseif ($kind === 'hr_updated')
        <p>The HR contact of a company you are SPOC for has changed. These are the current details.</p>
    @else
        <p>You are <strong>no longer a SPOC</strong> for this job profile. No further action is needed from you for it.</p>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:12px 0;">
        <tr><td style="padding:4px 8px 4px 0;color:#5A626C;width:140px;">Company</td><td style="padding:4px 0;"><strong>{{ $companyName }}</strong></td></tr>
        <tr><td style="padding:4px 8px 4px 0;color:#5A626C;">Role</td><td style="padding:4px 0;">{{ $role }}</td></tr>
        <tr><td style="padding:4px 8px 4px 0;color:#5A626C;">Placement</td><td style="padding:4px 0;">{{ $cycleName }}</td></tr>
        @if ($deadline)
            <tr><td style="padding:4px 8px 4px 0;color:#5A626C;">Apply by</td><td style="padding:4px 0;">{{ $deadline }}</td></tr>
        @endif
    </table>

    @if ($kind !== 'removed')
        <p style="margin:16px 0 4px;"><strong>Current HR details</strong></p>
        <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:0 0 12px;">
            <tr><td style="padding:4px 8px 4px 0;color:#5A626C;width:140px;">Name</td><td style="padding:4px 0;">{{ $hr['name'] ?: '—' }}</td></tr>
            <tr><td style="padding:4px 8px 4px 0;color:#5A626C;">Designation</td><td style="padding:4px 0;">{{ $hr['designation'] ?: '—' }}</td></tr>
            <tr><td style="padding:4px 8px 4px 0;color:#5A626C;">Email</td><td style="padding:4px 0;">{{ $hr['email'] ?: '—' }}</td></tr>
            <tr><td style="padding:4px 8px 4px 0;color:#5A626C;">Phone</td><td style="padding:4px 0;">{{ $hr['phone'] ?: '—' }}@if (! empty($hr['alt_phone'])) · {{ $hr['alt_phone'] }}@endif</td></tr>
        </table>
        <p>Students of this job profile can see your name, phone and email so that they can contact you.</p>
    @endif

    @include('emails.partials.button', ['url' => rtrim((string) config('app.frontend_url'), '/').'/student/spoc', 'label' => 'My SPOC Companies'])
@endsection
