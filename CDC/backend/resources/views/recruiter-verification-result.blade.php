<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recruiter Email Verification</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f6f8fb;
            color: #1f2937;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            width: 100%;
            max-width: 520px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 8px 28px rgba(15, 23, 42, 0.08);
        }
        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.3px;
            margin-bottom: 12px;
        }
        .badge-success {
            background: #dcfce7;
            color: #166534;
        }
        .badge-failed {
            background: #fee2e2;
            color: #991b1b;
        }
        h1 {
            margin: 0 0 10px;
            font-size: 24px;
        }
        p {
            margin: 0 0 18px;
            line-height: 1.5;
        }
        .button {
            display: inline-block;
            background: #0f766e;
            color: #ffffff;
            text-decoration: none;
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge {{ $status === 'success' ? 'badge-success' : 'badge-failed' }}">
            {{ $status === 'success' ? 'VERIFIED' : 'NOT VERIFIED' }}
        </span>
        <h1>{{ $status === 'success' ? 'Email Verified' : 'Verification Failed' }}</h1>
        <p>{{ $message }}</p>
        <p>Please go back to the registration page. It will now show the updated verification status.</p>
        <a class="button" href="{{ $returnUrl }}">Back to Registration Page</a>
    </div>
</body>
</html>
