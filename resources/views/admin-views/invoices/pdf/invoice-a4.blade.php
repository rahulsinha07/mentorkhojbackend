@php
    $brandColor = $company['brand_color'] ?? '#107980';
    $fmt = fn($n) => \App\CentralLogics\Helpers::set_symbol($n);
@endphp
<style>
    .inv-wrap { font-family: DejaVu Sans, Arial, sans-serif; color:#1a1a1a; font-size:12px; line-height:1.45; }
    .inv-header { border-bottom:3px solid {{ $brandColor }}; padding-bottom:14px; margin-bottom:18px; }
    .inv-brand { font-size:22px; font-weight:700; color:{{ $brandColor }}; margin:0; }
    .inv-legal { font-size:11px; color:#444; margin-top:4px; }
    .inv-title { font-size:28px; font-weight:700; letter-spacing:2px; color:#222; text-align:right; }
    .inv-meta { text-align:right; font-size:11px; }
    .inv-grid { width:100%; margin-bottom:16px; }
    .inv-grid td { vertical-align:top; width:50%; padding:0 8px 0 0; }
    .inv-box { background:#f8fafb; border:1px solid #e5ecef; border-radius:4px; padding:10px 12px; }
    .inv-box h4 { margin:0 0 6px; font-size:11px; text-transform:uppercase; color:{{ $brandColor }}; }
    .inv-table { width:100%; border-collapse:collapse; margin:12px 0; }
    .inv-table thead th { background:{{ $brandColor }}; color:#fff; padding:8px 6px; font-size:10px; text-align:left; }
    .inv-table tbody td { border-bottom:1px solid #e8e8e8; padding:7px 6px; font-size:10px; }
    .inv-table .num { text-align:right; white-space:nowrap; }
    .inv-totals { width:100%; max-width:320px; margin-left:auto; }
    .inv-totals td { padding:4px 0; font-size:11px; }
    .inv-totals .label { text-align:left; }
    .inv-totals .val { text-align:right; font-weight:600; }
    .inv-totals .grand td { border-top:2px solid {{ $brandColor }}; font-size:14px; font-weight:700; padding-top:8px; }
    .inv-footer { margin-top:20px; padding-top:12px; border-top:1px solid #ddd; font-size:10px; color:#555; }
    .inv-words { background:#f3f8f8; padding:8px 10px; border-left:3px solid {{ $brandColor }}; margin:12px 0; font-size:11px; }
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
                <div class="inv-title">INVOICE</div>
                <p style="margin:10px 0 0;"><strong>Invoice No:</strong> {{ $invoice->invoice_number }}</p>
                <p style="margin:2px 0;"><strong>Invoice Date:</strong> {{ $invoice->invoice_date?->format('d M Y') }}</p>
                @if($invoice->due_date)<p style="margin:2px 0;"><strong>Due Date:</strong> {{ $invoice->due_date->format('d M Y') }}</p>@endif
                @if($invoice->place_of_supply)<p style="margin:2px 0;"><strong>Place of Supply:</strong> {{ $invoice->place_of_supply }}</p>@endif
                @if($invoice->reference_number)<p style="margin:2px 0;"><strong>Reference:</strong> {{ $invoice->reference_number }}</p>@endif
            </td>
        </tr>
    </table>

    <table class="inv-grid" style="border:0;">
        <tr>
            <td style="border:0;">
                <div class="inv-box">
                    <h4>Bill To</h4>
                    <strong>{{ $invoice->customer_name }}</strong><br>
                    @if($invoice->customer_company){{ $invoice->customer_company }}<br>@endif
                    @if($invoice->customer_email){{ $invoice->customer_email }}<br>@endif
                    @if($invoice->customer_phone){{ $invoice->customer_phone }}<br>@endif
                    @if($invoice->billing_address){{ $invoice->billing_address }}<br>@endif
                    @if($invoice->billing_city || $invoice->billing_state){{ trim($invoice->billing_city.' '.$invoice->billing_state) }}<br>@endif
                    @if($invoice->customer_gstin)GSTIN: {{ $invoice->customer_gstin }}@endif
                </div>
            </td>
            <td style="border:0;">
                @if($invoice->shipping_address && $invoice->shipping_address !== $invoice->billing_address)
                <div class="inv-box">
                    <h4>Ship To</h4>
                    {{ $invoice->shipping_address }}<br>
                    {{ trim(($invoice->shipping_city ?? '').' '.($invoice->shipping_state ?? '')) }}
                </div>
                @endif
            </td>
        </tr>
    </table>

    <table class="inv-table">
        <thead>
            <tr>
                <th style="width:28%;">Description</th>
                <th class="num">Qty</th>
                <th class="num">Rate</th>
                <th class="num">Discount</th>
                <th class="num">Tax</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
            <tr>
                <td>
                    <strong>{{ $item->service_name }}</strong>
                    @if($item->description)<br><span style="color:#666;">{{ $item->description }}</span>@endif
                </td>
                <td class="num">{{ $item->quantity }} {{ $item->unit }}</td>
                <td class="num">{{ $fmt($item->unit_price) }}</td>
                <td class="num">{{ $item->discount > 0 ? ($item->discount_type === 'percent' ? $item->discount.'%' : $fmt($item->discount)) : '—' }}</td>
                <td class="num">{{ $item->tax_rate > 0 ? $fmt($item->tax_amount) : '—' }}</td>
                <td class="num"><strong>{{ $fmt($item->line_total) }}</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="inv-totals" style="border:0;">
        <tr><td class="label">Subtotal</td><td class="val">{{ $fmt($invoice->subtotal) }}</td></tr>
        @if($invoice->discount_total > 0)<tr><td class="label">Discount</td><td class="val">- {{ $fmt($invoice->discount_total) }}</td></tr>@endif
        <tr><td class="label">Taxable Amount</td><td class="val">{{ $fmt($invoice->taxable_amount) }}</td></tr>
        @if($invoice->cgst > 0)<tr><td class="label">CGST</td><td class="val">{{ $fmt($invoice->cgst) }}</td></tr>@endif
        @if($invoice->sgst > 0)<tr><td class="label">SGST</td><td class="val">{{ $fmt($invoice->sgst) }}</td></tr>@endif
        @if($invoice->igst > 0)<tr><td class="label">IGST</td><td class="val">{{ $fmt($invoice->igst) }}</td></tr>@endif
        @if($invoice->other_tax > 0)<tr><td class="label">Other Tax</td><td class="val">{{ $fmt($invoice->other_tax) }}</td></tr>@endif
        @if($invoice->additional_charges > 0)<tr><td class="label">Additional Charges</td><td class="val">{{ $fmt($invoice->additional_charges) }}</td></tr>@endif
        @if($invoice->round_off != 0)<tr><td class="label">Round Off</td><td class="val">{{ $fmt($invoice->round_off) }}</td></tr>@endif
        <tr class="grand"><td class="label">TOTAL</td><td class="val">{{ $fmt($invoice->total_amount) }}</td></tr>
        <tr><td class="label">Amount Paid</td><td class="val">{{ $fmt($invoice->amount_paid) }}</td></tr>
        <tr><td class="label">Balance Due</td><td class="val">{{ $fmt($invoice->balance_due) }}</td></tr>
    </table>

    <div class="inv-words"><strong>Amount in Words:</strong> {{ $amount_in_words ?? '' }}</div>

    @if($invoice->payment_method || $invoice->transaction_id)
    <div class="inv-box" style="margin-bottom:12px;">
        <h4>Payment Information</h4>
        Status: {{ ucfirst(str_replace('_',' ', $invoice->payment_status)) }}<br>
        @if($invoice->payment_method)Method: {{ ucfirst(str_replace('_',' ', $invoice->payment_method)) }}<br>@endif
        @if($invoice->transaction_id)Transaction ID: {{ $invoice->transaction_id }}@endif
    </div>
    @endif

    @if($invoice->customer_notes)
    <p><strong>Notes:</strong><br>{!! nl2br(e($invoice->customer_notes)) !!}</p>
    @endif

    @if($invoice->terms)
    <p><strong>Terms & Conditions:</strong><br>{!! nl2br(e($invoice->terms)) !!}</p>
    @endif

    @if(!empty($company['bank_name']) || !empty($company['upi_id']))
    <div class="inv-box" style="margin-top:10px;">
        <h4>Bank / Payment Details</h4>
        @if(!empty($company['bank_name']))Bank: {{ $company['bank_name'] }}<br>@endif
        @if(!empty($company['account_name']))Account Name: {{ $company['account_name'] }}<br>@endif
        @if(!empty($company['account_number']))Account No: {{ $company['account_number'] }}<br>@endif
        @if(!empty($company['ifsc']))IFSC: {{ $company['ifsc'] }}<br>@endif
        @if(!empty($company['upi_id']))UPI: {{ $company['upi_id'] }}@endif
    </div>
    @endif

    <div class="inv-footer">
        {{ $company['footer_text'] ?? ($company['brand_name'].' — '.$company['legal_name']) }}<br>
        GSTIN: {{ $company['gstin'] ?? '' }} | {{ $company['phone'] ?? '' }} | {{ $company['email'] ?? '' }}
    </div>
</div>
