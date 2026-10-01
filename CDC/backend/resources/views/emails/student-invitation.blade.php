@extends('emails.layouts.portal')

@section('title', 'Your CDC Portal account')

@section('content')
    <p>Hello {{ $name }},</p>
    <p>Your account on the IIT (ISM) CDC Placement Portal has been created. You will use it to view job profiles, apply to companies and track your placement process.</p>
    <p><strong>Username (Roll Number):</strong> {{ $rollNo }}</p>
    <p>Set your password using the button below to activate your account.</p>
    @include('emails.partials.button', ['url' => $setPasswordUrl, 'label' => 'Set Your Password'])
    <p style="font-size:13px;color:#6b7280;">This link is valid for {{ intdiv((int) config('auth.passwords.invites.expire'), 1440) }} days. If it has expired, open the student login page and use <em>Forgot password</em> with your roll number to get a new one.</p>
    <p>Student login: <a href="{{ $loginUrl }}">{{ $loginUrl }}</a></p>
@endsection
