@extends('emails.layouts.portal')

@section('title', 'Application received')

@section('content')
    <p>Hello {{ $name }},</p>
    <p>Your application for <strong>{{ $title }}</strong> at <strong>{{ $companyName }}</strong> has been received.</p>
    <p><strong>Resume attached:</strong> {{ $resumeLabel }}</p>
    @if ($unverifiedResume)
        <p style="padding:12px;background:#fff7ed;border-left:4px solid #b45309;">This resume is not verified yet. Get your resume verified by the CDC as soon as possible.</p>
    @endif
    <p>You can change the attached resume, edit your answers or withdraw until <strong>{{ $deadline }}</strong>.</p>
    @include('emails.partials.button', ['url' => $url, 'label' => 'My Applications'])
@endsection
