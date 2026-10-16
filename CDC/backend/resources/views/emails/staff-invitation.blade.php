@extends('emails.layouts.portal')

@section('title', 'Your CDC Admin Portal access')

@section('content')
    <p>Hello {{ $name }},</p>
    <p>{{ $invitedBy }} has given you access to the <strong>IIT (ISM) CDC Admin Portal</strong> with the role <strong>{{ $roleName }}</strong>.</p>
    @if ($roleDescription)
        <p style="color:#374151;">{{ $roleDescription }}</p>
    @endif

    <p style="margin-bottom:6px;"><strong>What your role lets you do</strong></p>
    @if ($fullAccess)
        <p>Everything in the admin portal except managing users and roles.</p>
    @elseif (count($permissions) === 0)
        <p>Nothing yet. A super admin will add permissions to your role.</p>
    @else
        <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px;margin-bottom:12px;">
            @foreach ($permissions as $group)
                <tr><td colspan="2" style="padding:8px 0 2px;font-weight:bold;color:#7f1d1d;">{{ $group['group'] }}</td></tr>
                @foreach ($group['items'] as $item)
                    <tr>
                        <td style="padding:2px 8px 2px 0;border-bottom:1px solid #f3f4f6;">{{ $item['label'] }}</td>
                        <td style="padding:2px 0;border-bottom:1px solid #f3f4f6;color:#374151;white-space:nowrap;">{{ $item['level'] }}</td>
                    </tr>
                @endforeach
            @endforeach
        </table>
        <p style="font-size:13px;color:#6b7280;">"Read" means you can view; "Read and verify" adds accepting or rejecting; "Read and write" adds creating, editing and sending.</p>
    @endif

    <p style="margin-bottom:6px;"><strong>Getting started</strong></p>
    <ol style="padding-left:20px;margin-top:0;">
        <li>Set your password with the button below.</li>
        <li>Sign in on the admin login page with <strong>{{ $email }}</strong>.</li>
        <li>Fill in your details (name, mobile number and designation) when the portal asks.</li>
    </ol>
    @include('emails.partials.button', ['url' => $setPasswordUrl, 'label' => 'Set Your Password'])
    <p style="font-size:13px;color:#6b7280;">This link is valid for {{ intdiv((int) config('auth.passwords.invites.expire'), 1440) }} days. If it has expired, ask a super admin to resend the invitation, or use <em>Forgot password</em> on the admin login page.</p>
    <p>Admin login: <a href="{{ $loginUrl }}">{{ $loginUrl }}</a></p>
@endsection
