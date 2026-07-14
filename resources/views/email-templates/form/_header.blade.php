@php
    $brand = $brand ?? [];
    $logoUrl = $brand['logo_url'] ?? 'https://www.mentorkhoj.com/logo-512.png';
    $siteName = $brand['site_name'] ?? 'MentorKhoj';
    $siteUrl = $brand['site_url'] ?? 'https://www.mentorkhoj.com';
    $tagline = $brand['tagline'] ?? 'Find mentors. Book sessions. Grow faster.';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $siteName }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;-webkit-font-smoothing:antialiased;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f1f5f9;">
    <tr>
        <td align="center" style="padding:32px 16px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;">
                {{-- Header --}}
                <tr>
                    <td style="background:linear-gradient(135deg,#006161 0%,#0d9488 50%,#4f46e5 100%);border-radius:16px 16px 0 0;padding:28px 32px;text-align:center;">
                        <a href="{{ $siteUrl }}" style="text-decoration:none;display:inline-block;">
                            <img src="{{ $logoUrl }}" alt="{{ $siteName }}" width="72" height="72" style="display:block;margin:0 auto 12px;border-radius:16px;border:3px solid rgba(255,255,255,0.25);">
                        </a>
                        <p style="margin:0;font-size:22px;font-weight:800;color:#ffffff;letter-spacing:-0.02em;">{{ $siteName }}</p>
                        <p style="margin:6px 0 0;font-size:13px;color:rgba(255,255,255,0.85);">{{ $tagline }}</p>
                    </td>
                </tr>
                {{-- Body --}}
                <tr>
                    <td style="background-color:#ffffff;padding:32px;border-left:1px solid #e2e8f0;border-right:1px solid #e2e8f0;">
