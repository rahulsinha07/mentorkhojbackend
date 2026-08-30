<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'logo' => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
            'bank_name' => 'nullable|string|max:191',
            'account_name' => 'nullable|string|max:191',
            'account_number' => 'nullable|string|max:64',
            'ifsc' => 'nullable|string|max:32',
            'bank_branch' => 'nullable|string|max:191',
            'upi_id' => 'nullable|string|max:120',
            'footer_text' => 'nullable|string|max:2000',
            'default_terms' => 'nullable|string|max:10000',
            'default_notes' => 'nullable|string|max:5000',
            'invoice_prefix' => 'required|string|max:32',
            'number_padding' => 'required|integer|min:3|max:10',
            'next_sequence' => 'required|integer|min:1',
            'default_currency' => 'required|string|max:8',
            'default_tax_mode' => 'required|in:none,gst,cgst_sgst,igst,custom',
            'default_tax_rate' => 'required|numeric|min:0|max:100',
            'brand_color' => 'nullable|string|max:16',
            'default_payment_terms_days' => 'nullable|integer|min:0|max:365',
        ];
    }
}
