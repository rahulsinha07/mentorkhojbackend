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
@endphp

<form id="invoice-form" action="{{ $formAction }}" method="post">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <input type="hidden" name="action" id="invoice-action" value="generate">
    <input type="hidden" name="source_type" value="{{ old('source_type', $prefillData['source_type'] ?? '') }}">
    <input type="hidden" name="source_id" value="{{ old('source_id', $prefillData['source_id'] ?? '') }}">
    <input type="hidden" name="user_id" id="user_id" value="{{ old('user_id', $prefillData['user_id'] ?? '') }}">

    <div class="row">
        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ translate('Create from existing record') }}</h5>
                </div>
                <div class="card-body row g-2">
                    <div class="col-md-4"><input type="number" id="prefill-order-id" class="form-control" placeholder="{{ translate('Order ID') }}"></div>
                    <div class="col-md-4"><input type="number" id="prefill-booking-id" class="form-control" placeholder="{{ translate('Booking ID') }}"></div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn--secondary" id="btn-prefill-order">{{ translate('Load Order') }}</button>
                        <button type="button" class="btn btn--secondary" id="btn-prefill-booking">{{ translate('Load Booking') }}</button>
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ translate('Invoice Items') }}</h5>
                    <button type="button" class="btn btn-sm btn--primary" id="add-item-row">{{ translate('Add Item') }}</button>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered" id="items-table">
                        <thead><tr>
                            <th>{{ translate('Item') }}</th>
                            <th>{{ translate('Description') }}</th>
                            <th>{{ translate('Qty') }}</th>
                            <th>{{ translate('Unit') }}</th>
                            <th>{{ translate('Rate') }}</th>
                            <th>{{ translate('Disc') }}</th>
                            <th>{{ translate('Disc %') }}</th>
                            <th>{{ translate('Tax %') }}</th>
                            <th>{{ translate('Total') }}</th>
                            <th></th>
                        </tr></thead>
                        <tbody id="items-body">
                        @foreach($items as $i => $item)
                            @include('admin-views.invoices.partials.item-row', ['index' => $i, 'item' => $item])
                        @endforeach
                        </tbody>
                    </table>
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
                <div class="card-header"><h5 class="mb-0">{{ translate('Invoice Summary') }}</h5></div>
                <div class="card-body" id="invoice-summary">
                    <div class="d-flex justify-content-between"><span>{{ translate('Subtotal') }}</span><strong id="sum-subtotal">₹0.00</strong></div>
                    <div class="d-flex justify-content-between"><span>{{ translate('Discount') }}</span><strong id="sum-discount">₹0.00</strong></div>
                    <div class="d-flex justify-content-between"><span>{{ translate('Taxable Amount') }}</span><strong id="sum-taxable">₹0.00</strong></div>
                    <div class="d-flex justify-content-between"><span>{{ translate('CGST') }}</span><strong id="sum-cgst">₹0.00</strong></div>
                    <div class="d-flex justify-content-between"><span>{{ translate('SGST') }}</span><strong id="sum-sgst">₹0.00</strong></div>
                    <div class="d-flex justify-content-between"><span>{{ translate('IGST') }}</span><strong id="sum-igst">₹0.00</strong></div>
                    <div class="d-flex justify-content-between"><span>{{ translate('Other Tax') }}</span><strong id="sum-other">₹0.00</strong></div>
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

@push('script')
<script>
    window.invoiceFormConfig = {
        companyState: @json($company['state'] ?? 'Bihar'),
        calculateUrl: @json(route('admin.invoices.calculate')),
        searchUsersUrl: @json(route('admin.invoices.search-users')),
        prefillOrderUrl: @json(url('/admin/invoices/prefill/order')),
        prefillBookingUrl: @json(url('/admin/invoices/prefill/booking')),
        defaultTaxRate: {{ (float) ($settings->default_tax_rate ?? 18) }},
    };
</script>
<script src="{{ asset('public/assets/admin/js/invoice-form.js') }}?v=1"></script>
@endpush
