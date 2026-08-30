@include('email-templates.form._header')

<h1 style="margin:0 0 8px;font-size:26px;font-weight:800;color:#0f172a;letter-spacing:-0.02em;line-height:1.2;">New message from {{ $student_first_name }}</h1>
<p style="margin:0 0 20px;font-size:15px;line-height:1.65;color:#475569;">
    Hi {{ $mentor_first_name }}, {{ $student_first_name }} sent you a message on MentorKhoj.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:18px 20px;">
            <p style="margin:0;font-size:14px;line-height:1.65;color:#334155;white-space:pre-wrap;">{{ $body }}</p>
        </td>
    </tr>
</table>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td align="center">
            <a href="{{ $bookings_link }}" style="display:inline-block;background:linear-gradient(135deg,#006161,#0d9488);color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;padding:14px 32px;border-radius:999px;">Reply on MentorKhoj</a>
        </td>
    </tr>
</table>

<p style="margin:0;font-size:13px;line-height:1.65;color:#64748b;">
    Keep all communication on MentorKhoj. Do not share email, phone, or social profiles.
</p>

@include('email-templates.form._footer')
