@include('email-templates.form._header')

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
        <td style="padding-bottom:20px;">
            <span style="display:inline-block;background-color:#ecfdf5;color:#047857;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;padding:6px 14px;border-radius:999px;border:1px solid #a7f3d0;">✓ Mentor profile created</span>
        </td>
    </tr>
</table>

<h1 style="margin:0 0 8px;font-size:26px;font-weight:800;color:#0f172a;letter-spacing:-0.02em;line-height:1.2;">Welcome to MentorKhoj!</h1>
<p style="margin:0 0 24px;font-size:15px;line-height:1.65;color:#475569;">
    Hi {{ $mentor_first_name }}, your mentor profile has been created successfully. You’re now part of a community helping learners grow through meaningful mentorship.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background:linear-gradient(135deg,#f0fdfa 0%,#ecfeff 100%);border:1px solid #99f6e4;border-radius:12px;padding:20px;text-align:center;">
            <p style="margin:0 0 4px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:#0d9488;">Your mentor page</p>
            <p style="margin:0;font-size:18px;font-weight:800;color:#006161;letter-spacing:-0.02em;">@{{ $username }}</p>
        </td>
    </tr>
</table>

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
                    <td style="padding:8px 0;border-top:1px solid #e2e8f0;font-size:14px;color:#334155;"><strong style="color:#0f172a;">Profile URL</strong><br>{{ $profile_url }}</td>
                </tr>
                <tr>
                    <td style="padding:8px 0;border-top:1px solid #e2e8f0;font-size:14px;color:#334155;"><strong style="color:#0f172a;">Created on</strong><br>{{ $created_at }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<h3 style="margin:0 0 12px;font-size:15px;font-weight:700;color:#006161;text-transform:uppercase;letter-spacing:0.06em;">Your next steps</h3>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="padding:6px 0;font-size:14px;line-height:1.55;color:#475569;">
            <span style="color:#10b981;font-weight:bold;margin-right:8px;">✓</span>Add your mentorship services and pricing
        </td>
    </tr>
    <tr>
        <td style="padding:6px 0;font-size:14px;line-height:1.55;color:#475569;">
            <span style="color:#10b981;font-weight:bold;margin-right:8px;">✓</span>Complete your profile and publish your page
        </td>
    </tr>
    <tr>
        <td style="padding:6px 0;font-size:14px;line-height:1.55;color:#475569;">
            <span style="color:#10b981;font-weight:bold;margin-right:8px;">✓</span>Share your MentorKhoj link to start getting bookings
        </td>
    </tr>
    <tr>
        <td style="padding:6px 0;font-size:14px;line-height:1.55;color:#475569;">
            <span style="color:#10b981;font-weight:bold;margin-right:8px;">✓</span>Manage sessions, messages, and bookings inside MentorKhoj
        </td>
    </tr>
</table>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:18px 20px;">
            <p style="margin:0;font-size:14px;line-height:1.65;color:#9a3412;">
                <strong style="color:#7c2d12;">Important:</strong>
                Keep all mentee communication, booking updates, and session management inside MentorKhoj.
                Do not share personal contact details outside the platform.
            </p>
        </td>
    </tr>
</table>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td align="center">
            <a href="{{ $dashboard_url }}" style="display:inline-block;background:linear-gradient(135deg,#006161,#0d9488);color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;padding:14px 32px;border-radius:999px;box-shadow:0 4px 14px rgba(0,97,97,0.35);margin-right:8px;">Open mentor dashboard</a>
        </td>
    </tr>
</table>

<p style="margin:0 0 8px;font-size:14px;line-height:1.65;color:#475569;">
    Need help getting started? Contact us at
    <a href="mailto:{{ $brand['admin_email'] ?? $support_email }}" style="color:#006161;text-decoration:none;font-weight:600;">{{ $brand['admin_email'] ?? $support_email }}</a>.
</p>
<p style="margin:0;font-size:14px;line-height:1.65;color:#475569;">
    We’re excited to have you on MentorKhoj — thank you for sharing your expertise. 🌟
</p>

@include('email-templates.form._footer')
