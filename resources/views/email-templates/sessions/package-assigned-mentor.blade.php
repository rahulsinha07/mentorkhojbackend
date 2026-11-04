@include('email-templates.form._header')

<h1 style="margin:0 0 8px;font-size:26px;font-weight:800;color:#0f172a;">New session pack assigned</h1>
<p style="margin:0 0 20px;font-size:15px;line-height:1.65;color:#475569;">
    Hi {{ $mentor_first_name }}, <strong>{{ $mentee_first_name }}</strong> has been assigned a pack of
    <strong>{{ $sessions_total }}</strong> session(s) with you on MentorKhoj.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;padding:18px 20px;text-align:center;">
            <a href="{{ $mentor_dashboard_link }}" style="display:inline-block;background:linear-gradient(135deg,#006161,#0d9488);color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;padding:12px 24px;border-radius:999px;">Open mentor dashboard</a>
        </td>
    </tr>
</table>

@include('email-templates.form._footer')
