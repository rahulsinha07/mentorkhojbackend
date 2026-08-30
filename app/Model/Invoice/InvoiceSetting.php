<?php

namespace App\Model\Invoice;

use Illuminate\Database\Eloquent\Model;

class InvoiceSetting extends Model
{
    protected $table = 'invoice_settings';

    protected $fillable = [
        'logo',
        'bank_name',
        'account_name',
        'account_number',
        'ifsc',
        'bank_branch',
        'upi_id',
        'footer_text',
        'default_terms',
        'default_notes',
        'invoice_prefix',
        'number_padding',
        'next_sequence',
        'default_currency',
        'default_tax_mode',
        'default_tax_rate',
        'brand_color',
        'default_payment_terms_days',
    ];

    protected $casts = [
        'number_padding' => 'integer',
        'next_sequence' => 'integer',
        'default_tax_rate' => 'float',
        'default_payment_terms_days' => 'integer',
    ];

    public static function instance(): self
    {
        $row = static::query()->first();
        if ($row) {
            return $row;
        }

        return static::query()->create([
            'invoice_prefix' => 'MK-INV',
            'number_padding' => 6,
            'next_sequence' => 1,
            'default_currency' => 'INR',
            'default_tax_mode' => 'cgst_sgst',
            'default_tax_rate' => 18,
            'brand_color' => '#107980',
        ]);
    }
}
