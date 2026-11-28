@include('email-templates.form._header')

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
        <td style="padding-bottom:20px;">
            <span style="display:inline-block;background-color:#fff7ed;color:#9a3412;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;padding:6px 14px;border-radius:999px;border:1px solid #fed7aa;">⏳ Profile under review</span>
        </td>
    </tr>
</table>

<h1 style="margin:0 0 8px;font-size:26px;font-weight:800;color:#0f172a;letter-spacing:-0.02em;line-height:1.2;">Thanks for applying, {{ $mentor_first_name }}!</h1>
<p style="margin:0 0 24px;font-size:15px;line-height:1.65;color:#475569;">
    We've received your mentor profile for MentorKhoj. Before it goes live, our team reviews every
    new mentor to keep the experience trustworthy for students. Please wait for approval — someone
    from our team will reach out to you with the details.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:24px;">
            <p style="margin:0 0 4px;font-size:28px;line-height:1;">🚀</p>
            <h2 style="margin:0 0 6px;font-size:20px;font-weight:800;color:#0f172a;">{{ $display_name }}</h2>
            @if(!empty($headline))
                <p style="margin:0 0 16px;font-size:14px;font-weight:600;color:#006161;">{{ $headline }}</p>
            @endif
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td style="padding:8px 0;border-top:1px solid #e2e8f0;font-size:14px;color:#334155;"><strong style="color:#0f172a;">Mentor page (reserved)</strong><br>@{{ $username }}</td>
                </tr>
                <tr>
                    <td style="padding:8px 0;border-top:1px solid #e2e8f0;font-size:14px;color:#334155;"><strong style="color:#0f172a;">Applied on</strong><br>{{ $created_at }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<h3 style="margin:0 0 12px;font-size:15px;font-weight:700;color:#006161;text-transform:uppercase;letter-spacing:0.06em;">What happens next</h3>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="padding:6px 0;font-size:14px;line-height:1.55;color:#475569;">
            <span style="color:#10b981;font-weight:bold;margin-right:8px;">✓</span>Our team reviews your profile and details
        </td>
    </tr>
    <tr>
        <td style="padding:6px 0;font-size:14px;line-height:1.55;color:#475569;">
            <span style="color:#10b981;font-weight:bold;margin-right:8px;">✓</span>Someone from MentorKhoj reaches out if we need anything else
        </td>
    </tr>
    <tr>
        <td style="padding:6px 0;font-size:14px;line-height:1.55;color:#475569;">
            <span style="color:#10b981;font-weight:bold;margin-right:8px;">✓</span>Once approved, your page goes live and we'll email you the link
        </td>
    </tr>
</table>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background:linear-gradient(135deg,#f0fdfa 0%,#ecfeff 100%);border:1px solid #99f6e4;border-radius:12px;padding:20px;">
            <p style="margin:0 0 4px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:#0d9488;">Have questions in the meantime?</p>
            <p style="margin:0;font-size:14px;line-height:1.65;color:#334155;">
                Call <a href="tel:{{ $support_call_tel }}" style="color:#006161;text-decoration:none;font-weight:700;">{{ $support_call_display }}</a>
                or WhatsApp <a href="{{ $support_whatsapp_url }}" style="color:#006161;text-decoration:none;font-weight:700;">{{ $support_whatsapp_display }}</a>
            </p>
        </td>
    </tr>
</table>

<p style="margin:0 0 8px;font-size:14px;line-height:1.65;color:#475569;">
    You can keep setting up your services and pricing in your dashboard while you wait — just note
    your page stays private until we approve it.
</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:16px 0 24px;">
    <tr>
        <td align="center">
            <a href="{{ $dashboard_url }}" style="display:inline-block;background:linear-gradient(135deg,#006161,#0d9488);color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;padding:14px 32px;border-radius:999px;box-shadow:0 4px 14px rgba(0,97,97,0.35);">Open mentor dashboard</a>
        </td>
    </tr>
</table>

<p style="margin:0;font-size:14px;line-height:1.65;color:#475569;">
    Thanks for your patience — we're looking forward to having you on MentorKhoj. 🌟
</p>

@include('email-templates.form._footer')
