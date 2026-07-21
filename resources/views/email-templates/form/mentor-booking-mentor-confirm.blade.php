@include('email-templates.form._header')

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
        <td style="padding-bottom:20px;">
            <span style="display:inline-block;background-color:#fef3c7;color:#92400e;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;padding:6px 14px;border-radius:999px;border:1px solid #fcd34d;">Action required</span>
        </td>
    </tr>
</table>

<h1 style="margin:0 0 8px;font-size:26px;font-weight:800;color:#0f172a;letter-spacing:-0.02em;line-height:1.2;">New session booking</h1>
<p style="margin:0 0 20px;font-size:15px;line-height:1.65;color:#475569;">
    Hi {{ $mentor_first_name }}, <strong>{{ $mentee_first_name }}</strong> has booked a mentorship session with you on MentorKhoj.
    Please review the details below and confirm the booking.
</p>

@include('email-templates.form._mentor-session-details')

@if(!empty($mentee_note))
<h3 style="margin:0 0 12px;font-size:15px;font-weight:700;color:#006161;text-transform:uppercase;letter-spacing:0.06em;">Note from {{ $mentee_first_name }}</h3>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:18px 20px;">
            <p style="margin:0;font-size:14px;line-height:1.65;color:#334155;">{{ $mentee_note }}</p>
        </td>
    </tr>
</table>
@endif

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:18px 20px;">
            <p style="margin:0 0 8px;font-size:14px;font-weight:700;color:#1e40af;">Your next step</p>
            <p style="margin:0;font-size:14px;line-height:1.65;color:#334155;">
                Confirm this booking on MentorKhoj so {{ $mentee_first_name }} receives a confirmation email and can prepare for the session.
            </p>
        </td>
    </tr>
</table>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td align="center">
            <a href="{{ $mentor_dashboard_link }}" style="display:inline-block;background:linear-gradient(135deg,#006161,#0d9488);color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;padding:14px 32px;border-radius:999px;box-shadow:0 4px 14px rgba(0,97,97,0.35);">Review &amp; confirm booking</a>
        </td>
    </tr>
</table>

<h3 style="margin:0 0 12px;font-size:15px;font-weight:700;color:#006161;text-transform:uppercase;letter-spacing:0.06em;">Important reminders</h3>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="padding:6px 0;font-size:14px;line-height:1.55;color:#475569;">
            <span style="color:#10b981;font-weight:bold;margin-right:8px;">✓</span>Keep all communication inside MentorKhoj
        </td>
    </tr>
    <tr>
        <td style="padding:6px 0;font-size:14px;line-height:1.55;color:#475569;">
            <span style="color:#10b981;font-weight:bold;margin-right:8px;">✓</span>Do not share personal email, phone, or social profiles
        </td>
    </tr>
    <tr>
        <td style="padding:6px 0;font-size:14px;line-height:1.55;color:#475569;">
            <span style="color:#10b981;font-weight:bold;margin-right:8px;">✓</span>Be ready to join 5 minutes before the scheduled time
        </td>
    </tr>
</table>

<p style="margin:0;font-size:14px;line-height:1.65;color:#475569;">
    Questions? Reach MentorKhoj Support at
    <a href="mailto:{{ $brand['admin_email'] ?? $support_email }}" style="color:#006161;text-decoration:none;font-weight:600;">{{ $brand['admin_email'] ?? $support_email }}</a>.
</p>

@include('email-templates.form._footer')
