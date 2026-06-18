<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Verification</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5; color: #1f2937;">
    <h2 style="margin-bottom: 12px;">IIT ISM CDC Portal</h2>
    <p>Hello {{ $name }},</p>
    <p>Thank you for starting company registration. Please verify your recruiter email address to continue.</p>
    <p style="margin: 24px 0;">
        <a href="{{ $verifyUrl }}" style="display: inline-block; padding: 10px 16px; background: #0f766e; color: #ffffff; text-decoration: none; border-radius: 6px;">
            Verify Recruiter Email
        </a>
    </p>
    <p>If the button does not work, copy and paste this link into your browser:</p>
    <p><a href="{{ $verifyUrl }}">{{ $verifyUrl }}</a></p>
    <p>This verification link will expire in {{ $expiryMinutes }} minutes.</p>
    <p>If you did not request this, you can safely ignore this email.</p>
</body>
</html>
