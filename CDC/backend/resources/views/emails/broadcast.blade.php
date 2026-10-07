@extends('emails.layouts.portal')

@section('title', $subjectLine)

@section('content')
    <p>{{ $greeting }}</p>
    <p><strong>{{ $headline }}</strong></p>
    @foreach ($lines as $line)
        <p>{{ $line }}</p>
    @endforeach
    @if ($attachmentName)
        <p style="color:#555;">Attachment: {{ $attachmentName }}</p>
    @endif
    @if ($actionUrl)
        @include('emails.partials.button', ['url' => $actionUrl, 'label' => $actionLabel ?? 'Open Portal'])
    @endif
@endsection
