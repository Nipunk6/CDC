<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Company Registration</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5;">
    <h2>IIT ISM CDC Portal</h2>
    <p>A new company has registered on the portal.</p>
    <p><strong>Company:</strong> {{ $companyName }}</p>
    <p><strong>HR Contact:</strong> {{ $hrName }} ({{ $hrEmail }})</p>
    <p>Please review company details in the admin portal.</p>
    <p>
        <a href="{{ config('app.frontend_url') }}/auth/login/admin" style="display:inline-block;background:#c62828;color:#fff;text-decoration:none;padding:10px 16px;border-radius:6px;font-weight:600;">
            Login to Admin Portal
        </a>
    </p>
</body>
</html>
