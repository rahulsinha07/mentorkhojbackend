@php
    $selectedMentorId = $item['mentor_id'] ?? null;
    if (!$selectedMentorId && !empty($item['sku'])) {
        if (ctype_digit((string) $item['sku'])) {
            $selectedMentorId = (int) $item['sku'];
        } else {
            $selectedMentorId = optional($mentors->firstWhere('username', $item['sku']))->id;
        }
    }
    $customItem = empty($selectedMentorId) && !empty($item['service_name'] ?? '');
    $unitValue = $item['unit'] ?? 'Session';
    $serviceNameValue = $item['service_name'] ?? '';
    $qtyValue = array_key_exists('quantity', $item) ? (int) $item['quantity'] : 1;
    $rateValue = array_key_exists('unit_price', $item) && $item['unit_price'] !== '' && $item['unit_price'] !== null
        ? (float) $item['unit_price']
        : '';
    $taxValue = array_key_exists('tax_rate', $item) && $item['tax_rate'] !== '' && $item['tax_rate'] !== null
        ? (float) $item['tax_rate']
        : ($settings->default_tax_rate ?? 18);
    if ($selectedMentorId && $serviceNameValue === '') {
        $serviceNameValue = optional($mentors->firstWhere('id', $selectedMentorId))->display_name ?? '';
    }
@endphp
<div class="invoice-item-card item-row border rounded p-3 mb-3 bg-white" data-index="{{ $index }}">
    <input type="hidden" name="items[{{ $index }}][sort_order]" class="item-sort" value="{{ $item['sort_order'] ?? $index }}">
    <input type="hidden" name="items[{{ $index }}][service_name]" class="item-service" value="{{ $serviceNameValue }}">
    <input type="hidden" name="items[{{ $index }}][sku]" class="item-sku" value="{{ $item['sku'] ?? '' }}">

    {{-- Row 1: Mentor + Description --}}
    <div class="row g-2 mb-2">
        <div class="col-md-6">
            <label class="small text-muted mb-1 d-block">{{ translate('Mentor') }} *</label>
            <select class="form-control item-mentor-select" aria-label="{{ translate('Select mentor') }}">
                <option value="">{{ translate('Select mentor') }}</option>
                @foreach($mentors as $mentor)
                    <option value="{{ $mentor->id }}"
                        data-name="{{ $mentor->display_name }}"
                        data-username="{{ $mentor->username }}"
                        data-price="{{ $mentor->default_price ?? 0 }}"
                        @selected((string) $selectedMentorId === (string) $mentor->id)>
                        {{ $mentor->display_name }}
                    </option>
                @endforeach
                <option value="custom" @selected($customItem)>{{ translate('Custom item') }}</option>
            </select>
            <input type="text"
                   class="form-control item-service-custom mt-1 {{ $customItem ? '' : 'd-none' }}"
                   value="{{ $customItem ? ($item['service_name'] ?? '') : '' }}"
                   placeholder="{{ translate('Custom item name') }}"
                   autocomplete="off">
        </div>
        <div class="col-md-6">
            <label class="small text-muted mb-1 d-block">{{ translate('Description') }}</label>
            <input type="text" name="items[{{ $index }}][description]" class="form-control item-description" value="{{ $item['description'] ?? '' }}" placeholder="{{ translate('Session details') }}">
        </div>
    </div>

    {{-- Row 2: Sessions, Unit, Rate, Discount, GST --}}
    <div class="row g-2 mb-2">
        <div class="col-6 col-md-2">
            <label class="small text-muted mb-1 d-block">{{ translate('Sessions') }}</label>
            <input type="number" step="1" min="0" max="9999" name="items[{{ $index }}][quantity]" class="form-control item-qty item-sessions" value="{{ old('items.'.$index.'.quantity', $qtyValue) }}" placeholder="0">
        </div>
        <div class="col-6 col-md-2">
            <label class="small text-muted mb-1 d-block">{{ translate('Unit') }}</label>
            <input type="text" name="items[{{ $index }}][unit]" class="form-control item-unit" value="{{ $unitValue }}" placeholder="Session">
        </div>
        <div class="col-6 col-md-2">
            <label class="small text-muted mb-1 d-block">{{ translate('Rate (₹)') }} *</label>
            <input type="number" step="0.01" min="0" max="100000" name="items[{{ $index }}][unit_price]" class="form-control item-rate" value="{{ old('items.'.$index.'.unit_price', $rateValue !== '' ? $rateValue : '') }}" placeholder="0" inputmode="decimal">
        </div>
        <div class="col-6 col-md-2">
            <label class="small text-muted mb-1 d-block">{{ translate('Discount') }}</label>
            <input type="number" step="0.01" min="0" name="items[{{ $index }}][discount]" class="form-control item-discount" value="{{ $item['discount'] ?? 0 }}" placeholder="0">
        </div>
        <div class="col-6 col-md-2">
            <label class="small text-muted mb-1 d-block">{{ translate('Disc type') }}</label>
            <select name="items[{{ $index }}][discount_type]" class="form-control item-discount-type">
                <option value="fixed" @selected(($item['discount_type'] ?? 'fixed')==='fixed')>{{ translate('Fixed') }}</option>
                <option value="percent" @selected(($item['discount_type'] ?? '')==='percent')>%</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="small text-muted mb-1 d-block">{{ translate('GST %') }}</label>
            <input type="number" step="0.01" min="0" max="100" name="items[{{ $index }}][tax_rate]" class="form-control item-tax" value="{{ old('items.'.$index.'.tax_rate', $taxValue) }}" placeholder="0">
        </div>
    </div>

    {{-- Row 3: Line total + actions --}}
    <div class="row g-2 align-items-center border-top pt-2 mt-1">
        <div class="col">
            <span class="text-muted small">{{ translate('Line total') }}:</span>
            <strong class="item-line-total fs-5 ms-1 text-primary">₹0.00</strong>
        </div>
        <div class="col-auto d-flex gap-1">
            <button type="button" class="btn btn-sm btn--secondary btn-dup-item" title="{{ translate('Duplicate') }}">+ {{ translate('Copy') }}</button>
            <button type="button" class="btn btn-sm btn--danger btn-remove-item" title="{{ translate('Remove') }}">× {{ translate('Remove') }}</button>
        </div>
    </div>
</div>
