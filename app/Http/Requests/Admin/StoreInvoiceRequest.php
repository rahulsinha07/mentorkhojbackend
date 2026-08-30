<?php

namespace App\Http\Requests\Admin;

use App\CentralLogics\InvoiceCalculationLogic;
use App\Model\Invoice\InvoiceSetting;
use App\Model\Mentor\Mentor;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return static::baseRules();
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('mentor_snapshot') && is_string($this->mentor_snapshot)) {
            $decoded = json_decode($this->mentor_snapshot, true);
            if (is_array($decoded)) {
                $this->merge(['mentor_snapshot' => $decoded]);
            }
        }

        if ($this->filled('customer_aadhaar')) {
            $this->merge([
                'customer_aadhaar' => preg_replace('/\D/', '', (string) $this->customer_aadhaar),
            ]);
        }

        $items = $this->input('items', []);
        if (is_array($items)) {
            foreach ($items as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }
                if (empty($item['service_name']) && !empty($item['sku']) && ctype_digit((string) $item['sku'])) {
                    $mentor = Mentor::query()->find((int) $item['sku']);
                    if ($mentor) {
                        $items[$index]['service_name'] = $mentor->display_name;
                    }
                }
                if (empty($item['unit'])) {
                    $items[$index]['unit'] = 'Session';
                }
                if (!isset($item['unit_price']) || $item['unit_price'] === '') {
                    $items[$index]['unit_price'] = 0;
                }
            }
            $this->merge(['items' => $items]);
        }

        $taxMode = (string) $this->input('tax_mode', 'none');
        $items = $this->input('items', []);
        if ($taxMode !== 'none' && is_array($items)) {
            $defaultTaxRate = (float) (InvoiceSetting::instance()->default_tax_rate ?? 18);
            $items = InvoiceCalculationLogic::normalizeItemTaxRates($items, $taxMode, $defaultTaxRate);
            $this->merge(['items' => $items]);
        }
    }

    /** @return array<string, mixed> */
    public static function baseRules(): array
    {
        return [
            'action' => 'nullable|in:draft,generate',
            'invoice_number' => 'nullable|string|max:64',
            'invoice_number_manual' => 'nullable|boolean',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'payment_date' => 'nullable|date',
            'currency' => 'required|string|max:8',
            'place_of_supply' => 'nullable|string|max:120',
            'reference_number' => 'nullable|string|max:120',
            'tax_mode' => 'required|in:none,gst,cgst_sgst,igst,custom',
            'additional_charges' => 'nullable|numeric|min:0',
            'amount_paid' => 'nullable|numeric|min:0',
            'payment_status' => 'required|in:paid,partially_paid,pending,cancelled,refunded',
            'payment_method' => 'nullable|in:cash,upi,bank_transfer,credit_card,debit_card,online_payment,other',
            'transaction_id' => 'nullable|string|max:191',
            'customer_notes' => 'nullable|string|max:5000',
            'terms' => 'nullable|string|max:10000',
            'user_id' => 'nullable|integer|exists:users,id',
            'source_type' => 'nullable|string|max:32',
            'source_id' => 'nullable|integer',
            'customer_name' => 'required|string|max:191',
            'customer_type' => 'nullable|string|max:32',
            'customer_company' => 'nullable|string|max:191',
            'customer_email' => 'nullable|email|max:191',
            'customer_phone' => 'nullable|string|max:32',
            'billing_address' => 'nullable|string|max:1000',
            'billing_city' => 'nullable|string|max:120',
            'billing_state' => 'nullable|string|max:120',
            'billing_country' => 'nullable|string|max:120',
            'billing_postal_code' => 'nullable|string|max:16',
            'shipping_address' => 'nullable|string|max:1000',
            'shipping_city' => 'nullable|string|max:120',
            'shipping_state' => 'nullable|string|max:120',
            'shipping_country' => 'nullable|string|max:120',
            'shipping_postal_code' => 'nullable|string|max:16',
            'customer_gstin' => ['nullable', 'string', 'max:20', function ($attr, $value, $fail) {
                if ($value && !InvoiceCalculationLogic::validateGstin($value)) {
                    $fail('Invalid GSTIN format.');
                }
            }],
            'customer_pan' => 'nullable|string|max:16',
            'customer_aadhaar' => ['nullable', 'string', 'regex:/^\d{12}$/'],
            'classes_booked' => 'nullable|integer|min:1',
            'mentor_snapshot' => 'nullable|array',
            'customer_external_id' => 'nullable|string|max:64',
            'items' => 'required|array|min:1',
            'items.*.service_name' => 'required|string|max:191',
            'items.*.description' => 'nullable|string|max:2000',
            'items.*.sku' => 'nullable|string|max:64',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.unit' => 'nullable|string|max:32',
            'items.*.unit_price' => 'required|numeric|min:0.01',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'nullable|in:fixed,percent',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_type' => 'nullable|string|max:32',
            'items.*.sort_order' => 'nullable|integer|min:0',
        ];
    }
}
