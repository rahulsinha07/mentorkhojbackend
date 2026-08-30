(function () {
    'use strict';

    var cfg = window.invoiceFormConfig || {};
    var form = document.getElementById('invoice-form');
    if (!form) return;

    var itemsBody = document.getElementById('items-body');
    var itemIndex = itemsBody ? itemsBody.querySelectorAll('.item-row').length : 0;

    function money(n) {
        return '₹' + (Number(n) || 0).toFixed(2);
    }

    function gatherItems() {
        var rows = itemsBody.querySelectorAll('.item-row');
        var items = [];
        rows.forEach(function (row, idx) {
            items.push({
                sort_order: idx,
                service_name: row.querySelector('.item-service').value,
                description: row.querySelector('[name*="[description]"]').value,
                quantity: parseFloat(row.querySelector('.item-qty').value) || 0,
                unit: row.querySelector('[name*="[unit]"]').value,
                unit_price: parseFloat(row.querySelector('.item-rate').value) || 0,
                discount: parseFloat(row.querySelector('.item-discount').value) || 0,
                discount_type: row.querySelector('.item-discount-type').value,
                tax_rate: parseFloat(row.querySelector('.item-tax').value) || 0,
            });
        });
        return items;
    }

    function recalcLocal() {
        var items = gatherItems();
        var subtotal = 0, discount = 0, taxable = 0, tax = 0;
        items.forEach(function (item) {
            var lineSub = item.quantity * item.unit_price;
            var lineDisc = item.discount_type === 'percent'
                ? Math.min(lineSub, lineSub * item.discount / 100)
                : Math.min(lineSub, item.discount);
            var lineTaxable = Math.max(0, lineSub - lineDisc);
            var lineTax = lineTaxable * item.tax_rate / 100;
            subtotal += lineSub;
            discount += lineDisc;
            taxable += lineTaxable;
            tax += lineTax;
        });
        var additional = parseFloat(document.getElementById('additional_charges').value) || 0;
        var paid = parseFloat(document.getElementById('amount_paid').value) || 0;
        var preRound = taxable + tax + additional;
        var rounded = Math.round(preRound);
        var roundOff = rounded - preRound;
        var total = preRound + roundOff;
        document.getElementById('sum-subtotal').textContent = money(subtotal);
        document.getElementById('sum-discount').textContent = money(discount);
        document.getElementById('sum-taxable').textContent = money(taxable);
        document.getElementById('sum-cgst').textContent = money(tax / 2);
        document.getElementById('sum-sgst').textContent = money(tax / 2);
        document.getElementById('sum-igst').textContent = money(0);
        document.getElementById('sum-other').textContent = money(0);
        document.getElementById('sum-additional').textContent = money(additional);
        document.getElementById('sum-roundoff').textContent = money(roundOff);
        document.getElementById('sum-total').textContent = money(total);
        document.getElementById('sum-paid').textContent = money(paid);
        document.getElementById('sum-balance').textContent = money(Math.max(0, total - paid));

        itemsBody.querySelectorAll('.item-row').forEach(function (row) {
            var qty = parseFloat(row.querySelector('.item-qty').value) || 0;
            var rate = parseFloat(row.querySelector('.item-rate').value) || 0;
            var disc = parseFloat(row.querySelector('.item-discount').value) || 0;
            var discType = row.querySelector('.item-discount-type').value;
            var taxRate = parseFloat(row.querySelector('.item-tax').value) || 0;
            var lineSub = qty * rate;
            var lineDisc = discType === 'percent' ? Math.min(lineSub, lineSub * disc / 100) : Math.min(lineSub, disc);
            var lineTaxable = Math.max(0, lineSub - lineDisc);
            var lineTotal = lineTaxable + lineTaxable * taxRate / 100;
            row.querySelector('.item-line-total').textContent = lineTotal.toFixed(2);
        });
    }

    function bindRowEvents(row) {
        row.querySelectorAll('input,select').forEach(function (el) {
            el.addEventListener('input', recalcLocal);
            el.addEventListener('change', recalcLocal);
        });
        row.querySelector('.btn-remove-item').addEventListener('click', function () {
            if (itemsBody.querySelectorAll('.item-row').length <= 1) return;
            row.remove();
            reindexRows();
            recalcLocal();
        });
        row.querySelector('.btn-dup-item').addEventListener('click', function () {
            var clone = row.cloneNode(true);
            itemsBody.appendChild(clone);
            reindexRows();
            bindRowEvents(clone);
            recalcLocal();
        });
    }

    function reindexRows() {
        itemsBody.querySelectorAll('.item-row').forEach(function (row, idx) {
            row.querySelectorAll('[name]').forEach(function (input) {
                input.name = input.name.replace(/items\[\d+\]/, 'items[' + idx + ']');
            });
            var sort = row.querySelector('.item-sort');
            if (sort) sort.value = idx;
        });
        itemIndex = itemsBody.querySelectorAll('.item-row').length;
    }

    function addRow() {
        var first = itemsBody.querySelector('.item-row');
        if (!first) return;
        var row = first.cloneNode(true);
        row.querySelectorAll('input[type="text"], input[type="number"]').forEach(function (i) {
            if (i.classList.contains('item-service')) i.value = '';
            else if (i.classList.contains('item-qty')) i.value = '1';
            else if (i.classList.contains('item-rate') || i.classList.contains('item-discount')) i.value = '0';
            else if (i.classList.contains('item-tax')) i.value = String(cfg.defaultTaxRate || 18);
            else i.value = '';
        });
        itemsBody.appendChild(row);
        reindexRows();
        bindRowEvents(row);
        recalcLocal();
    }

    document.getElementById('add-item-row').addEventListener('click', addRow);
    itemsBody.querySelectorAll('.item-row').forEach(bindRowEvents);

    ['additional_charges', 'amount_paid', 'tax_mode', 'billing_state', 'place_of_supply'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', recalcLocal);
        if (el) el.addEventListener('change', recalcLocal);
    });

    var shippingSame = document.getElementById('shipping_same');
    if (shippingSame) {
        shippingSame.addEventListener('change', function () {
            document.getElementById('shipping-fields').style.display = this.checked ? 'none' : 'block';
            if (this.checked) copyBillingToShipping();
        });
    }

    function copyBillingToShipping() {
        document.getElementById('shipping_address').value = document.getElementById('billing_address').value;
        document.getElementById('shipping_city').value = document.getElementById('billing_city').value;
        document.getElementById('shipping_state').value = document.getElementById('billing_state').value;
    }

    ['billing_address', 'billing_city', 'billing_state'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', function () {
            if (shippingSame && shippingSame.checked) copyBillingToShipping();
        });
    });

    document.getElementById('btn-prefill-order').addEventListener('click', function () {
        var id = document.getElementById('prefill-order-id').value;
        if (!id) return;
        fetch(cfg.prefillOrderUrl + '/' + id).then(function (r) { return r.json(); }).then(applyPrefill);
    });

    document.getElementById('btn-prefill-booking').addEventListener('click', function () {
        var id = document.getElementById('prefill-booking-id').value;
        if (!id) return;
        fetch(cfg.prefillBookingUrl + '/' + id).then(function (r) { return r.json(); }).then(applyPrefill);
    });

    function applyPrefill(data) {
        if (!data) return;
        var map = {
            customer_name: 'customer_name', customer_email: 'customer_email', customer_phone: 'customer_phone',
            reference_number: 'reference_number', transaction_id: 'transaction_id', place_of_supply: 'place_of_supply',
            user_id: 'user_id'
        };
        Object.keys(map).forEach(function (k) {
            var el = document.getElementById(map[k]) || form.querySelector('[name="' + k + '"]');
            if (el && data[k] != null) el.value = data[k];
        });
        if (data.payment_status) document.getElementById('payment_status').value = data.payment_status;
        if (data.amount_paid != null) document.getElementById('amount_paid').value = data.amount_paid;
        if (data.tax_mode) document.getElementById('tax_mode').value = data.tax_mode;
        if (data.items && data.items.length) {
            itemsBody.innerHTML = '';
            data.items.forEach(function (item, idx) {
                addRowFromData(item, idx);
            });
        }
        recalcLocal();
    }

    function addRowFromData(item, idx) {
        var tpl = document.createElement('tr');
        tpl.className = 'item-row';
        tpl.innerHTML = '<td><input type="text" name="items[' + idx + '][service_name]" class="form-control form-control-sm item-service" required value="' + (item.service_name || '') + '"></td>' +
            '<td><input type="text" name="items[' + idx + '][description]" class="form-control form-control-sm" value="' + (item.description || '') + '"></td>' +
            '<td><input type="number" step="0.01" name="items[' + idx + '][quantity]" class="form-control form-control-sm item-qty" value="' + (item.quantity || 1) + '"></td>' +
            '<td><input type="text" name="items[' + idx + '][unit]" class="form-control form-control-sm" value="' + (item.unit || 'Qty') + '"></td>' +
            '<td><input type="number" step="0.01" name="items[' + idx + '][unit_price]" class="form-control form-control-sm item-rate" value="' + (item.unit_price || 0) + '"></td>' +
            '<td><input type="number" step="0.01" name="items[' + idx + '][discount]" class="form-control form-control-sm item-discount" value="' + (item.discount || 0) + '"></td>' +
            '<td><select name="items[' + idx + '][discount_type]" class="form-control form-control-sm item-discount-type"><option value="fixed">Fixed</option><option value="percent">%</option></select></td>' +
            '<td><input type="number" step="0.01" name="items[' + idx + '][tax_rate]" class="form-control form-control-sm item-tax" value="' + (item.tax_rate || 18) + '"></td>' +
            '<td class="item-line-total text-right align-middle">0.00</td>' +
            '<td><input type="hidden" name="items[' + idx + '][sort_order]" class="item-sort" value="' + idx + '"><button type="button" class="btn btn-xs btn--secondary btn-dup-item">+</button><button type="button" class="btn btn-xs btn--danger btn-remove-item">×</button></td>';
        itemsBody.appendChild(tpl);
        tpl.querySelector('.item-discount-type').value = item.discount_type || 'fixed';
        bindRowEvents(tpl);
    }

    var searchInput = document.getElementById('customer-search');
    var searchResults = document.getElementById('customer-search-results');
    if (searchInput) {
        var timer;
        searchInput.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                var q = searchInput.value.trim();
                if (q.length < 2) { searchResults.innerHTML = ''; return; }
                fetch(cfg.searchUsersUrl + '?q=' + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (users) {
                        searchResults.innerHTML = users.map(function (u) {
                            return '<button type="button" class="list-group-item list-group-item-action" data-user=\'' + JSON.stringify(u) + '\'>' + u.name + ' — ' + (u.email || u.phone || '') + '</button>';
                        }).join('');
                        searchResults.querySelectorAll('[data-user]').forEach(function (btn) {
                            btn.addEventListener('click', function () {
                                var u = JSON.parse(btn.getAttribute('data-user'));
                                document.getElementById('user_id').value = u.id;
                                document.getElementById('customer_name').value = u.name;
                                document.getElementById('customer_email').value = u.email || '';
                                document.getElementById('customer_phone').value = u.phone || '';
                                searchResults.innerHTML = '';
                                searchInput.value = u.name;
                            });
                        });
                    });
            }, 300);
        });
    }

    recalcLocal();
})();
