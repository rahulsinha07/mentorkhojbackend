<?php

namespace App\CentralLogics;

class IndianAmountInWords
{
    private static array $ones = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
        'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private static array $tens = [
        '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
    ];

    public static function convert(float $amount, string $currencyLabel = 'Indian Rupees'): string
    {
        $rupees = (int) floor(abs($amount));
        $paise = (int) round((abs($amount) - $rupees) * 100);

        if ($rupees === 0 && $paise === 0) {
            return $currencyLabel . ' Zero Only';
        }

        $words = static::numberToWords($rupees);
        $result = trim($currencyLabel . ' ' . $words);

        if ($paise > 0) {
            $result .= ' and ' . static::numberToWords($paise) . ' Paise';
        }

        return $result . ' Only';
    }

    private static function numberToWords(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $parts = [];

        $crore = intdiv($number, 10000000);
        $number %= 10000000;
        $lakh = intdiv($number, 100000);
        $number %= 100000;
        $thousand = intdiv($number, 1000);
        $number %= 1000;
        $hundred = intdiv($number, 100);
        $number %= 100;

        if ($crore > 0) {
            $parts[] = static::numberToWords($crore) . ' Crore';
        }
        if ($lakh > 0) {
            $parts[] = static::twoDigitWords($lakh) . ' Lakh';
        }
        if ($thousand > 0) {
            $parts[] = static::twoDigitWords($thousand) . ' Thousand';
        }
        if ($hundred > 0) {
            $parts[] = static::$ones[$hundred] . ' Hundred';
        }
        if ($number > 0) {
            $parts[] = static::twoDigitWords($number);
        }

        return implode(' ', array_filter($parts));
    }

    private static function twoDigitWords(int $number): string
    {
        if ($number < 20) {
            return static::$ones[$number];
        }

        $ten = intdiv($number, 10);
        $one = $number % 10;

        return trim(static::$tens[$ten] . ($one ? ' ' . static::$ones[$one] : ''));
    }
}
