@include('email-templates.form._header')

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
        <td style="padding-bottom:20px;">
            <span style="display:inline-block;background-color:#eef2ff;color:#4338ca;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;padding:6px 14px;border-radius:999px;border:1px solid #c7d2fe;">✓ Application received</span>
        </td>
    </tr>
</table>

<h1 style="margin:0 0 8px;font-size:26px;font-weight:800;color:#0f172a;letter-spacing:-0.02em;line-height:1.2;">We got your application!</h1>
<p style="margin:0 0 24px;font-size:15px;line-height:1.65;color:#475569;">Hi {{ $name }}, thank you for applying to intern at <strong>MentorKhoj</strong>. Our team will review your profile and get back to you soon.</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background:linear-gradient(135deg,#eef2ff 0%,#f0fdfa 100%);border:1px solid #c7d2fe;border-radius:12px;padding:20px;text-align:center;">
            <p style="margin:0 0 4px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:#4338ca;">Application reference</p>
            <p style="margin:0;font-size:28px;font-weight:800;color:#4f46e5;letter-spacing:-0.02em;">#{{ $application['id'] }}</p>
        </td>
    </tr>
</table>

<h2 style="margin:0 0 12px;font-size:15px;font-weight:700;color:#006161;text-transform:uppercase;letter-spacing:0.06em;">Role applied for</h2>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:24px;">
            <p style="margin:0 0 12px;font-size:18px;font-weight:800;color:#0f172a;">{{ $application['role'] }}</p>
            @if($internship)
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr><td style="padding:6px 0;font-size:14px;color:#334155;border-top:1px solid #e2e8f0;"><strong>Team:</strong> {{ $internship['team'] ?? '—' }}</td></tr>
                    <tr><td style="padding:6px 0;font-size:14px;color:#334155;border-top:1px solid #e2e8f0;"><strong>Location:</strong> {{ $internship['location'] ?? '—' }}</td></tr>
                    <tr><td style="padding:6px 0;font-size:14px;color:#334155;border-top:1px solid #e2e8f0;"><strong>Type:</strong> {{ $internship['type'] ?? '—' }}</td></tr>
                    <tr><td style="padding:6px 0;font-size:14px;color:#334155;border-top:1px solid #e2e8f0;"><strong>Duration:</strong> {{ $internship['duration'] ?? '—' }}</td></tr>
                    <tr><td style="padding:6px 0;font-size:14px;color:#334155;border-top:1px solid #e2e8f0;"><strong>Stipend:</strong> {{ $internship['stipend'] ?? '—' }}</td></tr>
                </table>
                @if(!empty($internship['blurb']))
                    <p style="margin:16px 0 0;font-size:14px;line-height:1.65;color:#64748b;">{{ $internship['blurb'] }}</p>
                @endif
                @if(!empty($internship['skills']))
                    <p style="margin:16px 0 8px;font-size:13px;font-weight:700;color:#0f172a;">Skills we're looking for</p>
                    @foreach($internship['skills'] as $skill)
                    <span style="display:inline-block;background:#ecfdf5;color:#047857;font-size:12px;font-weight:600;padding:4px 10px;border-radius:999px;margin:0 6px 6px 0;">{{ $skill }}</span>
                    @endforeach
                @endif
            @else
                <p style="margin:0;font-size:14px;color:#64748b;">Custom / other role selected.</p>
            @endif
        </td>
    </tr>
</table>

<h2 style="margin:0 0 12px;font-size:15px;font-weight:700;color:#006161;text-transform:uppercase;letter-spacing:0.06em;">Your submission</h2>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:20px;">
            <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong>Name:</strong> {{ $application['name'] }}</p>
            <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong>Email:</strong> {{ $application['email'] }}</p>
            <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong>Phone:</strong> {{ $application['phone'] }}</p>
            <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong>College / Company:</strong> {{ $application['org'] }}</p>
            @if(!empty($application['resume_url']))
                <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong>Resume / Portfolio:</strong> <a href="{{ $application['resume_url'] }}" style="color:#006161;">{{ $application['resume_url'] }}</a></p>
            @endif
            @if(!empty($application['message']))
                <p style="margin:0;font-size:14px;color:#334155;"><strong>Why you're a great fit:</strong> {{ $application['message'] }}</p>
            @endif
        </td>
    </tr>
</table>

<p style="margin:0 0 24px;font-size:14px;line-height:1.65;color:#475569;">We typically respond within <strong>5 business days</strong>. In the meantime, explore our mentors and free seminars on MentorKhoj.</p>

@if(!empty($brand['internships_url']))
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
        <td align="center">
            <a href="{{ $brand['internships_url'] }}" style="display:inline-block;background:linear-gradient(135deg,#4f46e5,#006161);color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;padding:14px 32px;border-radius:999px;box-shadow:0 4px 14px rgba(79,70,229,0.35);">Browse internships</a>
        </td>
    </tr>
</table>
@endif

<p style="margin:24px 0 0;font-size:12px;color:#94a3b8;text-align:center;">Submitted on {{ $application['submitted_at'] ?? now()->format('d M Y, h:i A') }}</p>

@include('email-templates.form._footer')
