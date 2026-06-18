<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Alumni Outreach Confirmation</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">

    <div style="background: linear-gradient(135deg, #1565c0 0%, #0d47a1 100%); color: white; padding: 28px 24px; border-radius: 8px 8px 0 0; text-align: center;">
        <h1 style="margin: 0; font-size: 22px; font-weight: 700;">IIT (ISM) Dhanbad</h1>
        <p style="margin: 6px 0 0; font-size: 14px; opacity: 0.9;">Career Development Cell — Alumni Network</p>
    </div>

    <div style="background: #ffffff; padding: 28px 24px; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 8px 8px;">
        <p style="font-size: 16px;">Dear <strong>{{ $submission->full_name }}</strong>,</p>

        <p>Thank you for registering with the <strong>IIT (ISM) CDC Alumni Network</strong>. We have successfully received your outreach submission.</p>

        <p>Our CDC team will review your profile and reach out to you shortly regarding mentorship and referral opportunities.</p>

        <div style="background: #f5f5f5; border-left: 4px solid #1565c0; border-radius: 4px; padding: 16px 18px; margin: 20px 0;">
            <p style="margin: 0 0 8px; font-weight: 700; color: #1565c0; font-size: 14px;">Your Submission Details</p>

            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <tr>
                    <td style="padding: 4px 8px 4px 0; color: #666; width: 45%;">Graduation Year</td>
                    <td style="padding: 4px 0;"><strong>{{ $submission->graduation_year }}</strong></td>
                </tr>
                <tr>
                    <td style="padding: 4px 8px 4px 0; color: #666;">Programme</td>
                    <td style="padding: 4px 0;"><strong>{{ $submission->programme }}</strong></td>
                </tr>
                <tr>
                    <td style="padding: 4px 8px 4px 0; color: #666;">Department</td>
                    <td style="padding: 4px 0;"><strong>{{ $submission->department }}</strong></td>
                </tr>
                @if($submission->current_organization && strtolower($submission->current_organization) !== 'na')
                <tr>
                    <td style="padding: 4px 8px 4px 0; color: #666;">Organisation</td>
                    <td style="padding: 4px 0;"><strong>{{ $submission->current_organization }}</strong></td>
                </tr>
                @endif
                @if($submission->current_designation && strtolower($submission->current_designation) !== 'na')
                <tr>
                    <td style="padding: 4px 8px 4px 0; color: #666;">Designation</td>
                    <td style="padding: 4px 0;"><strong>{{ $submission->current_designation }}</strong></td>
                </tr>
                @endif
                @if($submission->city || $submission->country)
                <tr>
                    <td style="padding: 4px 8px 4px 0; color: #666;">Location</td>
                    <td style="padding: 4px 0;"><strong>{{ implode(', ', array_filter([$submission->city, $submission->country])) }}</strong></td>
                </tr>
                @endif
                <tr>
                    <td style="padding: 4px 8px 4px 0; color: #666;">Willing to Mentor</td>
                    <td style="padding: 4px 0;"><strong>{{ $submission->willing_to_mentor ? 'Yes' : 'No' }}</strong></td>
                </tr>
                <tr>
                    <td style="padding: 4px 8px 4px 0; color: #666;">Willing to Refer</td>
                    <td style="padding: 4px 0;"><strong>{{ $submission->willing_to_refer ? 'Yes' : 'No' }}</strong></td>
                </tr>
            </table>
        </div>

        <p style="font-size: 14px; color: #555;">If you have any questions or need to update your information, please contact us at the CDC office.</p>

        <p style="margin-top: 24px;">Warm regards,<br>
        <strong>IIT (ISM) CDC Team</strong><br>
        <span style="color: #666; font-size: 13px;">Career Development Cell, IIT (ISM) Dhanbad</span></p>
    </div>

    <p style="text-align: center; font-size: 11px; color: #999; margin-top: 16px;">
        This is an automated confirmation email. Please do not reply directly to this message.
    </p>

</body>
</html>
