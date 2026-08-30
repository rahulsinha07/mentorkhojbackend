@include('email-templates.form._header')

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
        <td style="padding-bottom:20px;">
            <span style="display:inline-block;background-color:#fff7ed;color:#c2410c;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;padding:6px 14px;border-radius:999px;border:1px solid #fed7aa;">Payment pending</span>
        </td>
    </tr>
</table>

<h1 style="margin:0 0 8px;font-size:26px;font-weight:800;color:#0f172a;letter-spacing:-0.02em;line-height:1.2;">Complete payment for your session</h1>
<p style="margin:0 0 20px;font-size:15px;line-height:1.65;color:#475569;">
    Hi {{ $mentee_first_name }}, your mentorship session with <strong>{{ $mentor_first_name }}</strong> is reserved on MentorKhoj.
    Please complete payment of <strong>{{ $amount_due }}</strong> to confirm your booking.
</p>

@include('email-templates.form._mentor-session-details')

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td align="center">
            <a href="{{ $payment_link }}" style="display:inline-block;background:linear-gradient(135deg,#006161,#0d9488);color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;padding:14px 32px;border-radius:999px;box-shadow:0 4px 14px rgba(0,97,97,0.35);">Pay now &amp; confirm session</a>
        </td>
    </tr>
</table>

<p style="margin:0 0 8px;font-size:14px;line-height:1.65;color:#475569;">
    If the button does not work, copy and paste this link into your browser:<br>
    <a href="{{ $payment_link }}" style="color:#006161;word-break:break-all;">{{ $payment_link }}</a>
</p>

<p style="margin:16px 0 8px;font-size:14px;line-height:1.65;color:#475569;">
    Need help? Contact MentorKhoj Support at
    <a href="mailto:{{ $brand['admin_email'] ?? $support_email }}" style="color:#006161;text-decoration:none;font-weight:600;">{{ $brand['admin_email'] ?? $support_email }}</a>.
</p>

@include('email-templates.form._footer')
