@extends('emails.layouts.portal')

@section('title', $subjectLine)

@section('content')
    <p>Hello {{ $name }},</p>
    <p>{{ $headline }}</p>
    @if (! empty($lines))
        <ul style="padding-left:18px;">
            @foreach ($lines as $line)
                <li>{{ $line }}</li>
            @endforeach
        </ul>
    @endif
    <p>If anything here looks wrong, contact the CDC office.</p>
    @include('emails.partials.button', ['url' => rtrim((string) config('app.frontend_url'), '/').'/student/profile', 'label' => 'View Your Profile'])
@endsection
