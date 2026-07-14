@include('email-templates.form._header')

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
        <td style="padding-bottom:16px;">
            <span style="display:inline-block;background-color:#fef3c7;color:#b45309;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;padding:6px 14px;border-radius:999px;">Admin notification</span>
        </td>
    </tr>
</table>

<h1 style="margin:0 0 8px;font-size:22px;font-weight:800;color:#0f172a;">New internship application</h1>
<p style="margin:0 0 20px;font-size:14px;color:#64748b;">Reference <strong style="color:#4f46e5;">#{{ $application['id'] }}</strong> · Role: <strong>{{ $application['role'] }}</strong></p>

<h2 style="margin:0 0 12px;font-size:14px;font-weight:700;color:#006161;text-transform:uppercase;letter-spacing:0.06em;">Applicant details</h2>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:20px;">
            <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong>Name:</strong> {{ $application['name'] }}</p>
            <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong>Email:</strong> <a href="mailto:{{ $application['email'] }}" style="color:#006161;">{{ $application['email'] }}</a></p>
            <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong>Phone:</strong> <a href="tel:{{ $application['phone'] }}" style="color:#006161;">{{ $application['phone'] }}</a></p>
            <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong>College / Company:</strong> {{ $application['org'] }}</p>
            @if(!empty($application['resume_url']))
                <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong>Resume:</strong> <a href="{{ $application['resume_url'] }}" style="color:#006161;">{{ $application['resume_url'] }}</a></p>
            @endif
            @if(!empty($application['message']))
                <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong>Message:</strong> {{ $application['message'] }}</p>
            @endif
            <p style="margin:0;font-size:14px;color:#334155;"><strong>Submitted:</strong> {{ $application['submitted_at'] }}</p>
        </td>
    </tr>
</table>

@if($internship)
<h2 style="margin:0 0 12px;font-size:14px;font-weight:700;color:#006161;text-transform:uppercase;letter-spacing:0.06em;">Role — {{ $internship['role'] }}</h2>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
        <td style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:20px;">
            <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong>Team:</strong> {{ $internship['team'] ?? '—' }}</p>
            <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong>Location:</strong> {{ $internship['location'] ?? '—' }}</p>
            <p style="margin:0 0 8px;font-size:14px;color:#334155;"><strong>Stipend:</strong> {{ $internship['stipend'] ?? '—' }}</p>
            @if(!empty($internship['skills']))
                <p style="margin:12px 0 0;font-size:14px;color:#334155;"><strong>Skills:</strong> {{ implode(', ', $internship['skills']) }}</p>
            @endif
        </td>
    </tr>
</table>
@endif

@include('email-templates.form._footer')
