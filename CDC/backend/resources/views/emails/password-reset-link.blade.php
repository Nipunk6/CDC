<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Reset</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5; color: #1f2937;">
    <h2 style="margin-bottom: 12px;">IIT ISM CDC Portal</h2>
    <p>Hello {{ $name }},</p>
    <p>We received a request to reset your account password.</p>
    <p style="margin: 24px 0;">
        <a href="{{ $resetUrl }}" style="display: inline-block; padding: 10px 16px; background: #2563eb; color: #ffffff; text-decoration: none; border-radius: 6px;">
            Reset Password
        </a>
    </p>
    <p>If the button does not work, copy and paste this link into your browser:</p>
    <p><a href="{{ $resetUrl }}">{{ $resetUrl }}</a></p>
    <p>This link is time-limited for security reasons. If you did not request this, you can ignore this email.</p>
</body>
</html>
