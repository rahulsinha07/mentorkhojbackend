<tr class="item-row">
    <td><input type="text" name="items[{{ $index }}][service_name]" class="form-control form-control-sm item-service" required value="{{ $item['service_name'] ?? '' }}"></td>
    <td><input type="text" name="items[{{ $index }}][description]" class="form-control form-control-sm" value="{{ $item['description'] ?? '' }}"></td>
    <td><input type="number" step="0.01" min="0.01" name="items[{{ $index }}][quantity]" class="form-control form-control-sm item-qty" value="{{ $item['quantity'] ?? 1 }}"></td>
    <td><input type="text" name="items[{{ $index }}][unit]" class="form-control form-control-sm" value="{{ $item['unit'] ?? 'Qty' }}"></td>
    <td><input type="number" step="0.01" min="0" name="items[{{ $index }}][unit_price]" class="form-control form-control-sm item-rate" value="{{ $item['unit_price'] ?? 0 }}"></td>
    <td><input type="number" step="0.01" min="0" name="items[{{ $index }}][discount]" class="form-control form-control-sm item-discount" value="{{ $item['discount'] ?? 0 }}"></td>
    <td>
        <select name="items[{{ $index }}][discount_type]" class="form-control form-control-sm item-discount-type">
            <option value="fixed" @selected(($item['discount_type'] ?? 'fixed')==='fixed')>Fixed</option>
            <option value="percent" @selected(($item['discount_type'] ?? '')==='percent')>%</option>
        </select>
    </td>
    <td><input type="number" step="0.01" min="0" max="100" name="items[{{ $index }}][tax_rate]" class="form-control form-control-sm item-tax" value="{{ $item['tax_rate'] ?? 18 }}"></td>
    <td class="item-line-total text-right align-middle">0.00</td>
    <td class="text-nowrap">
        <input type="hidden" name="items[{{ $index }}][sort_order]" class="item-sort" value="{{ $item['sort_order'] ?? $index }}">
        <button type="button" class="btn btn-xs btn--secondary btn-dup-item" title="Duplicate">+</button>
        <button type="button" class="btn btn-xs btn--danger btn-remove-item" title="Remove">×</button>
    </td>
</tr>
