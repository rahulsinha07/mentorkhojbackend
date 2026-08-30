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
              <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;">Hi {{ $booking->name }},</h1>
              <p style="margin:0 0 20px;font-size:16px;line-height:1.6;color:#334155;">
                A mentor is ready for your free 1:1 session.
              </p>
              <p style="margin:0 0 24px;font-size:16px;line-height:1.6;color:#334155;">
                @if($hasAccount)
                  Sign in to your student profile to see them.
                @else
                  Create your student profile to see them.
                @endif
              </p>
              <p style="margin:0 0 28px;">
                <a href="{{ $ctaUrl }}" style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:12px 22px;border-radius:999px;">
                  @if($hasAccount)
                    Open student profile
                  @else
                    Create student profile
                  @endif
                </a>
              </p>
              <p style="margin:0;font-size:13px;color:#64748b;">— MentorKhoj Team</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
