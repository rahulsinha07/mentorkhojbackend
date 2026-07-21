<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Reset your MentorKhoj password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style type="text/css">
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f1f5f9;
            margin: 0;
            padding: 24px 16px;
            color: #0f172a;
        }
        .card {
            max-width: 520px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            padding: 32px 28px;
            box-shadow: 0 4px 24px rgba(15, 23, 42, 0.08);
        }
        h1 {
            font-size: 22px;
            margin: 0 0 8px;
        }
        p {
            line-height: 1.6;
            margin: 0 0 16px;
            color: #334155;
        }
        .btn {
            display: inline-block;
            background: #2563eb;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 600;
            margin: 8px 0 20px;
        }
        .muted {
            font-size: 13px;
            color: #64748b;
        }
        .fallback {
            word-break: break-all;
            font-size: 12px;
            color: #475569;
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
        }
    </style>
</head>
<body>
@php($business_name = \App\Model\BusinessSetting::where(['key' => 'restaurant_name'])->first()->value ?? 'MentorKhoj')
@php($expiry = $expiryMinutes ?? 30)

<div class="card">
    <h1>Reset your password</h1>
    <p>Hi {{ $name }},</p>
    <p>We received a request to reset your {{ $business_name }} account password. Click the button below to choose a new password.</p>

    @if(!empty($link))
        <p style="text-align:center;">
            <a href="{{ $link }}" class="btn">Reset password</a>
        </p>
        <p class="muted">This link expires in {{ $expiry }} minutes and can only be used once.</p>
        <p class="muted">If the button does not work, copy and paste this URL into your browser:</p>
        <p class="fallback">{{ $link }}</p>
    @else
        <p>Your verification code is:</p>
        <p style="font-size: 28px; letter-spacing: 4px; font-weight: 700; text-align: center;">{{ $token }}</p>
        <p class="muted">This code expires in {{ $expiry }} minutes.</p>
    @endif

    <p class="muted">If you did not request a password reset, you can safely ignore this email.</p>
    <p class="muted">— {{ $business_name }} Team</p>
</div>
</body>
</html>
