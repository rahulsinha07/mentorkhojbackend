@include('email-templates.form._header')

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
        <td style="padding-bottom:20px;">
            <span style="display:inline-block;background-color:#ecfdf5;color:#047857;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;padding:6px 14px;border-radius:999px;border:1px solid #a7f3d0;">✓ Session booked</span>
        </td>
    </tr>
</table>

<h1 style="margin:0 0 8px;font-size:26px;font-weight:800;color:#0f172a;letter-spacing:-0.02em;line-height:1.2;">Your MentorKhoj session is booked!</h1>
<p style="margin:0 0 20px;font-size:15px;line-height:1.65;color:#475569;">
    Hi {{ $mentee_first_name }}, great choice — your mentorship session with <strong>{{ $mentor_first_name }}</strong> has been booked successfully on MentorKhoj.
</p>

@include('email-templates.form._mentor-session-details')

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:18px 20px;">
            <p style="margin:0 0 8px;font-size:14px;font-weight:700;color:#1e40af;">What happens next?</p>
            <p style="margin:0;font-size:14px;line-height:1.65;color:#334155;">
                {{ $mentor_first_name }} will review and confirm your booking shortly.
                You will receive <strong>another confirmation email</strong> once your mentor confirms the session.
            </p>
        </td>
    </tr>
</table>

<h3 style="margin:0 0 12px;font-size:15px;font-weight:700;color:#006161;text-transform:uppercase;letter-spacing:0.06em;">Before your session</h3>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="padding:6px 0;font-size:14px;line-height:1.55;color:#475569;">
            <span style="color:#10b981;font-weight:bold;margin-right:8px;">✓</span>Join <strong>5 minutes before</strong> the scheduled time
        </td>
    </tr>
    <tr>
        <td style="padding:6px 0;font-size:14px;line-height:1.55;color:#475569;">
            <span style="color:#10b981;font-weight:bold;margin-right:8px;">✓</span>Prepare your questions and goals in advance
        </td>
    </tr>
    <tr>
        <td style="padding:6px 0;font-size:14px;line-height:1.55;color:#475569;">
            <span style="color:#10b981;font-weight:bold;margin-right:8px;">✓</span>Use MentorKhoj for all communication and session updates
        </td>
    </tr>
</table>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td align="center">
            <a href="{{ $session_access_link }}" style="display:inline-block;background:linear-gradient(135deg,#006161,#0d9488);color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;padding:14px 32px;border-radius:999px;box-shadow:0 4px 14px rgba(0,97,97,0.35);">View my bookings</a>
        </td>
    </tr>
</table>

<p style="margin:0 0 8px;font-size:14px;line-height:1.65;color:#475569;">
    Need help? Contact MentorKhoj Support at
    <a href="mailto:{{ $brand['admin_email'] ?? $support_email }}" style="color:#006161;text-decoration:none;font-weight:600;">{{ $brand['admin_email'] ?? $support_email }}</a>.
</p>
<p style="margin:0;font-size:14px;line-height:1.65;color:#475569;">
    We're cheering you on — this session could be the start of something great. 🚀
</p>

@include('email-templates.form._footer')
