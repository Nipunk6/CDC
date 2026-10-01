@extends('emails.layouts.portal')

@section('title', 'New opening')

@section('content')
    <p>Dear Students,</p>
    <p>A new {{ $typeLabel }} opening you are eligible for is now open on the CDC portal.</p>
    <table role="presentation" cellpadding="6" style="border-collapse:collapse;margin:12px 0;">
        <tr><td style="color:#6b7280;">Company</td><td><strong>{{ $companyName }}</strong></td></tr>
        <tr><td style="color:#6b7280;">Role</td><td><strong>{{ $title }}</strong></td></tr>
        <tr><td style="color:#6b7280;">Apply by</td><td><strong>{{ $deadline }}</strong></td></tr>
    </table>
    @include('emails.partials.button', ['url' => $url, 'label' => 'View & Apply'])
@endsection
