@include('email-templates.form._header')

{{-- Badge --}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
        <td style="padding-bottom:20px;">
            <span style="display:inline-block;background-color:#ecfdf5;color:#047857;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;padding:6px 14px;border-radius:999px;border:1px solid #a7f3d0;">✓ Seminar confirmed</span>
        </td>
    </tr>
</table>

<h1 style="margin:0 0 8px;font-size:26px;font-weight:800;color:#0f172a;letter-spacing:-0.02em;line-height:1.2;">You're registered!</h1>
<p style="margin:0 0 24px;font-size:15px;line-height:1.65;color:#475569;">Hi {{ $name }}, thank you for registering for our <strong>free seminar</strong>. Your spot is saved — details below.</p>

{{-- Booking ref --}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background:linear-gradient(135deg,#f0fdfa 0%,#ecfeff 100%);border:1px solid #99f6e4;border-radius:12px;padding:20px;text-align:center;">
            <p style="margin:0 0 4px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:#0d9488;">Booking reference</p>
            <p style="margin:0;font-size:28px;font-weight:800;color:#006161;letter-spacing:-0.02em;">#{{ $registration['id'] }}</p>
        </td>
    </tr>
</table>

{{-- Seminar card --}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:24px;">
            <p style="margin:0 0 4px;font-size:28px;line-height:1;">{{ $seminar['emoji'] ?? '📅' }}</p>
            <h2 style="margin:0 0 6px;font-size:20px;font-weight:800;color:#0f172a;">{{ $seminar['title'] }}</h2>
            @if(!empty($seminar['tagline']))
                <p style="margin:0 0 12px;font-size:14px;font-weight:600;color:#006161;">{{ $seminar['tagline'] }}</p>
            @endif
            @if(!empty($seminar['blurb']))
                <p style="margin:0 0 16px;font-size:14px;line-height:1.65;color:#64748b;">{{ $seminar['blurb'] }}</p>
            @endif
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td style="padding:8px 0;border-top:1px solid #e2e8f0;font-size:14px;color:#334155;"><strong style="color:#0f172a;">Schedule</strong><br>{{ $seminar['date'] ?? 'To be announced' }}</td>
                </tr>
                <tr>
                    <td style="padding:8px 0;border-top:1px solid #e2e8f0;font-size:14px;color:#334155;"><strong style="color:#0f172a;">Duration</strong><br>{{ $seminar['duration'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td style="padding:8px 0;border-top:1px solid #e2e8f0;font-size:14px;color:#334155;"><strong style="color:#0f172a;">Mode</strong><br>{{ $seminar['mode'] ?? 'Online' }}</td>
                </tr>
                <tr>
                    <td style="padding:8px 0;border-top:1px solid #e2e8f0;font-size:14px;color:#334155;"><strong style="color:#0f172a;">Audience</strong><br>{{ $seminar['audience'] ?? 'All learners' }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@if(!empty($seminar['highlights']))
<h3 style="margin:0 0 12px;font-size:15px;font-weight:700;color:#006161;text-transform:uppercase;letter-spacing:0.06em;">What you'll learn</h3>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    @foreach($seminar['highlights'] as $item)
    <tr>
        <td style="padding:6px 0;font-size:14px;line-height:1.5;color:#475569;">
            <span style="color:#10b981;font-weight:bold;margin-right:8px;">✓</span>{{ $item }}
        </td>
    </tr>
    @endforeach
</table>
@endif

<h3 style="margin:0 0 12px;font-size:15px;font-weight:700;color:#006161;text-transform:uppercase;letter-spacing:0.06em;">Your details</h3>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:20px;">
            <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong style="color:#0f172a;">Name:</strong> {{ $registration['name'] }}</p>
            <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong style="color:#0f172a;">Email:</strong> {{ $registration['email'] }}</p>
            <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong style="color:#0f172a;">Phone:</strong> {{ $registration['phone'] }}</p>
            <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong style="color:#0f172a;">College:</strong> {{ $registration['college'] }}</p>
            @if(!empty($registration['details']))
                <p style="margin:0;font-size:14px;color:#334155;"><strong style="color:#0f172a;">Your notes:</strong> {{ $registration['details'] }}</p>
            @endif
        </td>
    </tr>
</table>

<p style="margin:0 0 24px;font-size:14px;line-height:1.65;color:#475569;">We'll email you the <strong>joining link</strong> before the session starts. Add this email to your contacts so you don't miss it.</p>

@if(!empty($seminar['page_url']))
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
        <td align="center">
            <a href="{{ $seminar['page_url'] }}" style="display:inline-block;background:linear-gradient(135deg,#006161,#0d9488);color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;padding:14px 32px;border-radius:999px;box-shadow:0 4px 14px rgba(0,97,97,0.35);">View seminar page</a>
        </td>
    </tr>
</table>
@endif

<p style="margin:24px 0 0;font-size:12px;color:#94a3b8;text-align:center;">Submitted on {{ $registration['submitted_at'] ?? now()->format('d M Y, h:i A') }}</p>

@include('email-templates.form._footer')
