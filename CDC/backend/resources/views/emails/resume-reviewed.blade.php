@extends('emails.layouts.portal')

@section('title', $subjectLine)

@section('content')
    <p>Hello {{ $name }},</p>
    @if ($approved)
        <p>Your resume <strong>{{ $label }}</strong> has been verified by the CDC. Applications that use it are no longer flagged.</p>
    @else
        <p>Your resume <strong>{{ $label }}</strong> was not approved.</p>
        <p style="padding:12px;background:#fff7ed;border-left:4px solid #b45309;"><strong>CDC remark:</strong> {{ $remark }}</p>
        <p>Please upload a corrected version into the same slot. It will be reviewed again.</p>
    @endif
    @include('emails.partials.button', ['url' => rtrim((string) config('app.frontend_url'), '/').'/student/resumes', 'label' => 'My Resumes'])
@endsection
