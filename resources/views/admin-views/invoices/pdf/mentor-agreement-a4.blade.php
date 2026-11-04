@php
    $brandColor = $company['brand_color'] ?? '#107980';
    $fmt = fn($n) => \App\CentralLogics\Helpers::set_symbol($n);
    $mentorDisplay = $mentor_display ?? [];
    $commercial = $commercial ?? ['lines' => [], 'platform_fee_percent' => 10, 'total_gross' => 0, 'total_platform_fee' => 0, 'total_net_payable' => 0];
    $mentorkhojUrl = $mentorkhoj_url ?? ($company['website'] ?? 'https://www.mentorkhoj.com/');
    $feePercent = $commercial['platform_fee_percent'] ?? 10;
@endphp
<style>
    .inv-wrap { font-family: DejaVu Sans, Arial, sans-serif; color:#1a1a1a; font-size:12px; line-height:1.45; }
    .inv-header { border-bottom:3px solid {{ $brandColor }}; padding-bottom:14px; margin-bottom:18px; }
    .inv-brand { font-size:22px; font-weight:700; color:{{ $brandColor }}; margin:0; }
    .inv-legal { font-size:11px; color:#444; margin-top:4px; }
    .inv-title { font-size:22px; font-weight:700; letter-spacing:1px; color:#222; text-align:right; }
    .inv-meta { text-align:right; font-size:11px; }
    .inv-grid { width:100%; margin-bottom:16px; }
    .inv-grid td { vertical-align:top; width:50%; padding:0 8px 0 0; }
    .inv-box { background:#f8fafb; border:1px solid #e5ecef; border-radius:4px; padding:10px 12px; }
    .inv-box h4 { margin:0 0 6px; font-size:11px; text-transform:uppercase; color:{{ $brandColor }}; }
    .inv-table { width:100%; border-collapse:collapse; margin:12px 0; }
    .inv-table thead th { background:{{ $brandColor }}; color:#fff; padding:8px 6px; font-size:10px; text-align:left; }
    .inv-table tbody td { border-bottom:1px solid #e8e8e8; padding:7px 6px; font-size:10px; }
    .inv-table .num { text-align:right; white-space:nowrap; }
    .inv-totals { width:100%; max-width:360px; margin-left:auto; }
    .inv-totals td { padding:4px 0; font-size:11px; }
    .inv-totals .label { text-align:left; }
    .inv-totals .val { text-align:right; font-weight:600; }
    .inv-totals .grand td { border-top:2px solid {{ $brandColor }}; font-size:14px; font-weight:700; padding-top:8px; }
    .inv-footer { margin-top:20px; padding-top:12px; border-top:1px solid #ddd; font-size:10px; color:#555; }
    .inv-words { background:#f3f8f8; padding:8px 10px; border-left:3px solid {{ $brandColor }}; margin:12px 0; font-size:11px; }
    .inv-warning { background:#fff8e6; border:1px solid #f0d78c; border-radius:4px; padding:8px 10px; margin:10px 0; font-size:11px; color:#7a5b00; }
    .inv-terms { margin-top:14px; font-size:10px; }
    .inv-terms h5 { margin:10px 0 4px; font-size:11px; color:{{ $brandColor }}; }
    .inv-terms p { margin:0 0 8px; color:#333; }
    .inv-sig { margin-top:24px; }
    .inv-sig td { width:50%; padding-top:40px; font-size:11px; vertical-align:top; }
</style>

<div class="inv-wrap">
    <table class="inv-header" style="width:100%;border:0;">
        <tr>
            <td style="width:55%;border:0;">
                @if(!empty($logo_path))
                    <img src="{{ $logo_path }}" alt="Logo" style="max-height:52px;max-width:160px;margin-bottom:8px;">
                @elseif(!empty($logo_url))
                    <img src="{{ $logo_url }}" alt="Logo" style="max-height:52px;max-width:160px;margin-bottom:8px;">
                @endif
                <p class="inv-brand">{{ $company['brand_name'] ?? 'Mentorkhoj' }}</p>
                <p class="inv-legal" style="margin:0;">{{ $company['tagline'] ?? '' }}</p>
                <p class="inv-legal" style="margin-top:8px;"><strong>{{ $company['legal_name'] ?? '' }}</strong></p>
                <p class="inv-legal">{{ $company['address'] ?? '' }}</p>
                <p class="inv-legal">GSTIN: <strong>{{ $company['gstin'] ?? '' }}</strong> | PAN: {{ $company['pan'] ?? '' }}</p>
                <p class="inv-legal">Phone: {{ $company['phone'] ?? '' }} | {{ $company['email'] ?? '' }}</p>
                <p class="inv-legal">{{ $company['website'] ?? '' }}</p>
            </td>
            <td style="width:45%;border:0;" class="inv-meta">
                <div class="inv-title">MENTOR SERVICE AGREEMENT</div>
                <p style="margin:10px 0 0;"><strong>Linked Invoice:</strong> {{ $invoice->invoice_number }}</p>
                <p style="margin:2px 0;"><strong>Agreement Date:</strong> {{ $invoice->invoice_date?->format('d M Y') }}</p>
                @if($invoice->reference_number)<p style="margin:2px 0;"><strong>Reference:</strong> {{ $invoice->reference_number }}</p>@endif
                @if(!empty($agreement_version))<p style="margin:2px 0;"><strong>Agreement Version:</strong> {{ $agreement_version }}</p>@endif
            </td>
        </tr>
    </table>

    <table class="inv-grid" style="border:0;">
        <tr>
            <td style="border:0;">
                <div class="inv-box">
                    <h4>Mentor / Service Provider</h4>
                    <strong>{{ $mentorDisplay['name'] ?? 'Mentor' }}</strong><br>
                    @if(!empty($mentorDisplay['headline'])){{ $mentorDisplay['headline'] }}<br>@endif
                    @if(!empty($mentorDisplay['profile_url']))<span style="color:#666;font-size:10px;">{{ $mentorDisplay['profile_url'] }}</span><br>@endif
                    @if(!empty($mentorDisplay['email']))Email: {{ $mentorDisplay['email'] }}<br>@endif
                    @if(!empty($mentorDisplay['phone']))Phone: {{ $mentorDisplay['phone'] }}<br>@endif
                    @if(!empty($mentorDisplay['aadhaar']))Aadhaar: {{ $mentorDisplay['aadhaar'] }}<br>@endif
                </div>
            </td>
            <td style="border:0;">
                <div class="inv-box">
                    <h4>Payout Details (on file)</h4>
                    @if(!empty($mentorDisplay['has_payout']))
                        @if(!empty($mentorDisplay['account_holder']))Account holder: <strong>{{ $mentorDisplay['account_holder'] }}</strong><br>@endif
                        @if(!empty($mentorDisplay['upi_id']))UPI ID: <strong>{{ $mentorDisplay['upi_id'] }}</strong>@endif
                    @else
                        <span style="color:#b45309;">{{ $payout_pending_note ?? 'Payout details pending — mentor must complete Payouts setup.' }}</span>
                    @endif
                </div>
                <div class="inv-box" style="margin-top:8px;">
                    <h4>Customer (Reference)</h4>
                    <strong>{{ $invoice->customer_name }}</strong><br>
                    @if($invoice->classes_booked)Sessions booked: <strong>{{ $invoice->classes_booked }}</strong><br>@endif
                </div>
            </td>
        </tr>
    </table>

    @if(empty($mentorDisplay['has_payout']))
    <div class="inv-warning">{{ $payout_pending_note ?? 'Payout details pending — mentor must complete Payouts setup on the MentorKhoj dashboard.' }}</div>
    @endif

    <h4 style="margin:14px 0 6px;font-size:12px;color:{{ $brandColor }};text-transform:uppercase;">Commercial Summary</h4>
    <table class="inv-table">
        <thead>
            <tr>
                <th style="width:30%;">Description</th>
                <th class="num">Qty</th>
                <th class="num">Gross Rate</th>
                <th class="num">Line Gross</th>
                <th class="num">Platform Fee ({{ $feePercent }}%)</th>
                <th class="num">Net Payable</th>
            </tr>
        </thead>
        <tbody>
            @forelse($commercial['lines'] ?? [] as $line)
            <tr>
                <td>
                    <strong>{{ $line['service_name'] }}</strong>
                    @if(!empty($line['description']))<br><span style="color:#666;">{{ $line['description'] }}</span>@endif
                </td>
                <td class="num">{{ $line['quantity'] }} {{ $line['unit'] ?? 'Session' }}</td>
                <td class="num">{{ $fmt($line['unit_price']) }}</td>
                <td class="num">{{ $fmt($line['line_gross']) }}</td>
                <td class="num">− {{ $fmt($line['platform_fee']) }}</td>
                <td class="num"><strong>{{ $fmt($line['net_payable']) }}</strong></td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align:center;color:#666;">No line items matched for this mentor on the linked invoice.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <table class="inv-totals" style="border:0;">
        <tr><td class="label">Total Gross (pre-tax)</td><td class="val">{{ $fmt($commercial['total_gross'] ?? 0) }}</td></tr>
        <tr><td class="label">Platform Fee ({{ $feePercent }}%)</td><td class="val">− {{ $fmt($commercial['total_platform_fee'] ?? 0) }}</td></tr>
        <tr class="grand"><td class="label">Net Payable to Mentor</td><td class="val">{{ $fmt($commercial['total_net_payable'] ?? 0) }}</td></tr>
    </table>

    <div class="inv-words"><strong>Net payable in words:</strong> {{ $net_amount_in_words ?? '' }}</div>

    <div class="inv-box" style="margin:12px 0;">
        <h4>Payout Instructions</h4>
        {{ $payout_note ?? 'Mentor payout is released only after each session is marked completed on MentorKhoj.' }}
    </div>

    <div class="inv-terms">
        <h4 style="margin:0 0 8px;font-size:12px;color:{{ $brandColor }};text-transform:uppercase;">Terms &amp; Conditions</h4>
        @foreach($terms_clauses ?? [] as $clause)
            <h5>{{ $clause['heading'] ?? '' }}</h5>
            <p>{{ $clause['body'] ?? '' }}</p>
        @endforeach
    </div>

    <table class="inv-sig" style="width:100%;border:0;">
        <tr>
            <td style="border:0;border-top:1px solid #ccc;">
                <strong>For {{ $company['legal_name'] ?? 'BetterWits Software Private Limited' }}</strong><br>
                <span style="color:#666;font-size:10px;">Authorised signatory — MentorKhoj Platform</span>
            </td>
            <td style="border:0;border-top:1px solid #ccc;">
                <strong>{{ $mentorDisplay['name'] ?? 'Mentor' }}</strong><br>
                <span style="color:#666;font-size:10px;">Mentor / Service Provider</span>
            </td>
        </tr>
    </table>

    <div class="inv-footer">
        {{ $company['footer_text'] ?? ($company['brand_name'].' — '.$company['legal_name']) }}<br>
        Visit <a href="{{ $mentorkhojUrl }}/" style="color:{{ $brandColor }};font-weight:600;">{{ $mentorkhojUrl }}/</a> for mentorship sessions and mentor profiles.<br>
        GSTIN: {{ $company['gstin'] ?? '' }} | {{ $company['phone'] ?? '' }} | {{ $company['email'] ?? '' }}
    </div>
</div>
