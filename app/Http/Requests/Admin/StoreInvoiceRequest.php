<?php

namespace App\Http\Requests\Admin;

use App\CentralLogics\InvoiceCalculationLogic;
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
            'customer_external_id' => 'nullable|string|max:64',
            'items' => 'required|array|min:1',
            'items.*.service_name' => 'required|string|max:191',
            'items.*.description' => 'nullable|string|max:2000',
            'items.*.sku' => 'nullable|string|max:64',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.unit' => 'nullable|string|max:32',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'nullable|in:fixed,percent',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_type' => 'nullable|string|max:32',
            'items.*.sort_order' => 'nullable|integer|min:0',
        ];
    }
}
