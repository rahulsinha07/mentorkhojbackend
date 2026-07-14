@include('email-templates.form._header')

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
        <td style="padding-bottom:16px;">
            <span style="display:inline-block;background-color:#fef3c7;color:#b45309;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;padding:6px 14px;border-radius:999px;">Admin notification</span>
        </td>
    </tr>
</table>

<h1 style="margin:0 0 8px;font-size:22px;font-weight:800;color:#0f172a;">New seminar registration</h1>
<p style="margin:0 0 20px;font-size:14px;color:#64748b;">Reference <strong style="color:#006161;">#{{ $registration['id'] }}</strong> · {{ $registration['submitted_at'] }}</p>

<h2 style="margin:0 0 12px;font-size:14px;font-weight:700;color:#006161;text-transform:uppercase;letter-spacing:0.06em;">Student details</h2>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:20px;">
            <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong>Name:</strong> {{ $registration['name'] }}</p>
            <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong>Email:</strong> <a href="mailto:{{ $registration['email'] }}" style="color:#006161;">{{ $registration['email'] }}</a></p>
            <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong>Phone:</strong> <a href="tel:{{ $registration['phone'] }}" style="color:#006161;">{{ $registration['phone'] }}</a></p>
            <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong>College:</strong> {{ $registration['college'] }}</p>
            @if(!empty($registration['details']))
                <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong>Notes:</strong> {{ $registration['details'] }}</p>
            @endif
            <p style="margin:0;font-size:14px;color:#334155;"><strong>Status:</strong> {{ $registration['status'] }}</p>
        </td>
    </tr>
</table>

<h2 style="margin:0 0 12px;font-size:14px;font-weight:700;color:#006161;text-transform:uppercase;letter-spacing:0.06em;">Seminar — {{ $seminar['title'] }}</h2>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
        <td style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:20px;">
            <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong>Tagline:</strong> {{ $seminar['tagline'] ?? '—' }}</p>
            <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong>Schedule:</strong> {{ $seminar['date'] ?? '—' }}</p>
            <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong>Duration:</strong> {{ $seminar['duration'] ?? '—' }}</p>
            <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong>Mode:</strong> {{ $seminar['mode'] ?? '—' }}</p>
            <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong>Audience:</strong> {{ $seminar['audience'] ?? '—' }}</p>
            @if(!empty($seminar['page_url']))
                <p style="margin:12px 0 0;"><a href="{{ $seminar['page_url'] }}" style="color:#006161;font-weight:600;">View public page →</a></p>
            @endif
        </td>
    </tr>
</table>

@include('email-templates.form._footer')
