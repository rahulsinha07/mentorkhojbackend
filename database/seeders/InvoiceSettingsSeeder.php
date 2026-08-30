<?php

namespace Database\Seeders;

use App\Model\Invoice\InvoiceSetting;
use Illuminate\Database\Seeder;

class InvoiceSettingsSeeder extends Seeder
{
    public function run(): void
    {
        if (InvoiceSetting::query()->exists()) {
            return;
        }

        InvoiceSetting::query()->create([
            'invoice_prefix' => 'MK-INV',
            'number_padding' => 6,
            'next_sequence' => 1,
            'default_currency' => 'INR',
            'default_tax_mode' => 'cgst_sgst',
            'default_tax_rate' => 18,
            'brand_color' => '#107980',
            'default_payment_terms_days' => 0,
            'default_notes' => 'Thank you for choosing Mentorkhoj.',
            'default_terms' => implode("\n", [
                'Payment once made is subject to the applicable cancellation/refund policy.',
                'Please retain this invoice for your records.',
                'For support, contact Mentorkhoj at mentorkhoj@gmail.com or +91 9102 695888.',
            ]),
            'footer_text' => 'Mentorkhoj — A brand of BetterWits Software Private Limited',
        ]);
    }
}
