<?php

namespace App\CentralLogics;

use App\Model\Invoice\Invoice;
use App\Model\Invoice\InvoiceSetting;
use Illuminate\Support\Facades\DB;

class InvoiceNumberLogic
{
    public static function previewNext(?InvoiceSetting $settings = null): string
    {
        $settings = $settings ?? InvoiceSetting::instance();

        return static::formatNumber(
            (string) $settings->invoice_prefix,
            (int) $settings->next_sequence,
            (int) $settings->number_padding
        );
    }

    public static function assign(?string $manualNumber = null, bool $isManual = false): string
    {
        if ($isManual && $manualNumber) {
            $number = strtoupper(trim($manualNumber));
            if (Invoice::withTrashed()->where('invoice_number', $number)->exists()) {
                throw new \InvalidArgumentException('Invoice number already exists: ' . $number);
            }

            return $number;
        }

        return DB::transaction(function () {
            $settings = InvoiceSetting::query()->lockForUpdate()->first();
            if (!$settings) {
                $settings = InvoiceSetting::instance();
                $settings = InvoiceSetting::query()->lockForUpdate()->find($settings->id);
            }

            do {
                $number = static::formatNumber(
                    (string) $settings->invoice_prefix,
                    (int) $settings->next_sequence,
                    (int) $settings->number_padding
                );
                $exists = Invoice::withTrashed()->where('invoice_number', $number)->exists();
                if ($exists) {
                    $settings->next_sequence++;
                    $settings->save();
                }
            } while ($exists);

            $settings->next_sequence = (int) $settings->next_sequence + 1;
            $settings->save();

            return $number;
        });
    }

    public static function formatNumber(string $prefix, int $sequence, int $padding): string
    {
        $year = date('Y');

        return sprintf('%s-%s-%0' . max(1, $padding) . 'd', rtrim($prefix, '-'), $year, $sequence);
    }
}
