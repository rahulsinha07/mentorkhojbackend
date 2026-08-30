<?php

namespace App\CentralLogics;

use App\Model\Invoice\InvoiceSetting;

class InvoiceCalculationLogic
{
    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    public static function calculate(array $items, array $options = []): array
    {
        $taxMode = (string) ($options['tax_mode'] ?? 'none');
        $defaultTaxRate = (float) ($options['default_tax_rate'] ?? InvoiceSetting::instance()->default_tax_rate ?? 18);
        $items = static::normalizeItemTaxRates($items, $taxMode, $defaultTaxRate);
        $placeOfSupply = trim((string) ($options['place_of_supply'] ?? ''));
        $companyState = trim((string) InvoiceCompanyProfile::get('state', 'Bihar'));
        $additionalCharges = round((float) ($options['additional_charges'] ?? 0), 2);
        $amountPaid = round((float) ($options['amount_paid'] ?? 0), 2);
        $applyRoundOff = (bool) ($options['apply_round_off'] ?? true);

        $computedItems = [];
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxableAmount = 0.0;
        $totalTax = 0.0;

        foreach ($items as $index => $item) {
            $line = static::calculateLine($item, $index, $taxMode);
            $computedItems[] = $line;
            $subtotal += $line['line_subtotal'];
            $discountTotal += $line['line_discount'];
            $taxableAmount += $line['line_taxable'];
            $totalTax += $line['tax_amount'];
        }

        $subtotal = round($subtotal, 2);
        $discountTotal = round($discountTotal, 2);
        $taxableAmount = round($taxableAmount, 2);
        $totalTax = round($totalTax, 2);

        if ($taxMode === 'none') {
            $totalTax = 0.0;
            foreach ($computedItems as &$line) {
                $line['tax_amount'] = 0.0;
                $line['line_total'] = $line['line_taxable'];
            }
            unset($line);
        }

        $cgst = 0.0;
        $sgst = 0.0;
        $igst = 0.0;
        $otherTax = 0.0;

        if ($taxMode === 'igst') {
            $igst = $totalTax;
        } elseif ($taxMode === 'cgst_sgst' || ($taxMode === 'gst' && static::isSameState($placeOfSupply, $companyState))) {
            $cgst = round($totalTax / 2, 2);
            $sgst = round($totalTax - $cgst, 2);
        } elseif ($taxMode === 'gst' && !static::isSameState($placeOfSupply, $companyState)) {
            $igst = $totalTax;
        } elseif ($taxMode === 'custom') {
            $otherTax = $totalTax;
        } elseif ($taxMode !== 'none') {
            $cgst = round($totalTax / 2, 2);
            $sgst = round($totalTax - $cgst, 2);
        }

        $preRoundTotal = round($taxableAmount + $cgst + $sgst + $igst + $otherTax + $additionalCharges, 2);
        $roundOff = 0.0;
        $totalAmount = $preRoundTotal;

        if ($applyRoundOff) {
            $rounded = round($preRoundTotal);
            $roundOff = round($rounded - $preRoundTotal, 2);
            $totalAmount = round($preRoundTotal + $roundOff, 2);
        }

        $balanceDue = round(max(0, $totalAmount - $amountPaid), 2);

        return [
            'items' => $computedItems,
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'taxable_amount' => $taxableAmount,
            'total_tax' => $totalTax,
            'cgst' => $cgst,
            'sgst' => $sgst,
            'igst' => $igst,
            'other_tax' => $otherTax,
            'additional_charges' => $additionalCharges,
            'round_off' => $roundOff,
            'total_amount' => $totalAmount,
            'amount_paid' => $amountPaid,
            'balance_due' => $balanceDue,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    public static function calculateLine(array $item, int $sortOrder = 0, ?string $taxMode = null): array
    {
        $qty = max(0, (float) ($item['quantity'] ?? 1));
        $unitPrice = max(0, (float) ($item['unit_price'] ?? 0));
        $discount = max(0, (float) ($item['discount'] ?? 0));
        $discountType = (string) ($item['discount_type'] ?? 'fixed');
        $taxRate = max(0, (float) ($item['tax_rate'] ?? 0));

        if ($taxMode === 'none') {
            $taxRate = 0.0;
        }

        $lineSubtotal = round($qty * $unitPrice, 2);
        $lineDiscount = $discountType === 'percent'
            ? round(min($lineSubtotal, $lineSubtotal * $discount / 100), 2)
            : round(min($lineSubtotal, $discount), 2);

        $lineTaxable = round(max(0, $lineSubtotal - $lineDiscount), 2);
        $taxAmount = round($lineTaxable * $taxRate / 100, 2);
        $lineTotal = round($lineTaxable + $taxAmount, 2);

        return [
            'sort_order' => (int) ($item['sort_order'] ?? $sortOrder),
            'service_name' => (string) ($item['service_name'] ?? ''),
            'description' => $item['description'] ?? null,
            'sku' => $item['sku'] ?? null,
            'quantity' => $qty,
            'unit' => $item['unit'] ?? null,
            'unit_price' => $unitPrice,
            'discount' => $discount,
            'discount_type' => $discountType,
            'tax_rate' => $taxRate,
            'tax_type' => $item['tax_type'] ?? null,
            'line_subtotal' => $lineSubtotal,
            'line_discount' => $lineDiscount,
            'line_taxable' => $lineTaxable,
            'tax_amount' => $taxAmount,
            'line_total' => $lineTotal,
        ];
    }

    public static function isSameState(string $placeOfSupply, string $companyState): bool
    {
        return strcasecmp(trim($placeOfSupply), trim($companyState)) === 0;
    }

    public static function suggestTaxMode(?string $customerState): string
    {
        $companyState = (string) InvoiceCompanyProfile::get('state', 'Bihar');
        if ($customerState && !static::isSameState($customerState, $companyState)) {
            return 'igst';
        }

        return 'cgst_sgst';
    }

    public static function validateGstin(?string $gstin): bool
    {
        if ($gstin === null || trim($gstin) === '') {
            return true;
        }

        return (bool) preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', strtoupper(trim($gstin)));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeItemTaxRates(array $items, string $taxMode, ?float $defaultRate = null): array
    {
        if ($taxMode === 'none') {
            return $items;
        }

        $defaultRate = $defaultRate ?? (float) (InvoiceSetting::instance()->default_tax_rate ?? 18);

        foreach ($items as $index => $item) {
            if (!isset($item['tax_rate']) || $item['tax_rate'] === '' || $item['tax_rate'] === null) {
                $items[$index]['tax_rate'] = $defaultRate;
            }
        }

        return $items;
    }
}
