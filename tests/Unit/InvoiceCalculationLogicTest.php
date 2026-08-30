<?php

namespace Tests\Unit;

use App\CentralLogics\IndianAmountInWords;
use App\CentralLogics\InvoiceCalculationLogic;
use PHPUnit\Framework\TestCase;

class InvoiceCalculationLogicTest extends TestCase
{
    public function test_discount_before_tax_same_state(): void
    {
        $result = InvoiceCalculationLogic::calculate([
            ['service_name' => 'Session', 'quantity' => 1, 'unit_price' => 1000, 'discount' => 100, 'discount_type' => 'fixed', 'tax_rate' => 18],
        ], ['tax_mode' => 'cgst_sgst', 'place_of_supply' => 'Bihar', 'amount_paid' => 1062]);

        $this->assertEquals(1000, $result['subtotal']);
        $this->assertEquals(100, $result['discount_total']);
        $this->assertEquals(900, $result['taxable_amount']);
        $this->assertEquals(81, $result['cgst']);
        $this->assertEquals(81, $result['sgst']);
        $this->assertEquals(1062, $result['total_amount']);
        $this->assertEquals(0, $result['balance_due']);
    }

    public function test_igst_interstate(): void
    {
        $result = InvoiceCalculationLogic::calculate([
            ['service_name' => 'Session', 'quantity' => 1, 'unit_price' => 800, 'discount' => 0, 'discount_type' => 'fixed', 'tax_rate' => 18],
        ], ['tax_mode' => 'igst', 'place_of_supply' => 'Maharashtra']);

        $this->assertEquals(144, $result['igst']);
        $this->assertEquals(944, $result['total_amount']);
    }

    public function test_amount_in_words(): void
    {
        $words = IndianAmountInWords::convert(1062);
        $this->assertStringContainsString('One Thousand Sixty-Two', $words);
        $this->assertStringContainsString('Only', $words);
    }

    public function test_gstin_validation(): void
    {
        $this->assertTrue(InvoiceCalculationLogic::validateGstin('10AALCB4748G1ZU'));
        $this->assertFalse(InvoiceCalculationLogic::validateGstin('INVALID'));
    }
}
