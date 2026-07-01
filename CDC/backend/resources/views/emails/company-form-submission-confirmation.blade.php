<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submission Confirmation</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5;">
    <h2>IIT ISM CDC Portal</h2>
    <p>Dear {{ $companyName }},</p>
    <p>We have received your {{ $formType }} submission.</p>
    <p><strong>Form Title:</strong> {{ $title }}</p>
    <p>Your form is now in the review workflow. We will notify you when its status changes.</p>

    @if(!empty($formUrl))
        <p style="margin-top: 18px;">
            <a href="{{ $formUrl }}" style="display:inline-block;background:#1976d2;color:#fff;text-decoration:none;padding:10px 16px;border-radius:6px;font-weight:600;">
                View Submission
            </a>
        </p>
        <p style="font-size: 12px; color: #666; word-break: break-all;">{{ $formUrl }}</p>
    @endif

    @if(empty($formUrl))
        <p style="margin-top: 18px;">
            <a href="{{ config('app.frontend_url') }}/auth/login/recruiter" style="display:inline-block;background:#2e7d32;color:#fff;text-decoration:none;padding:10px 16px;border-radius:6px;font-weight:600;">
                Login to Company Portal
            </a>
        </p>
    @endif

    <p style="margin-top: 18px;">Regards,<br>IIT ISM CDC Team</p>
</body>
</html>
