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
</body>
</html>
