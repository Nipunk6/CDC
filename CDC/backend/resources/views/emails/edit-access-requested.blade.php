<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Access Requested</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5;">
    <h2>IIT ISM CDC Portal</h2>
    <p>A company has requested edit access for a submitted {{ $formType }}.</p>
    <p><strong>Company:</strong> {{ $companyName }}</p>
    <p><strong>Form Title:</strong> {{ $title }}</p>
    <p><strong>Reason:</strong> {{ $reason }}</p>

    @if(!empty($reviewUrl))
        <p style="margin-top: 18px;">
            <a href="{{ $reviewUrl }}" style="display:inline-block;background:#1976d2;color:#fff;text-decoration:none;padding:10px 16px;border-radius:6px;font-weight:600;">
                Review in CDC Portal
            </a>
        </p>
        <p style="font-size: 12px; color: #666; word-break: break-all;">{{ $reviewUrl }}</p>
    @endif
</body>
</html>
