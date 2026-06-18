<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Form Submitted</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5;">
    <h2>IIT ISM CDC Portal</h2>
    <p>A new {{ $formType }} has been submitted.</p>
    <p><strong>Company:</strong> {{ $companyName }}</p>
    <p><strong>Form Title:</strong> {{ $title }}</p>

    @if(!empty($role) || !empty($location) || !empty($compensation) || !empty($eligibility))
        <p style="margin-top: 14px;"><strong>Quick Summary</strong></p>
        <ul style="margin-top: 6px; padding-left: 18px;">
            @if(!empty($role))
                <li><strong>Role:</strong> {{ $role }}</li>
            @endif
            @if(!empty($location))
                <li><strong>Location:</strong> {{ $location }}</li>
            @endif
            @if(!empty($compensation))
                <li><strong>Stipend / CTC:</strong> {{ $compensation }}</li>
            @endif
            @if(!empty($eligibility))
                <li><strong>Eligibility:</strong> {{ $eligibility }}</li>
            @endif
        </ul>
    @endif

    @if(!empty($reviewUrl))
        <p style="margin-top: 18px;">
            <a href="{{ $reviewUrl }}" style="display:inline-block;background:#1976d2;color:#fff;text-decoration:none;padding:10px 16px;border-radius:6px;font-weight:600;">
                Review in CDC Portal
            </a>
        </p>
        <p style="font-size: 12px; color: #666; word-break: break-all;">{{ $reviewUrl }}</p>
    @else
        <p>Please review it in the CDC portal.</p>
    @endif
</body>
</html>
