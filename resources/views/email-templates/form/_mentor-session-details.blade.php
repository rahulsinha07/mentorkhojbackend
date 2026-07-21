<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:24px;">
            <p style="margin:0 0 4px;font-size:28px;line-height:1;">🎯</p>
            <h2 style="margin:0 0 6px;font-size:20px;font-weight:800;color:#0f172a;">{{ $session_title }}</h2>
            @if(!empty($for_mentor))
                <p style="margin:0 0 16px;font-size:14px;line-height:1.65;color:#64748b;">Requested by {{ $mentee_first_name }}</p>
            @else
                <p style="margin:0 0 16px;font-size:14px;line-height:1.65;color:#64748b;">with {{ $mentor_first_name }}</p>
            @endif
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td style="padding:8px 0;border-top:1px solid #e2e8f0;font-size:14px;color:#334155;"><strong style="color:#0f172a;">Session date</strong><br>{{ $session_date }}</td>
                </tr>
                <tr>
                    <td style="padding:8px 0;border-top:1px solid #e2e8f0;font-size:14px;color:#334155;"><strong style="color:#0f172a;">Session time</strong><br>{{ $session_time }}</td>
                </tr>
                <tr>
                    <td style="padding:8px 0;border-top:1px solid #e2e8f0;font-size:14px;color:#334155;"><strong style="color:#0f172a;">Time zone</strong><br>{{ $time_zone }}</td>
                </tr>
                <tr>
                    <td style="padding:8px 0;border-top:1px solid #e2e8f0;font-size:14px;color:#334155;"><strong style="color:#0f172a;">Duration</strong><br>{{ $session_duration }}</td>
                </tr>
                <tr>
                    <td style="padding:8px 0;border-top:1px solid #e2e8f0;font-size:14px;color:#334155;"><strong style="color:#0f172a;">Mode</strong><br>{{ $session_mode }}</td>
                </tr>
                <tr>
                    <td style="padding:8px 0;border-top:1px solid #e2e8f0;font-size:14px;color:#334155;"><strong style="color:#0f172a;">Amount paid</strong><br>{{ $amount_paid }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background:linear-gradient(135deg,#f0fdfa 0%,#ecfeff 100%);border:1px solid #99f6e4;border-radius:12px;padding:20px;text-align:center;">
            <p style="margin:0 0 4px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:#0d9488;">Booking ID</p>
            <p style="margin:0;font-size:28px;font-weight:800;color:#006161;letter-spacing:-0.02em;">{{ $booking_id }}</p>
            <p style="margin:8px 0 0;font-size:12px;color:#64748b;">Booked on {{ $booking_date }}</p>
        </td>
    </tr>
</table>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:18px 20px;">
            <p style="margin:0;font-size:14px;line-height:1.65;color:#9a3412;">
                <strong style="color:#7c2d12;">Privacy &amp; communication:</strong>
                For your safety, please keep all session communication, rescheduling, and support inside MentorKhoj.
                Do not share personal contact details or attempt to connect outside the platform.
            </p>
        </td>
    </tr>
</table>
