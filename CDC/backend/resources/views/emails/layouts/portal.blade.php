<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'IIT ISM CDC Portal')</title>
</head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:Arial,sans-serif;line-height:1.5;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="background:#7B1113;color:#ffffff;padding:18px 24px;">
                            <div style="font-size:18px;font-weight:700;">IIT (ISM) Dhanbad</div>
                            <div style="font-size:13px;opacity:0.9;">Career Development Centre</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:14px 24px;background:#fafafa;color:#6b7280;font-size:12px;">
                            This is an automated message from the IIT ISM CDC Portal. Please do not reply to this email.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
