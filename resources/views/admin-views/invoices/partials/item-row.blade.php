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
    if ($selectedMentorId && $serviceNameValue === '') {
        $serviceNameValue = optional($mentors->firstWhere('id', $selectedMentorId))->display_name ?? '';
    }
@endphp
<tr class="item-row">
    <td class="col-mentor">
        <select class="form-control form-control-sm item-mentor-select" aria-label="{{ translate('Select mentor') }}">
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
        <input type="hidden"
               name="items[{{ $index }}][service_name]"
               class="item-service"
               value="{{ $serviceNameValue }}">
        <input type="text"
               class="form-control form-control-sm item-service-custom mt-1 {{ $customItem ? '' : 'd-none' }}"
               value="{{ $customItem ? ($item['service_name'] ?? '') : '' }}"
               placeholder="{{ translate('Custom item name') }}"
               autocomplete="off">
        <input type="hidden" name="items[{{ $index }}][sku]" class="item-sku" value="{{ $item['sku'] ?? '' }}">
    </td>
    <td class="col-desc"><input type="text" name="items[{{ $index }}][description]" class="form-control form-control-sm item-description" value="{{ $item['description'] ?? '' }}"></td>
    <td class="col-sessions"><input type="number" step="1" min="1" name="items[{{ $index }}][quantity]" class="form-control form-control-sm item-qty item-sessions" value="{{ (int) ($item['quantity'] ?? 1) }}" title="{{ translate('Number of sessions') }}"></td>
    <td class="col-unit"><input type="text" name="items[{{ $index }}][unit]" class="form-control form-control-sm item-unit" value="{{ $unitValue }}" placeholder="Session"></td>
    <td class="col-rate"><input type="number" step="0.01" min="0" name="items[{{ $index }}][unit_price]" class="form-control form-control-sm item-rate" value="{{ old('items.'.$index.'.unit_price', $item['unit_price'] ?? '') }}" placeholder="250" inputmode="decimal"></td>
    <td class="col-disc"><input type="number" step="0.01" min="0" name="items[{{ $index }}][discount]" class="form-control form-control-sm item-discount" value="{{ $item['discount'] ?? 0 }}"></td>
    <td class="col-disc-type">
        <select name="items[{{ $index }}][discount_type]" class="form-control form-control-sm item-discount-type">
            <option value="fixed" @selected(($item['discount_type'] ?? 'fixed')==='fixed')>Fixed</option>
            <option value="percent" @selected(($item['discount_type'] ?? '')==='percent')>%</option>
        </select>
    </td>
    <td class="col-tax"><input type="number" step="0.01" min="0" max="100" name="items[{{ $index }}][tax_rate]" class="form-control form-control-sm item-tax" value="{{ isset($item['tax_rate']) && (float) $item['tax_rate'] > 0 ? $item['tax_rate'] : ($settings->default_tax_rate ?? 18) }}"></td>
    <td class="item-line-total text-right align-middle col-total">₹0.00</td>
    <td class="text-nowrap col-actions">
        <input type="hidden" name="items[{{ $index }}][sort_order]" class="item-sort" value="{{ $item['sort_order'] ?? $index }}">
        <button type="button" class="btn btn-xs btn--secondary btn-dup-item btn-row-action" title="{{ translate('Duplicate row') }}">+</button>
        <button type="button" class="btn btn-xs btn--danger btn-remove-item btn-row-action" title="{{ translate('Remove row') }}">×</button>
    </td>
</tr>
