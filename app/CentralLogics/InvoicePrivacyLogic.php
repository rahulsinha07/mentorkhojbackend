<?php

namespace App\CentralLogics;

class InvoicePrivacyLogic
{
    public static function maskPhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) < 4) {
            return '****';
        }

        $last4 = substr($digits, -4);

        return '+91******' . $last4;
    }

    public static function maskEmail(?string $email): ?string
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        $email = trim($email);
        if (!str_contains($email, '@')) {
            return '***';
        }

        [$local, $domain] = explode('@', $email, 2);
        $first = $local !== '' ? substr($local, 0, 1) : '*';

        return $first . '***@' . $domain;
    }

    public static function maskAadhaar(?string $aadhaar): ?string
    {
        if ($aadhaar === null || trim($aadhaar) === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $aadhaar);
        if (strlen($digits) < 4) {
            return 'XXXX XXXX ****';
        }

        return 'XXXX XXXX ' . substr($digits, -4);
    }

    /** @return array<string, mixed> */
    public static function customerDisplayForInvoice(object $invoice, bool $maskPii): array
    {
        $phone = $invoice->customer_phone ?? null;
        $email = $invoice->customer_email ?? null;
        $aadhaar = $invoice->customer_aadhaar ?? null;

        if ($maskPii) {
            return [
                'name' => $invoice->customer_name,
                'phone' => static::maskPhone($phone),
                'email' => static::maskEmail($email),
                'aadhaar' => static::maskAadhaar($aadhaar),
            ];
        }

        return [
            'name' => $invoice->customer_name,
            'phone' => $phone,
            'email' => $email,
            'aadhaar' => $aadhaar,
        ];
    }
}
