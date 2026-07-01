<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Form Status Updated</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5;">
    <h2>IIT ISM CDC Portal</h2>
    <p>Your {{ $formType }} status has been updated.</p>
    <p><strong>Form Title:</strong> {{ $title }}</p>
    <p><strong>New Status:</strong> {{ $status }}</p>
    @if (!empty($remarks))
        <p><strong>Admin Remarks:</strong> {{ $remarks }}</p>
    @endif
    <p>Please login to the company portal for details.</p>
    <p>
        <a href="{{ config('app.frontend_url') }}/auth/login/recruiter" style="display:inline-block;background:#2e7d32;color:#fff;text-decoration:none;padding:10px 16px;border-radius:6px;font-weight:600;">
            Login to Company Portal
        </a>
    </p>
</body>
</html>
