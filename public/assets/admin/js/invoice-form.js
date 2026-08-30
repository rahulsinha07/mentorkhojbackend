(function () {
    'use strict';

    var cfg = window.invoiceFormConfig || {};
    var form = document.getElementById('invoice-form');
    if (!form) return;

    var itemsBody = document.getElementById('items-body');
    var mentors = cfg.mentors || [];

    function escapeHtml(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function setInputValue(idOrName, value) {
        var el = document.getElementById(idOrName) || form.querySelector('[name="' + idOrName + '"]');
        if (el && value != null) el.value = value;
    }

    function money(n) {
        return '₹' + (Number(n) || 0).toFixed(2);
    }

    function setCalcStatus(message, type) {
        var el = document.getElementById('calc-status');
        if (!el) return;
        el.textContent = message || '';
        el.className = 'small ms-1' + (type ? ' text-' + type : ' text-muted');
    }

    function prepareRowsForCalc() {
        syncTaxRatesForMode();
        itemsBody.querySelectorAll('.item-row').forEach(function (row) {
            applyMentorSelection(row, true, true);
        });
    }

    function parseNum(value, fallback) {
        var n = parseFloat(value);
        return isNaN(n) ? (fallback || 0) : n;
    }

    function effectiveTaxRate(rawRate, taxMode) {
        if (taxMode === 'none') {
            return 0;
        }
        var rate = parseNum(rawRate, 0);
        return rate > 0 ? rate : (cfg.defaultTaxRate || 18);
    }

    function syncTaxRatesForMode() {
        if (getTaxMode() === 'none') {
            return;
        }
        itemsBody.querySelectorAll('.item-tax').forEach(function (input) {
            if (parseNum(input.value, 0) <= 0) {
                input.value = cfg.defaultTaxRate || 18;
            }
        });
    }

    function resolveMentorIdFromItem(item) {
        if (item.mentor_id) return String(item.mentor_id);
        if (item.sku && /^\d+$/.test(String(item.sku))) return String(item.sku);
        if (item.sku) {
            var byUsername = mentors.find(function (m) { return m.username === item.sku; });
            if (byUsername) return String(byUsername.id);
        }
        if (item.service_name) {
            var byName = mentors.find(function (m) { return m.name === item.service_name; });
            if (byName) return String(byName.id);
        }
        return '';
    }

    function buildMentorSelect(selectedValue) {
        var select = document.createElement('select');
        select.className = 'form-control form-control-sm item-mentor-select';

        var empty = document.createElement('option');
        empty.value = '';
        empty.textContent = 'Select mentor';
        select.appendChild(empty);

        mentors.forEach(function (m) {
            var opt = document.createElement('option');
            opt.value = String(m.id);
            opt.textContent = m.name;
            opt.setAttribute('data-name', m.name);
            opt.setAttribute('data-username', m.username || '');
            opt.setAttribute('data-price', m.default_price != null ? String(m.default_price) : '0');
            if (String(selectedValue) === String(m.id)) opt.selected = true;
            select.appendChild(opt);
        });

        var custom = document.createElement('option');
        custom.value = 'custom';
        custom.textContent = 'Custom item';
        if (selectedValue === 'custom') custom.selected = true;
        select.appendChild(custom);

        return select;
    }

    function getMentorMeta(mentorId) {
        return mentors.find(function (m) { return String(m.id) === String(mentorId); }) || null;
    }

    function applyMentorSelection(row, keepDescription, forceRate) {
        var select = row.querySelector('.item-mentor-select');
        var serviceInput = row.querySelector('.item-service');
        var customInput = row.querySelector('.item-service-custom');
        var skuInput = row.querySelector('.item-sku');
        var unitInput = row.querySelector('.item-unit');
        var rateInput = row.querySelector('.item-rate');
        var descInput = row.querySelector('.item-description');
        if (!select || !serviceInput) return;

        var val = select.value;
        var siteUrl = cfg.mentorkhojSiteUrl || 'https://www.mentorkhoj.com';

        if (val === 'custom') {
            if (customInput) customInput.classList.remove('d-none');
            serviceInput.value = customInput ? customInput.value.trim() : '';
            if (skuInput) skuInput.value = '';
            return;
        }

        if (customInput) {
            customInput.classList.add('d-none');
            if (val !== 'custom') customInput.value = '';
        }

        if (val === '') {
            serviceInput.value = '';
            if (skuInput) skuInput.value = '';
            return;
        }

        var opt = select.selectedOptions[0];
        var mentorName = opt ? (opt.getAttribute('data-name') || opt.textContent) : '';
        var username = opt ? opt.getAttribute('data-username') : '';
        var meta = getMentorMeta(val);
        var defaultPrice = meta ? parseNum(meta.default_price, 0) : parseNum(opt ? opt.getAttribute('data-price') : 0, 0);

        serviceInput.value = mentorName;
        if (skuInput) skuInput.value = val;
        if (unitInput && !unitInput.value.trim()) unitInput.value = 'Session';

        if (rateInput && (forceRate || parseNum(rateInput.value, 0) <= 0) && defaultPrice > 0) {
            rateInput.value = defaultPrice;
        }

        if (descInput && !keepDescription) {
            var parts = [];
            if (meta && meta.service_title) parts.push(meta.service_title);
            if (username) parts.push('Profile: ' + siteUrl + '/mentor/' + username);
            if (parts.length) descInput.value = parts.join(' | ');
        }
    }

    function syncCustomServiceName(row) {
        var customInput = row.querySelector('.item-service-custom');
        var serviceInput = row.querySelector('.item-service');
        var select = row.querySelector('.item-mentor-select');
        if (!serviceInput || !select || select.value !== 'custom') return;
        if (customInput) serviceInput.value = customInput.value.trim();
    }

    function readRowData(row) {
        syncCustomServiceName(row);
        applyMentorSelection(row, true, false);
        var select = row.querySelector('.item-mentor-select');
        var taxMode = getTaxMode();
        return {
            mentor_id: select ? select.value : '',
            service_name: row.querySelector('.item-service') ? row.querySelector('.item-service').value : '',
            description: row.querySelector('.item-description') ? row.querySelector('.item-description').value : '',
            sku: row.querySelector('.item-sku') ? row.querySelector('.item-sku').value : '',
            quantity: parseInt(row.querySelector('.item-qty').value, 10) || 1,
            unit: row.querySelector('.item-unit').value || 'Session',
            unit_price: parseNum(row.querySelector('.item-rate').value, 0),
            discount: parseNum(row.querySelector('.item-discount').value, 0),
            discount_type: row.querySelector('.item-discount-type').value,
            tax_rate: effectiveTaxRate(row.querySelector('.item-tax').value, taxMode),
        };
    }

    function buildCalculateFormData() {
        var formData = new FormData();
        formData.append('_token', csrfToken());
        formData.append('tax_mode', getTaxMode());
        formData.append('place_of_supply', document.getElementById('place_of_supply') ? document.getElementById('place_of_supply').value : '');
        formData.append('additional_charges', String(parseNum(document.getElementById('additional_charges').value, 0)));
        formData.append('amount_paid', String(parseNum(document.getElementById('amount_paid').value, 0)));
        var items = gatherItems();
        items.forEach(function (item, index) {
            Object.keys(item).forEach(function (key) {
                formData.append('items[' + index + '][' + key + ']', item[key] != null ? item[key] : '');
            });
        });
        return formData;
    }

    function gatherItems() {
        var rows = itemsBody.querySelectorAll('.item-row');
        var items = [];
        rows.forEach(function (row, idx) {
            var data = readRowData(row);
            items.push({
                sort_order: idx,
                service_name: data.service_name,
                description: data.description,
                sku: data.sku,
                quantity: data.quantity,
                unit: data.unit,
                unit_price: data.unit_price,
                discount: data.discount,
                discount_type: data.discount_type,
                tax_rate: data.tax_rate,
            });
        });
        return items;
    }

    function getTaxMode() {
        var el = document.getElementById('tax_mode');
        return el ? el.value : 'none';
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function calculateLineLocal(item, taxMode) {
        var qty = item.quantity || 0;
        var rate = item.unit_price || 0;
        var disc = item.discount || 0;
        var discType = item.discount_type || 'fixed';
        var taxRate = effectiveTaxRate(item.tax_rate, taxMode);
        var lineSub = qty * rate;
        var lineDisc = discType === 'percent'
            ? Math.min(lineSub, lineSub * disc / 100)
            : Math.min(lineSub, disc);
        var lineTaxable = Math.max(0, lineSub - lineDisc);
        var lineTax = lineTaxable * taxRate / 100;
        return {
            line_subtotal: lineSub,
            line_discount: lineDisc,
            line_taxable: lineTaxable,
            tax_amount: lineTax,
            line_total: lineTaxable + lineTax,
        };
    }

    function applySummaryResult(result) {
        var taxMode = getTaxMode();
        var totalTax = result.total_tax != null
            ? result.total_tax
            : (result.cgst || 0) + (result.sgst || 0) + (result.igst || 0) + (result.other_tax || 0);
        var paid = parseNum(document.getElementById('amount_paid').value, 0);

        document.getElementById('sum-subtotal').textContent = money(result.subtotal);
        document.getElementById('sum-discount').textContent = money(result.discount_total);
        document.getElementById('sum-taxable').textContent = money(result.taxable_amount);
        document.getElementById('sum-gst').textContent = money(totalTax);
        document.getElementById('sum-cgst').textContent = money(result.cgst || 0);
        document.getElementById('sum-sgst').textContent = money(result.sgst || 0);
        document.getElementById('sum-igst').textContent = money(result.igst || 0);
        document.getElementById('sum-other').textContent = money(result.other_tax || 0);
        document.getElementById('sum-additional').textContent = money(result.additional_charges || 0);
        document.getElementById('sum-roundoff').textContent = money(result.round_off || 0);
        document.getElementById('sum-total').textContent = money(result.total_amount);
        document.getElementById('sum-paid').textContent = money(paid);
        document.getElementById('sum-balance').textContent = money(Math.max(0, (result.total_amount || 0) - paid));

        document.getElementById('sum-discount-row').style.display = (result.discount_total || 0) > 0 ? '' : 'none';

        var showGst = taxMode !== 'none' && totalTax > 0;
        document.getElementById('sum-gst-row').style.display = showGst ? '' : 'none';
        document.getElementById('sum-cgst-row').style.display = (taxMode === 'cgst_sgst' || taxMode === 'gst') && (result.cgst || 0) > 0 ? '' : 'none';
        document.getElementById('sum-sgst-row').style.display = (taxMode === 'cgst_sgst' || taxMode === 'gst') && (result.sgst || 0) > 0 ? '' : 'none';
        document.getElementById('sum-igst-row').style.display = (taxMode === 'igst' || ((result.igst || 0) > 0 && taxMode === 'gst')) ? '' : 'none';
        document.getElementById('sum-other-row').style.display = taxMode === 'custom' && (result.other_tax || 0) > 0 ? '' : 'none';
        document.getElementById('tax-mode-hint').style.display = (taxMode === 'cgst_sgst' || taxMode === 'gst') && totalTax > 0 ? '' : 'none';

        if (result.items && result.items.length) {
            itemsBody.querySelectorAll('.item-row').forEach(function (row, idx) {
                var line = result.items[idx];
                if (line) {
                    row.querySelector('.item-line-total').textContent = money(line.line_total || 0);
                    var taxInput = row.querySelector('.item-tax');
                    if (taxInput && getTaxMode() !== 'none' && parseNum(taxInput.value, 0) <= 0 && parseNum(line.tax_rate, 0) > 0) {
                        taxInput.value = line.tax_rate;
                    }
                }
            });
        }
    }

    function recalcLocalFallback() {
        var taxMode = getTaxMode();
        var items = gatherItems();
        var subtotal = 0, discount = 0, taxable = 0, totalTax = 0;
        var computedItems = [];
        items.forEach(function (item, idx) {
            var line = calculateLineLocal(item, taxMode);
            computedItems.push(Object.assign({ sort_order: idx }, item, line));
            subtotal += line.line_subtotal;
            discount += line.line_discount;
            taxable += line.line_taxable;
            totalTax += line.tax_amount;
        });
        var additional = parseNum(document.getElementById('additional_charges').value, 0);
        var cgst = 0, sgst = 0, igst = 0, other = 0;
        var place = document.getElementById('place_of_supply') ? document.getElementById('place_of_supply').value : '';
        var sameState = place.toLowerCase() === String(cfg.companyState || 'Bihar').toLowerCase();
        if (taxMode === 'none') {
            totalTax = 0;
        } else if (taxMode === 'igst' || (taxMode === 'gst' && !sameState)) {
            igst = totalTax;
        } else if (taxMode === 'cgst_sgst' || (taxMode === 'gst' && sameState)) {
            cgst = totalTax / 2;
            sgst = totalTax - cgst;
        } else if (taxMode === 'custom') {
            other = totalTax;
        } else {
            cgst = totalTax / 2;
            sgst = totalTax - cgst;
        }
        var preRound = taxable + cgst + sgst + igst + other + additional;
        var rounded = Math.round(preRound);
        applySummaryResult({
            items: computedItems,
            subtotal: subtotal,
            discount_total: discount,
            taxable_amount: taxable,
            total_tax: totalTax,
            cgst: cgst,
            sgst: sgst,
            igst: igst,
            other_tax: other,
            additional_charges: additional,
            round_off: rounded - preRound,
            total_amount: preRound + (rounded - preRound),
        });
        setCalcStatus('Totals updated', 'success');
    }

    function recalcNow() {
        clearTimeout(recalcTimer);
        prepareRowsForCalc();
        setCalcStatus('Calculating…', '');

        if (!cfg.calculateUrl) {
            recalcLocalFallback();
            return;
        }

        fetch(cfg.calculateUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: buildCalculateFormData(),
        })
            .then(function (r) {
                if (!r.ok) {
                    return r.json().catch(function () { return {}; }).then(function (body) {
                        var msg = body.message || 'Could not calculate totals';
                        throw new Error(msg);
                    });
                }
                return r.json();
            })
            .then(function (result) {
                applySummaryResult(result);
                setCalcStatus('Totals calculated', 'success');
            })
            .catch(function (err) {
                recalcLocalFallback();
                setCalcStatus(err.message || 'Calculated locally', 'danger');
            });
    }

    var recalcTimer;
    function recalcLocal() {
        clearTimeout(recalcTimer);
        recalcTimer = setTimeout(function () {
            prepareRowsForCalc();
            if (!cfg.calculateUrl) {
                recalcLocalFallback();
                return;
            }
            var payload = buildCalculateFormData();
            fetch(cfg.calculateUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: payload,
            })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                .then(function (result) {
                    applySummaryResult(result);
                    setCalcStatus('', '');
                })
                .catch(recalcLocalFallback);
        }, 120);
    }

    function recalcLocalImmediate() {
        recalcNow();
    }

    function resetRow(row) {
        var select = row.querySelector('.item-mentor-select');
        if (select) select.value = '';
        row.querySelectorAll('input[type="text"], input[type="number"], textarea').forEach(function (input) {
            if (input.classList.contains('item-sort')) return;
            if (input.classList.contains('item-service')) {
                input.value = '';
                return;
            }
            if (input.classList.contains('item-sku')) {
                input.value = '';
                return;
            }
            if (input.classList.contains('item-qty')) {
                input.value = '1';
                return;
            }
            if (input.classList.contains('item-unit')) {
                input.value = 'Session';
                return;
            }
            if (input.classList.contains('item-discount')) {
                input.value = '0';
                return;
            }
            if (input.classList.contains('item-tax')) {
                input.value = String(cfg.defaultTaxRate || 18);
                return;
            }
            input.value = '';
        });
        var customInput = row.querySelector('.item-service-custom');
        if (customInput) {
            customInput.value = '';
            customInput.classList.add('d-none');
        }
        var discType = row.querySelector('.item-discount-type');
        if (discType) discType.value = 'fixed';
        row.querySelector('.item-line-total').textContent = money(0);
    }

    function bindRowActions(row) {
        var removeBtn = row.querySelector('.btn-remove-item');
        var dupBtn = row.querySelector('.btn-dup-item');
        if (removeBtn) {
            removeBtn.onclick = function () {
                if (itemsBody.querySelectorAll('.item-row').length <= 1) {
                    resetRow(row);
                } else {
                    row.remove();
                    reindexRows();
                }
                recalcLocal();
            };
        }
        if (dupBtn) {
            dupBtn.onclick = function () {
                var data = readRowData(row);
                addRowFromData(data, itemsBody.querySelectorAll('.item-row').length);
                recalcLocal();
            };
        }
    }

    function reindexRows() {
        itemsBody.querySelectorAll('.item-row').forEach(function (row, idx) {
            row.querySelectorAll('[name]').forEach(function (input) {
                input.name = input.name.replace(/items\[\d+\]/, 'items[' + idx + ']');
            });
            var sort = row.querySelector('.item-sort');
            if (sort) sort.value = idx;
        });
    }

    function addRow() {
        addRowFromData({
            quantity: 1,
            unit: 'Session',
            unit_price: 0,
            discount: 0,
            discount_type: 'fixed',
            tax_rate: cfg.defaultTaxRate || 18,
        }, itemsBody.querySelectorAll('.item-row').length);
        recalcLocal();
    }

    itemsBody.addEventListener('input', function (e) {
        if (!e.target.closest('.item-row')) return;
        if (e.target.classList.contains('item-service-custom')) {
            syncCustomServiceName(e.target.closest('.item-row'));
        }
        recalcLocal();
    });

    itemsBody.addEventListener('change', function (e) {
        var row = e.target.closest('.item-row');
        if (!row) return;
        if (e.target.classList.contains('item-mentor-select')) {
            applyMentorSelection(row, false, true);
        }
        recalcLocal();
    });

    document.querySelectorAll('.btn-add-item-row').forEach(function (btn) {
        btn.addEventListener('click', addRow);
    });

    document.querySelectorAll('.btn-calculate-items').forEach(function (btn) {
        btn.addEventListener('click', recalcNow);
    });

    itemsBody.querySelectorAll('.item-row').forEach(function (row) {
        bindRowActions(row);
        applyMentorSelection(row, true, true);
    });

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

    function showPrefillStatus(msg, isError) {
        var el = document.getElementById('prefill-demo-status');
        if (!el) return;
        el.textContent = msg || '';
        el.className = 'small ' + (isError ? 'text-danger' : 'text-success');
    }

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

    document.getElementById('btn-prefill-demo').addEventListener('click', function () {
        var ref = document.getElementById('prefill-demo-ref').value.trim();
        if (!ref) return;
        showPrefillStatus('Loading…', false);
        fetch(cfg.prefillDemoUrl + '/' + encodeURIComponent(ref))
            .then(function (r) {
                if (!r.ok) throw new Error('Demo not found');
                return r.json();
            })
            .then(function (data) {
                applyPrefill(data);
                showPrefillStatus('Demo loaded: ' + (data.reference_number || ref), false);
            })
            .catch(function (err) {
                showPrefillStatus(err.message || 'Demo booking not found', true);
            });
    });

    function renderDemoDetailsCard(demo, mentorList) {
        var card = document.getElementById('demo-details-card');
        var body = document.getElementById('demo-details-body');
        if (!card || !body || !demo) {
            if (card) card.style.display = 'none';
            return;
        }
        var subjects = Array.isArray(demo.subjects) ? demo.subjects.join(', ') : (demo.subjects || '—');
        var mentorNames = (mentorList || []).map(function (m) { return m.name; }).join(', ') || '—';
        body.innerHTML =
            '<div class="col-md-6"><strong>Name:</strong> ' + escapeHtml(demo.name || '—') + '</div>' +
            '<div class="col-md-6"><strong>Ref:</strong> ' + escapeHtml(demo.booking_ref || '—') + '</div>' +
            '<div class="col-md-6"><strong>Phone:</strong> ' + escapeHtml(demo.phone || '—') + '</div>' +
            '<div class="col-md-6"><strong>Email:</strong> ' + escapeHtml(demo.email || '—') + '</div>' +
            '<div class="col-md-6"><strong>Program:</strong> ' + escapeHtml(demo.program || demo.category_label || '—') + '</div>' +
            '<div class="col-md-6"><strong>Stage:</strong> ' + escapeHtml(demo.stage || '—') + '</div>' +
            '<div class="col-md-6"><strong>Subjects:</strong> ' + escapeHtml(subjects) + '</div>' +
            '<div class="col-md-6"><strong>Mentors:</strong> ' + escapeHtml(mentorNames) + '</div>';
        card.style.display = 'block';
    }

    function applyPrefill(data) {
        if (!data) return;
        var map = {
            customer_name: 'customer_name', customer_email: 'customer_email', customer_phone: 'customer_phone',
            reference_number: 'reference_number', transaction_id: 'transaction_id', place_of_supply: 'place_of_supply',
            user_id: 'user_id', customer_type: 'customer_type', customer_external_id: 'customer_external_id',
            billing_address: 'billing_address', billing_city: 'billing_city', billing_state: 'billing_state',
            billing_country: 'billing_country', billing_postal_code: 'billing_postal_code',
            customer_notes: 'customer_notes', source_type: 'source_type', source_id: 'source_id',
            customer_aadhaar: 'customer_aadhaar', classes_booked: 'classes_booked',
        };
        Object.keys(map).forEach(function (k) {
            setInputValue(map[k], data[k]);
        });
        if (data.payment_status) document.getElementById('payment_status').value = data.payment_status;
        if (data.amount_paid != null) document.getElementById('amount_paid').value = data.amount_paid;
        if (data.tax_mode) {
            document.getElementById('tax_mode').value = data.tax_mode;
        }
        syncTaxRatesForMode();

        var snapshot = data.mentor_snapshot || data.mentors || [];
        var snapshotEl = document.getElementById('mentor_snapshot');
        if (snapshotEl && snapshot.length) {
            snapshotEl.value = JSON.stringify(snapshot);
        }

        if (data.demo_booking) {
            renderDemoDetailsCard(data.demo_booking, snapshot);
            if (data.demo_booking.booking_ref) {
                setInputValue('prefill-demo-ref', data.demo_booking.booking_ref);
            }
        }

        if (data.items && data.items.length) {
            itemsBody.innerHTML = '';
            data.items.forEach(function (item, idx) {
                addRowFromData(item, idx);
            });
        }
        recalcLocal();
    }

    function addRowFromData(item, idx) {
        var row = document.createElement('tr');
        row.className = 'item-row';

        var mentorId = resolveMentorIdFromItem(item);
        var useCustom = !mentorId && item.service_name;

        var mentorTd = document.createElement('td');
        mentorTd.className = 'col-mentor';
        var select = buildMentorSelect(useCustom ? 'custom' : mentorId);
        mentorTd.appendChild(select);

        var serviceInput = document.createElement('input');
        serviceInput.type = 'hidden';
        serviceInput.name = 'items[' + idx + '][service_name]';
        serviceInput.className = 'item-service';
        serviceInput.value = item.service_name || '';
        mentorTd.appendChild(serviceInput);

        var customInput = document.createElement('input');
        customInput.type = 'text';
        customInput.className = 'form-control form-control-sm item-service-custom mt-1' + (useCustom ? '' : ' d-none');
        customInput.value = useCustom ? (item.service_name || '') : '';
        customInput.placeholder = 'Custom item name';
        customInput.autocomplete = 'off';
        mentorTd.appendChild(customInput);

        var skuInput = document.createElement('input');
        skuInput.type = 'hidden';
        skuInput.name = 'items[' + idx + '][sku]';
        skuInput.className = 'item-sku';
        skuInput.value = item.sku || mentorId || '';
        mentorTd.appendChild(skuInput);
        row.appendChild(mentorTd);

        function cellInput(attrs, className, value, colClass) {
            var td = document.createElement('td');
            if (colClass) td.className = colClass;
            var input = document.createElement('input');
            Object.keys(attrs).forEach(function (k) { input.setAttribute(k, attrs[k]); });
            input.className = className;
            input.value = value != null && value !== '' ? value : '';
            td.appendChild(input);
            return td;
        }

        row.appendChild(cellInput({
            type: 'text', name: 'items[' + idx + '][description]'
        }, 'form-control form-control-sm item-description', item.description || '', 'col-desc'));

        row.appendChild(cellInput({
            type: 'number', step: '1', min: '1', name: 'items[' + idx + '][quantity]'
        }, 'form-control form-control-sm item-qty item-sessions', parseInt(item.quantity, 10) || 1, 'col-sessions'));

        row.appendChild(cellInput({
            type: 'text', name: 'items[' + idx + '][unit]'
        }, 'form-control form-control-sm item-unit', item.unit || 'Session', 'col-unit'));

        row.appendChild(cellInput({
            type: 'number', step: '0.01', min: '0', name: 'items[' + idx + '][unit_price]'
        }, 'form-control form-control-sm item-rate', item.unit_price != null && item.unit_price !== '' ? item.unit_price : '', 'col-rate'));

        row.appendChild(cellInput({
            type: 'number', step: '0.01', min: '0', name: 'items[' + idx + '][discount]'
        }, 'form-control form-control-sm item-discount', item.discount || 0, 'col-disc'));

        var discTd = document.createElement('td');
        discTd.className = 'col-disc-type';
        var discSelect = document.createElement('select');
        discSelect.name = 'items[' + idx + '][discount_type]';
        discSelect.className = 'form-control form-control-sm item-discount-type';
        ['fixed', 'percent'].forEach(function (v) {
            var opt = document.createElement('option');
            opt.value = v;
            opt.textContent = v === 'fixed' ? 'Fixed' : '%';
            discSelect.appendChild(opt);
        });
        discSelect.value = item.discount_type || 'fixed';
        discTd.appendChild(discSelect);
        row.appendChild(discTd);

        row.appendChild(cellInput({
            type: 'number', step: '0.01', min: '0', max: '100', name: 'items[' + idx + '][tax_rate]'
        }, 'form-control form-control-sm item-tax', item.tax_rate != null && parseNum(item.tax_rate, 0) > 0 ? item.tax_rate : (cfg.defaultTaxRate || 18), 'col-tax'));

        var totalTd = document.createElement('td');
        totalTd.className = 'item-line-total text-right align-middle col-total';
        totalTd.textContent = money(0);
        row.appendChild(totalTd);

        var actionTd = document.createElement('td');
        actionTd.className = 'text-nowrap col-actions';
        var sortInput = document.createElement('input');
        sortInput.type = 'hidden';
        sortInput.name = 'items[' + idx + '][sort_order]';
        sortInput.className = 'item-sort';
        sortInput.value = idx;
        actionTd.appendChild(sortInput);

        var dupBtn = document.createElement('button');
        dupBtn.type = 'button';
        dupBtn.className = 'btn btn-xs btn--secondary btn-dup-item btn-row-action';
        dupBtn.title = 'Duplicate row';
        dupBtn.textContent = '+';
        actionTd.appendChild(dupBtn);

        var rmBtn = document.createElement('button');
        rmBtn.type = 'button';
        rmBtn.className = 'btn btn-xs btn--danger btn-remove-item btn-row-action';
        rmBtn.title = 'Remove row';
        rmBtn.textContent = '×';
        actionTd.appendChild(rmBtn);
        row.appendChild(actionTd);

        itemsBody.appendChild(row);
        bindRowActions(row);
        applyMentorSelection(row, true, true);
    }

    form.addEventListener('submit', function (e) {
        var invalid = false;
        var messages = [];
        itemsBody.querySelectorAll('.item-row').forEach(function (row) {
            syncCustomServiceName(row);
            applyMentorSelection(row, true, false);
            var select = row.querySelector('.item-mentor-select');
            var serviceInput = row.querySelector('.item-service');
            var customInput = row.querySelector('.item-service-custom');
            var rateInput = row.querySelector('.item-rate');
            if (select && select.value === '') {
                invalid = true;
                select.classList.add('is-invalid');
                messages.push('Select a mentor on each row.');
            } else if (select) {
                select.classList.remove('is-invalid');
            }
            if (select && select.value === 'custom' && customInput && !customInput.value.trim()) {
                invalid = true;
                customInput.classList.add('is-invalid');
                messages.push('Enter a custom item name.');
            } else if (customInput) {
                customInput.classList.remove('is-invalid');
            }
            if (serviceInput && !serviceInput.value.trim()) {
                invalid = true;
                messages.push('Item name missing — re-select the mentor.');
            }
            if (rateInput && parseNum(rateInput.value, 0) <= 0) {
                invalid = true;
                rateInput.classList.add('is-invalid');
                messages.push('Enter rate (₹) per session on each row.');
            } else if (rateInput) {
                rateInput.classList.remove('is-invalid');
            }
        });
        if (invalid) {
            e.preventDefault();
            alert(messages.filter(function (m, i, arr) { return arr.indexOf(m) === i; }).join('\n'));
        }
    });

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
                            return '<button type="button" class="list-group-item list-group-item-action" data-user=\'' + JSON.stringify(u).replace(/'/g, '&#39;') + '\'>' + escapeHtml(u.name) + ' — ' + escapeHtml(u.email || u.phone || '') + '</button>';
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

    recalcNow();

    if (cfg.autoDemoRef) {
        document.getElementById('btn-prefill-demo').click();
    }
})();
