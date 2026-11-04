<!DOCTYPE html>
<html>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#0f172a;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f4f5;padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="520" cellspacing="0" cellpadding="0" style="background:#ffffff;border-radius:16px;padding:32px 28px;">
          <tr>
            <td>
              <p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#4f46e5;">MentorKhoj</p>
              <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;">Hi {{ $studentName }},</h1>
              <p style="margin:0 0 24px;font-size:16px;line-height:1.6;color:#334155;">
                Your study plan is ready on MentorKhoj. Log in to check your plan.
              </p>
              <p style="margin:0 0 28px;">
                <a href="{{ $link }}" style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:12px 22px;border-radius:999px;">
                  Open PrepBoard
                </a>
              </p>
              <p style="margin:0 0 8px;font-size:13px;color:#64748b;">Or paste this link: {{ $link }}</p>
              <p style="margin:0;font-size:13px;color:#64748b;">— MentorKhoj Team</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
