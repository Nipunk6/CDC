@extends('emails.layouts.portal')

@section('title', $subjectLine)

@section('content')
    <p>{{ $greeting }}</p>
    <p>{{ $headline }}</p>
    @if (! empty($lines))
        <ul style="padding-left:18px;">
            @foreach ($lines as $line)
                <li>{{ $line }}</li>
            @endforeach
        </ul>
    @endif
    @if ($actionUrl)
        @include('emails.partials.button', ['url' => $actionUrl, 'label' => $actionLabel ?? 'Open Portal'])
    @endif
@endsection
