@include('email-templates.form._header')

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
        <td style="padding-bottom:20px;">
            <span style="display:inline-block;background-color:#fffbeb;color:#b45309;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;padding:6px 14px;border-radius:999px;border:1px solid #fde68a;">★ Feedback requested</span>
        </td>
    </tr>
</table>

<h1 style="margin:0 0 8px;font-size:26px;font-weight:800;color:#0f172a;letter-spacing:-0.02em;line-height:1.2;">How was your session?</h1>
<p style="margin:0 0 20px;font-size:15px;line-height:1.65;color:#475569;">
    Hi {{ $mentee_first_name }}, <strong>{{ $mentor_first_name }}</strong> would really appreciate your feedback on
    {{ $session_title }}. It only takes a minute, and it helps other students find the right mentor too.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:18px 20px;">
            <p style="margin:0 0 12px;font-size:14px;line-height:1.65;color:#334155;">
                Leave a quick rating and review — {{ $mentor_first_name }} will see it on their MentorKhoj dashboard.
            </p>
            <a href="{{ $feedback_link }}" style="display:inline-block;background:linear-gradient(135deg,#006161,#0d9488);color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;padding:12px 24px;border-radius:999px;">Leave feedback on MentorKhoj</a>
        </td>
    </tr>
</table>

<p style="margin:0 0 8px;font-size:14px;line-height:1.65;color:#475569;">
    Need help? Email
    <a href="mailto:{{ $brand['admin_email'] ?? $support_email }}" style="color:#006161;text-decoration:none;font-weight:600;">{{ $brand['admin_email'] ?? $support_email }}</a>
    or WhatsApp MentorKhoj:
    <strong>{{ $support_phone_primary ?? '+91 73669 39888' }}</strong>.
    Do not exchange personal phone numbers or emails with your mentor — everything happens through MentorKhoj.
</p>
<p style="margin:0;font-size:14px;line-height:1.65;color:#475569;">
    Thanks for being part of MentorKhoj! 🌟
</p>

@include('email-templates.form._footer')
