@include('email-templates.form._header')

<h1 style="margin:0 0 8px;font-size:26px;font-weight:800;color:#0f172a;">Session cancelled</h1>
<p style="margin:0 0 20px;font-size:15px;line-height:1.65;color:#475569;">
    Hi {{ $mentee_first_name }}, your session with {{ $mentor_first_name }} has been cancelled.
    @if(!empty($cancellation_reason))
        <br><br><strong>Reason:</strong> {{ $cancellation_reason }}
    @endif
</p>

@include('email-templates.form._mentor-session-details')

@include('email-templates.form._footer')
