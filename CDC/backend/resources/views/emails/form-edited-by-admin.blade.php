<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $formType }} Updated by Admin</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2 style="color: #1976d2;">IIT ISM CDC Portal</h2>
    <p>Your <strong>{{ $formType }}</strong> &ldquo;{{ $title }}&rdquo; has been updated by an administrator.</p>

    @if (count($changedFields) > 0)
        <p>The following fields were modified:</p>
        <table style="border-collapse: collapse; width: 100%; max-width: 640px; margin-top: 12px;">
            <thead>
                <tr style="background-color: #f5f5f5;">
                    <th style="border: 1px solid #ddd; padding: 8px 12px; text-align: left;">Field</th>
                    <th style="border: 1px solid #ddd; padding: 8px 12px; text-align: left;">Previous Value</th>
                    <th style="border: 1px solid #ddd; padding: 8px 12px; text-align: left;">Updated Value</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($changedFields as $field => $values)
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 8px 12px;">{{ $field }}</td>
                        <td style="border: 1px solid #ddd; padding: 8px 12px; color: #b71c1c;">{{ $values['old'] ?: '—' }}</td>
                        <td style="border: 1px solid #ddd; padding: 8px 12px; color: #2e7d32;">{{ $values['new'] ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p style="margin-top: 16px;">Please log in to the company portal for full details.</p>
</body>
</html>
