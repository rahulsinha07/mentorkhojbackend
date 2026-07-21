@include('email-templates.form._header')

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
        <td style="padding-bottom:20px;">
            <span style="display:inline-block;background-color:#ecfdf5;color:#047857;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;padding:6px 14px;border-radius:999px;border:1px solid #a7f3d0;">✓ Session confirmed</span>
        </td>
    </tr>
</table>

<h1 style="margin:0 0 8px;font-size:26px;font-weight:800;color:#0f172a;letter-spacing:-0.02em;line-height:1.2;">Your session is confirmed!</h1>
<p style="margin:0 0 20px;font-size:15px;line-height:1.65;color:#475569;">
    Hi {{ $mentee_first_name }}, great news — <strong>{{ $mentor_first_name }}</strong> has confirmed your mentorship session on MentorKhoj. You're all set!
</p>

@include('email-templates.form._mentor-session-details')

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;padding:18px 20px;">
            <p style="margin:0 0 8px;font-size:14px;font-weight:700;color:#047857;">Session access</p>
            <p style="margin:0 0 12px;font-size:14px;line-height:1.65;color:#334155;">
                Use the button below to open your session on MentorKhoj when it's time to join.
            </p>
            <a href="{{ $session_access_link }}" style="display:inline-block;background:linear-gradient(135deg,#006161,#0d9488);color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;padding:12px 24px;border-radius:999px;">Open session on MentorKhoj</a>
        </td>
    </tr>
</table>

<h3 style="margin:0 0 12px;font-size:15px;font-weight:700;color:#006161;text-transform:uppercase;letter-spacing:0.06em;">Get ready to make it count</h3>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="padding:6px 0;font-size:14px;line-height:1.55;color:#475569;">
            <span style="color:#10b981;font-weight:bold;margin-right:8px;">✓</span>Join <strong>5 minutes before</strong> {{ $session_time }}
        </td>
    </tr>
    <tr>
        <td style="padding:6px 0;font-size:14px;line-height:1.55;color:#475569;">
            <span style="color:#10b981;font-weight:bold;margin-right:8px;">✓</span>Write down your top questions and what you want to achieve
        </td>
    </tr>
    <tr>
        <td style="padding:6px 0;font-size:14px;line-height:1.55;color:#475569;">
            <span style="color:#10b981;font-weight:bold;margin-right:8px;">✓</span>Manage everything through MentorKhoj — messages, updates, and support
        </td>
    </tr>
</table>

<p style="margin:0 0 8px;font-size:14px;line-height:1.65;color:#475569;">
    Need help before your session? Email
    <a href="mailto:{{ $brand['admin_email'] ?? $support_email }}" style="color:#006161;text-decoration:none;font-weight:600;">{{ $brand['admin_email'] ?? $support_email }}</a>.
</p>
<p style="margin:0;font-size:14px;line-height:1.65;color:#475569;">
    You've got this — we're excited for you! 🌟
</p>

@include('email-templates.form._footer')
