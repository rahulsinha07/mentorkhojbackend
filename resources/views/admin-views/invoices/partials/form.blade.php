@php
    $isEdit = isset($invoice);
    $formAction = $isEdit ? route('admin.invoices.update', $invoice->id) : route('admin.invoices.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $prefillData = $isEdit ? $invoice : ($prefill ?? []);
    if (!is_array($prefillData)) {
        $prefillData = $prefillData->toArray();
    }
    $items = old('items', $isEdit ? $invoice->items->toArray() : ($prefill['items'] ?? [[
        'service_name' => '', 'quantity' => 1, 'unit' => 'Qty', 'unit_price' => 0, 'discount' => 0, 'discount_type' => 'fixed', 'tax_rate' => $settings->default_tax_rate ?? 18,
    ]]));
    $effectiveTaxMode = old('tax_mode', $isEdit ? ($invoice->tax_mode ?? 'none') : ($prefill['tax_mode'] ?? $settings->default_tax_mode ?? 'cgst_sgst'));
    $defaultTaxRate = (float) ($settings->default_tax_rate ?? 18);
    if ($effectiveTaxMode !== 'none') {
        foreach ($items as $idx => $item) {
            if ((float) ($item['tax_rate'] ?? 0) <= 0) {
                $items[$idx]['tax_rate'] = $defaultTaxRate;
            }
        }
    }
    $demoBooking = $prefill['demo_booking'] ?? null;
    $prefillMentors = $prefill['mentors'] ?? ($prefill['mentor_snapshot'] ?? []);
    $mentors = $mentors ?? \App\Model\Mentor\Mentor::query()
        ->with(['enabledServices' => fn ($q) => $q->limit(1)])
        ->orderBy('display_name')
        ->get(['id', 'display_name', 'username', 'headline'])
        ->map(function ($mentor) {
            $service = $mentor->enabledServices->first();

            return (object) [
                'id' => $mentor->id,
                'display_name' => $mentor->display_name,
                'username' => $mentor->username,
                'default_price' => $service ? (float) $service->price : 0,
                'service_title' => $service->title ?? null,
            ];
        });
    $mentorSiteUrl = rtrim((string) config('app.mentorkhoj_site_url', 'https://www.mentorkhoj.com'), '/');
    $mentorsJson = $mentors->map(function ($m) {
        return [
            'id' => $m->id,
            'name' => $m->display_name,
            'username' => $m->username,
            'default_price' => (float) ($m->default_price ?? 0),
            'service_title' => $m->service_title ?? null,
        ];
    })->values();
@endphp

<form id="invoice-form" action="{{ $formAction }}" method="post">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <input type="hidden" name="action" id="invoice-action" value="generate">
    <input type="hidden" name="source_type" value="{{ old('source_type', $prefillData['source_type'] ?? '') }}">
    <input type="hidden" name="source_id" value="{{ old('source_id', $prefillData['source_id'] ?? '') }}">
    <input type="hidden" name="user_id" id="user_id" value="{{ old('user_id', $prefillData['user_id'] ?? '') }}">
    @if(!empty($prefillMentors))
        <input type="hidden" name="mentor_snapshot" id="mentor_snapshot" value="{{ old('mentor_snapshot', json_encode($prefillMentors)) }}">
    @else
        <input type="hidden" name="mentor_snapshot" id="mentor_snapshot" value="{{ old('mentor_snapshot', $isEdit && $invoice->mentor_snapshot ? json_encode($invoice->mentor_snapshot) : '') }}">
    @endif

    <div class="row">
        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ translate('Create from existing record') }}</h5>
                </div>
                <div class="card-body row g-2">
                    <div class="col-md-4"><input type="number" id="prefill-order-id" class="form-control" placeholder="{{ translate('Order ID') }}"></div>
                    <div class="col-md-4"><input type="number" id="prefill-booking-id" class="form-control" placeholder="{{ translate('Mentor Booking ID') }}"></div>
                    <div class="col-md-4"><input type="text" id="prefill-demo-ref" class="form-control" placeholder="DM-PDOQJR-7117" value="{{ old('demo_ref', request('demo_ref', '')) }}"></div>
                    <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                        <button type="button" class="btn btn--secondary btn-sm" id="btn-prefill-order">{{ translate('Load Order') }}</button>
                        <button type="button" class="btn btn--secondary btn-sm" id="btn-prefill-booking">{{ translate('Load Booking') }}</button>
                        <button type="button" class="btn btn--primary btn-sm" id="btn-prefill-demo">{{ translate('Load Demo') }}</button>
                        <span id="prefill-demo-status" class="small"></span>
                    </div>
                    @if(!empty($demoPrefillError))
                        <div class="col-12"><div class="alert alert-danger py-2 mb-0">{{ $demoPrefillError }}</div></div>
                    @endif
                    <div class="col-12"><small class="text-muted">{{ translate('Demo ref loads student details and one line item per assigned mentor.') }}</small></div>
                    <div class="col-12" id="demo-details-card" @if(empty($demoBooking)) style="display:none;" @endif>
                        <div class="card bg-light border mb-0">
                            <div class="card-body py-2">
                                <h6 class="mb-2">{{ translate('Demo student details') }}</h6>
                                <div class="row small" id="demo-details-body">
                                    @if(!empty($demoBooking))
                                        <div class="col-md-6"><strong>{{ translate('Name') }}:</strong> {{ $demoBooking['name'] ?? '—' }}</div>
                                        <div class="col-md-6"><strong>{{ translate('Ref') }}:</strong> {{ $demoBooking['booking_ref'] ?? '—' }}</div>
                                        <div class="col-md-6"><strong>{{ translate('Phone') }}:</strong> {{ $demoBooking['phone'] ?? '—' }}</div>
                                        <div class="col-md-6"><strong>{{ translate('Email') }}:</strong> {{ $demoBooking['email'] ?? '—' }}</div>
                                        <div class="col-md-6"><strong>{{ translate('Program') }}:</strong> {{ $demoBooking['program'] ?? ($demoBooking['category_label'] ?? '—') }}</div>
                                        <div class="col-md-6"><strong>{{ translate('Stage') }}:</strong> {{ $demoBooking['stage'] ?? '—' }}</div>
                                        <div class="col-md-6"><strong>{{ translate('Subjects') }}:</strong> {{ is_array($demoBooking['subjects'] ?? null) ? implode(', ', $demoBooking['subjects']) : '—' }}</div>
                                        <div class="col-md-6"><strong>{{ translate('Mentors') }}:</strong> {{ !empty($prefillMentors) ? collect($prefillMentors)->pluck('name')->implode(', ') : '—' }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{ translate('Invoice Information') }}</h5></div>
                <div class="card-body row">
                    <div class="col-md-4 form-group">
                        <label>{{ translate('Invoice Number') }}</label>
                        <input type="text" name="invoice_number" id="invoice_number" class="form-control"
                               value="{{ old('invoice_number', $isEdit ? $invoice->invoice_number : ($nextInvoiceNumber ?? '')) }}">
                        <div class="custom-control custom-checkbox mt-1">
                            <input type="checkbox" class="custom-control-input" id="invoice_number_manual" name="invoice_number_manual" value="1"
                                @checked(old('invoice_number_manual', $isEdit ? $invoice->invoice_number_manual : false))>
                            <label class="custom-control-label" for="invoice_number_manual">{{ translate('Manual override') }}</label>
                        </div>
                    </div>
                    <div class="col-md-4 form-group"><label>{{ translate('Invoice Date') }} *</label><input type="date" name="invoice_date" class="form-control" required value="{{ old('invoice_date', $isEdit ? $invoice->invoice_date?->format('Y-m-d') : ($prefill['invoice_date'] ?? date('Y-m-d'))) }}"></div>
                    <div class="col-md-4 form-group"><label>{{ translate('Due Date') }}</label><input type="date" name="due_date" class="form-control" value="{{ old('due_date', $isEdit ? $invoice->due_date?->format('Y-m-d') : ($prefill['due_date'] ?? '')) }}"></div>
                    <div class="col-md-4 form-group"><label>{{ translate('Currency') }}</label><input type="text" name="currency" class="form-control" value="{{ old('currency', $isEdit ? $invoice->currency : ($prefill['currency'] ?? $settings->default_currency)) }}"></div>
                    <div class="col-md-4 form-group"><label>{{ translate('Place of Supply') }}</label><input type="text" name="place_of_supply" id="place_of_supply" class="form-control" value="{{ old('place_of_supply', $isEdit ? $invoice->place_of_supply : ($prefill['place_of_supply'] ?? $company['default_place_of_supply'] ?? 'Bihar')) }}"></div>
                    <div class="col-md-4 form-group"><label>{{ translate('Reference Number') }}</label><input type="text" name="reference_number" class="form-control" value="{{ old('reference_number', $isEdit ? $invoice->reference_number : ($prefill['reference_number'] ?? '')) }}"></div>
                    <div class="col-md-4 form-group">
                        <label>{{ translate('Tax Structure') }}</label>
                        <select name="tax_mode" id="tax_mode" class="form-control">
                            @foreach(['none'=>'No Tax','cgst_sgst'=>'CGST + SGST','igst'=>'IGST','gst'=>'Auto GST','custom'=>'Custom Tax'] as $val=>$label)
                                <option value="{{ $val }}" @selected(old('tax_mode', $isEdit ? $invoice->tax_mode : ($prefill['tax_mode'] ?? $settings->default_tax_mode))===$val)>{{ translate($label) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{ translate('Customer / Bill To') }}</h5></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>{{ translate('Search existing customer') }}</label>
                        <input type="text" id="customer-search" class="form-control" placeholder="{{ translate('Name, email or phone') }}">
                        <div id="customer-search-results" class="list-group mt-1"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group"><label>{{ translate('Customer Name') }} *</label><input type="text" name="customer_name" id="customer_name" class="form-control" required value="{{ old('customer_name', $isEdit ? $invoice->customer_name : ($prefill['customer_name'] ?? '')) }}"></div>
                        <div class="col-md-6 form-group"><label>{{ translate('Customer Type') }}</label><input type="text" name="customer_type" class="form-control" value="{{ old('customer_type', $isEdit ? $invoice->customer_type : ($prefill['customer_type'] ?? '')) }}"></div>
                        <div class="col-md-6 form-group"><label>{{ translate('Company Name') }}</label><input type="text" name="customer_company" class="form-control" value="{{ old('customer_company', $isEdit ? $invoice->customer_company : ($prefill['customer_company'] ?? '')) }}"></div>
                        <div class="col-md-6 form-group"><label>{{ translate('Customer ID') }}</label><input type="text" name="customer_external_id" class="form-control" value="{{ old('customer_external_id', $isEdit ? $invoice->customer_external_id : ($prefill['customer_external_id'] ?? '')) }}"></div>
                        <div class="col-md-6 form-group"><label>{{ translate('Email') }}</label><input type="email" name="customer_email" id="customer_email" class="form-control" value="{{ old('customer_email', $isEdit ? $invoice->customer_email : ($prefill['customer_email'] ?? '')) }}"></div>
                        <div class="col-md-6 form-group"><label>{{ translate('Phone') }}</label><input type="text" name="customer_phone" id="customer_phone" class="form-control" value="{{ old('customer_phone', $isEdit ? $invoice->customer_phone : ($prefill['customer_phone'] ?? '')) }}"></div>
                        <div class="col-12 form-group"><label>{{ translate('Billing Address') }}</label><textarea name="billing_address" id="billing_address" class="form-control" rows="2">{{ old('billing_address', $isEdit ? $invoice->billing_address : ($prefill['billing_address'] ?? '')) }}</textarea></div>
                        <div class="col-md-3 form-group"><label>{{ translate('City') }}</label><input type="text" name="billing_city" id="billing_city" class="form-control" value="{{ old('billing_city', $isEdit ? $invoice->billing_city : ($prefill['billing_city'] ?? '')) }}"></div>
                        <div class="col-md-3 form-group"><label>{{ translate('State') }}</label><input type="text" name="billing_state" id="billing_state" class="form-control" value="{{ old('billing_state', $isEdit ? $invoice->billing_state : ($prefill['billing_state'] ?? '')) }}"></div>
                        <div class="col-md-3 form-group"><label>{{ translate('Country') }}</label><input type="text" name="billing_country" class="form-control" value="{{ old('billing_country', $isEdit ? $invoice->billing_country : ($prefill['billing_country'] ?? 'India')) }}"></div>
                        <div class="col-md-3 form-group"><label>{{ translate('PIN/ZIP') }}</label><input type="text" name="billing_postal_code" class="form-control" value="{{ old('billing_postal_code', $isEdit ? $invoice->billing_postal_code : ($prefill['billing_postal_code'] ?? '')) }}"></div>
                        <div class="col-md-6 form-group"><label>{{ translate('GSTIN') }}</label><input type="text" name="customer_gstin" class="form-control" value="{{ old('customer_gstin', $isEdit ? $invoice->customer_gstin : ($prefill['customer_gstin'] ?? '')) }}"></div>
                        <div class="col-md-6 form-group"><label>{{ translate('PAN') }}</label><input type="text" name="customer_pan" class="form-control" value="{{ old('customer_pan', $isEdit ? $invoice->customer_pan : ($prefill['customer_pan'] ?? '')) }}"></div>
                        <div class="col-md-6 form-group"><label>{{ translate('Aadhaar Number') }}</label><input type="text" name="customer_aadhaar" id="customer_aadhaar" class="form-control" maxlength="12" pattern="\d{12}" placeholder="12-digit Aadhaar" value="{{ old('customer_aadhaar', $isEdit ? $invoice->customer_aadhaar : ($prefill['customer_aadhaar'] ?? '')) }}"></div>
                        <div class="col-md-6 form-group"><label>{{ translate('Number of Classes Booked') }}</label><input type="number" name="classes_booked" id="classes_booked" class="form-control" min="1" value="{{ old('classes_booked', $isEdit ? $invoice->classes_booked : ($prefill['classes_booked'] ?? '')) }}"></div>
                        <div class="col-12">
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" id="shipping_same" checked>
                                <label class="custom-control-label" for="shipping_same">{{ translate('Shipping address same as billing address') }}</label>
                            </div>
                        </div>
                        <div class="col-12" id="shipping-fields" style="display:none;">
                            <div class="form-group"><label>{{ translate('Shipping Address') }}</label><textarea name="shipping_address" id="shipping_address" class="form-control" rows="2">{{ old('shipping_address', $isEdit ? $invoice->shipping_address : '') }}</textarea></div>
                            <div class="row">
                                <div class="col-md-3 form-group"><label>{{ translate('City') }}</label><input type="text" name="shipping_city" id="shipping_city" class="form-control" value="{{ old('shipping_city', $isEdit ? $invoice->shipping_city : '') }}"></div>
                                <div class="col-md-3 form-group"><label>{{ translate('State') }}</label><input type="text" name="shipping_state" id="shipping_state" class="form-control" value="{{ old('shipping_state', $isEdit ? $invoice->shipping_state : '') }}"></div>
                                <div class="col-md-3 form-group"><label>{{ translate('Country') }}</label><input type="text" name="shipping_country" class="form-control" value="{{ old('shipping_country', $isEdit ? $invoice->shipping_country : '') }}"></div>
                                <div class="col-md-3 form-group"><label>{{ translate('PIN/ZIP') }}</label><input type="text" name="shipping_postal_code" class="form-control" value="{{ old('shipping_postal_code', $isEdit ? $invoice->shipping_postal_code : '') }}"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">{{ translate('Invoice Items') }}</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn--primary btn-add-item-row">{{ translate('Add Item') }}</button>
                        <button type="button" class="btn btn-sm btn--secondary" id="btn-calculate-items">{{ translate('Calculate Totals') }}</button>
                    </div>
                </div>
                <div class="card-body invoice-items-wrap p-0 p-md-3">
                    <table class="table table-bordered invoice-items-table mb-0" id="items-table">
                        <colgroup>
                            <col class="col-mentor">
                            <col class="col-desc">
                            <col class="col-sessions">
                            <col class="col-unit">
                            <col class="col-rate">
                            <col class="col-disc">
                            <col class="col-disc-type">
                            <col class="col-tax">
                            <col class="col-total">
                            <col class="col-actions">
                        </colgroup>
                        <thead><tr>
                            <th class="col-mentor">{{ translate('Mentor') }}</th>
                            <th class="col-desc">{{ translate('Description') }}</th>
                            <th class="col-sessions">{{ translate('Sessions') }}</th>
                            <th class="col-unit">{{ translate('Unit') }}</th>
                            <th class="col-rate">{{ translate('Rate (₹)') }}</th>
                            <th class="col-disc">{{ translate('Discount') }}</th>
                            <th class="col-disc-type">{{ translate('Disc type') }}</th>
                            <th class="col-tax">{{ translate('GST %') }}</th>
                            <th class="col-total">{{ translate('Total') }}</th>
                            <th class="col-actions"></th>
                        </tr></thead>
                        <tbody id="items-body">
                        @foreach($items as $i => $item)
                            @include('admin-views.invoices.partials.item-row', ['index' => $i, 'item' => $item, 'mentors' => $mentors])
                        @endforeach
                        </tbody>
                    </table>
                    <div class="px-3 pb-3 pt-2 d-flex flex-wrap gap-2 align-items-center border-top bg-light">
                        <button type="button" class="btn btn-sm btn--primary btn-add-item-row">{{ translate('Add Item') }}</button>
                        <button type="button" class="btn btn-sm btn--secondary btn-calculate-items">{{ translate('Calculate Totals') }}</button>
                        <span id="calc-status" class="small text-muted ms-1"></span>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{ translate('Payment Information') }}</h5></div>
                <div class="card-body row">
                    <div class="col-md-4 form-group">
                        <label>{{ translate('Payment Status') }}</label>
                        <select name="payment_status" id="payment_status" class="form-control">
                            @foreach(['paid','partially_paid','pending','cancelled','refunded'] as $st)
                                <option value="{{ $st }}" @selected(old('payment_status', $isEdit ? $invoice->payment_status : ($prefill['payment_status'] ?? 'pending'))===$st)>{{ ucfirst(str_replace('_',' ',$st)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>{{ translate('Payment Method') }}</label>
                        <select name="payment_method" class="form-control">
                            <option value="">{{ translate('Select') }}</option>
                            @foreach(['cash','upi','bank_transfer','credit_card','debit_card','online_payment','other'] as $m)
                                <option value="{{ $m }}" @selected(old('payment_method', $isEdit ? $invoice->payment_method : ($prefill['payment_method'] ?? ''))===$m)>{{ ucfirst(str_replace('_',' ',$m)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 form-group"><label>{{ translate('Payment Date') }}</label><input type="date" name="payment_date" class="form-control" value="{{ old('payment_date', $isEdit ? $invoice->payment_date?->format('Y-m-d') : '') }}"></div>
                    <div class="col-md-4 form-group"><label>{{ translate('Transaction ID') }}</label><input type="text" name="transaction_id" class="form-control" value="{{ old('transaction_id', $isEdit ? $invoice->transaction_id : ($prefill['transaction_id'] ?? '')) }}"></div>
                    <div class="col-md-4 form-group"><label>{{ translate('Amount Paid') }}</label><input type="number" step="0.01" name="amount_paid" id="amount_paid" class="form-control" value="{{ old('amount_paid', $isEdit ? $invoice->amount_paid : ($prefill['amount_paid'] ?? 0)) }}"></div>
                    <div class="col-md-4 form-group"><label>{{ translate('Additional Charges') }}</label><input type="number" step="0.01" name="additional_charges" id="additional_charges" class="form-control" value="{{ old('additional_charges', $isEdit ? $invoice->additional_charges : 0) }}"></div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">{{ translate('Notes & Terms') }}</h5></div>
                <div class="card-body">
                    <div class="form-group"><label>{{ translate('Customer Notes') }}</label><textarea name="customer_notes" class="form-control" rows="2">{{ old('customer_notes', $isEdit ? $invoice->customer_notes : ($prefill['customer_notes'] ?? $settings->default_notes)) }}</textarea></div>
                    <div class="form-group mb-0"><label>{{ translate('Terms & Conditions') }}</label><textarea name="terms" class="form-control" rows="4">{{ old('terms', $isEdit ? $invoice->terms : ($prefill['terms'] ?? $settings->default_terms)) }}</textarea></div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card sticky-top" style="top:80px;">
                <div class="card-header d-flex justify-content-between align-items-center gap-2">
                    <h5 class="mb-0">{{ translate('Invoice Summary') }}</h5>
                    <button type="button" class="btn btn-sm btn--primary btn-calculate-items">{{ translate('Calculate') }}</button>
                </div>
                <div class="card-body" id="invoice-summary">
                    <div class="d-flex justify-content-between"><span>{{ translate('Gross Amount') }}</span><strong id="sum-subtotal">₹0.00</strong></div>
                    <div class="d-flex justify-content-between" id="sum-discount-row"><span>{{ translate('Discount (−)') }}</span><strong id="sum-discount">₹0.00</strong></div>
                    <div class="d-flex justify-content-between"><span>{{ translate('Taxable Amount') }}</span><strong id="sum-taxable">₹0.00</strong></div>
                    <div class="d-flex justify-content-between" id="sum-gst-row" style="display:none;"><span>{{ translate('Total GST') }}</span><strong id="sum-gst">₹0.00</strong></div>
                    <div class="d-flex justify-content-between small text-muted" id="sum-cgst-row" style="display:none;"><span>{{ translate('↳ CGST (half)') }}</span><strong id="sum-cgst">₹0.00</strong></div>
                    <div class="d-flex justify-content-between small text-muted" id="sum-sgst-row" style="display:none;"><span>{{ translate('↳ SGST (half)') }}</span><strong id="sum-sgst">₹0.00</strong></div>
                    <div class="d-flex justify-content-between" id="sum-igst-row" style="display:none;"><span>{{ translate('IGST') }}</span><strong id="sum-igst">₹0.00</strong></div>
                    <div class="d-flex justify-content-between" id="sum-other-row" style="display:none;"><span>{{ translate('Other Tax') }}</span><strong id="sum-other">₹0.00</strong></div>
                    <p class="small text-muted mb-2" id="tax-mode-hint" style="display:none;">{{ translate('GST % on each row is applied once. CGST + SGST are equal halves of that GST (not added again).') }}</p>
                    <div class="d-flex justify-content-between"><span>{{ translate('Additional Charges') }}</span><strong id="sum-additional">₹0.00</strong></div>
                    <div class="d-flex justify-content-between"><span>{{ translate('Round Off') }}</span><strong id="sum-roundoff">₹0.00</strong></div>
                    <hr>
                    <div class="d-flex justify-content-between fs-5"><span>{{ translate('Total') }}</span><strong id="sum-total">₹0.00</strong></div>
                    <div class="d-flex justify-content-between"><span>{{ translate('Amount Paid') }}</span><strong id="sum-paid">₹0.00</strong></div>
                    <div class="d-flex justify-content-between"><span>{{ translate('Balance Due') }}</span><strong id="sum-balance">₹0.00</strong></div>
                </div>
                <div class="card-footer d-grid gap-2">
                    <button type="submit" class="btn btn--primary" onclick="document.getElementById('invoice-action').value='generate'">{{ translate('Generate Invoice') }}</button>
                    <button type="submit" class="btn btn--secondary" onclick="document.getElementById('invoice-action').value='draft'">{{ translate('Save as Draft') }}</button>
                </div>
            </div>
        </div>
    </div>
</form>

@push('css')
<style>
    .invoice-items-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .invoice-items-table {
        min-width: 1280px;
        width: max-content;
        max-width: none;
        table-layout: auto;
    }
    .invoice-items-table th,
    .invoice-items-table td { vertical-align: middle; padding: 0.45rem; white-space: nowrap; }
    .invoice-items-table .col-desc { white-space: normal; min-width: 140px; max-width: 220px; }
    .invoice-items-table .col-mentor { min-width: 160px; }
    .invoice-items-table .col-sessions { min-width: 88px; }
    .invoice-items-table .col-unit { min-width: 96px; }
    .invoice-items-table .col-rate { min-width: 132px; }
    .invoice-items-table .col-disc { min-width: 84px; }
    .invoice-items-table .col-disc-type { min-width: 92px; }
    .invoice-items-table .col-tax { min-width: 84px; }
    .invoice-items-table .col-total { min-width: 96px; text-align: right; }
    .invoice-items-table .col-actions { min-width: 96px; text-align: center; }
    .invoice-items-table input.form-control-sm,
    .invoice-items-table select.form-control-sm {
        width: 100%;
        min-width: 72px;
        min-height: 36px;
        font-size: 0.9rem;
        padding-left: 0.45rem;
        padding-right: 0.45rem;
    }
    .invoice-items-table .item-rate,
    .invoice-items-table .item-qty,
    .invoice-items-table .item-tax,
    .invoice-items-table .item-discount {
        font-weight: 600;
        text-align: right;
        font-variant-numeric: tabular-nums;
        min-width: 80px !important;
    }
    .invoice-items-table .item-unit { min-width: 88px !important; }
    .invoice-items-table .item-line-total {
        font-weight: 700;
        white-space: nowrap;
        color: #1e3a5f;
        font-variant-numeric: tabular-nums;
    }
    .invoice-items-table .col-actions .btn-row-action {
        min-width: 32px;
        padding: 4px 8px;
        line-height: 1.2;
    }
    .invoice-items-table .is-invalid { border-color: #dc3545 !important; }
    #calc-status.text-success { color: #008768 !important; }
    #calc-status.text-danger { color: #dc3545 !important; }
</style>
@endpush

@push('script')
<script>
    window.invoiceFormConfig = {
        companyState: @json($company['state'] ?? 'Bihar'),
        calculateUrl: @json(route('admin.invoices.calculate')),
        searchUsersUrl: @json(route('admin.invoices.search-users')),
        prefillOrderUrl: @json(url('/admin/invoices/prefill/order')),
        prefillBookingUrl: @json(url('/admin/invoices/prefill/booking')),
        prefillDemoUrl: @json(url('/admin/invoices/prefill/demo')),
        defaultTaxRate: {{ (float) ($settings->default_tax_rate ?? 18) }},
        autoDemoRef: @json(request('demo_ref')),
        mentors: @json($mentorsJson),
        mentorkhojSiteUrl: @json($mentorSiteUrl),
    };
</script>
<script src="{{ asset('public/assets/admin/js/invoice-form.js') }}?v=10"></script>
@endpush
