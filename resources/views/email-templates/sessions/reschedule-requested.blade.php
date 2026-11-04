@include('email-templates.form._header')

<h1 style="margin:0 0 8px;font-size:26px;font-weight:800;color:#0f172a;">Reschedule request</h1>
<p style="margin:0 0 20px;font-size:15px;line-height:1.65;color:#475569;">
    Hi {{ $mentor_first_name }}, {{ $mentee_first_name }} has requested to reschedule session {{ $booking_id }}.
</p>

@include('email-templates.form._mentor-session-details')

<p style="margin:0 0 8px;font-size:14px;line-height:1.65;color:#475569;">
    Please review and approve or reject the request from your mentor dashboard.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;padding:18px 20px;text-align:center;">
            <a href="{{ $mentor_dashboard_link }}" style="display:inline-block;background:linear-gradient(135deg,#006161,#0d9488);color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;padding:12px 24px;border-radius:999px;">Review request</a>
        </td>
    </tr>
</table>

@include('email-templates.form._footer')
