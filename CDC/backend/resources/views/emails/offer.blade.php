@extends('emails.layouts.portal')

@section('title', 'Offer')

@section('content')
    <p>Hello {{ $name }},</p>
    <p>Congratulations! You have been selected by <strong>{{ $companyName }}</strong> for <strong>{{ $title }}</strong>.</p>
    <table role="presentation" cellpadding="6" style="border-collapse:collapse;margin:12px 0;">
        <tr><td style="color:#6b7280;">Offer type</td><td><strong>{{ $offerLabel }}</strong></td></tr>
        @if ($compensation)
            <tr><td style="color:#6b7280;">CTC Offered</td><td><strong>{{ $compensation }}</strong></td></tr>
        @endif
    </table>
    @if ($blockNote)
        <p style="padding:12px;background:#f3f4f6;border-left:4px solid #7B1113;">{{ $blockNote }}</p>
    @endif
    <p>The CDC will share next steps with you.</p>
    @include('emails.partials.button', ['url' => rtrim((string) config('app.frontend_url'), '/').'/student/applications', 'label' => 'My Applications'])
@endsection
