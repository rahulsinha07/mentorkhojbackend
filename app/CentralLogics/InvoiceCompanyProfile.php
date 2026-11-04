<?php

namespace App\CentralLogics;

use App\Model\Invoice\InvoiceSetting;
use Illuminate\Support\Facades\Http;

class InvoiceCompanyProfile
{
    /** @return array<string, mixed> */
    public static function locked(): array
    {
        return config('invoice_company', []);
    }

    public static function get(string $key, $default = null)
    {
        return static::locked()[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public static function mergedWithSettings(?InvoiceSetting $settings = null): array
    {
        $settings = $settings ?? InvoiceSetting::instance();
        $locked = static::locked();

        return array_merge($locked, [
            'logo' => $settings->logo,
            'bank_name' => $settings->bank_name,
            'account_name' => $settings->account_name,
            'account_number' => $settings->account_number,
            'ifsc' => $settings->ifsc,
            'bank_branch' => $settings->bank_branch,
            'upi_id' => $settings->upi_id,
            'footer_text' => $settings->footer_text,
            'default_terms' => $settings->default_terms,
            'default_notes' => $settings->default_notes,
            'brand_color' => $settings->brand_color ?? '#107980',
        ]);
    }

    public static function logoUrl(?string $storedLogo): string
    {
        if ($storedLogo) {
            return asset('storage/app/public/invoice/' . $storedLogo);
        }

        $brandPath = static::mentorkhojBrandLogoPath();
        if ($brandPath) {
            return static::mentorkhojBrandLogoUrl();
        }

        $businessLogo = Helpers::get_business_settings('logo');
        if ($businessLogo) {
            return asset('storage/app/public/restaurant/' . $businessLogo);
        }

        return static::mentorkhojBrandLogoUrl();
    }

    public static function logoPathForPdf(?string $storedLogo): ?string
    {
        if ($storedLogo) {
            $path = storage_path('app/public/invoice/' . $storedLogo);
            if (is_file($path)) {
                return $path;
            }
        }

        $brandPath = static::mentorkhojBrandLogoPath();
        if ($brandPath) {
            return $brandPath;
        }

        $businessLogo = Helpers::get_business_settings('logo');
        if ($businessLogo) {
            $path = storage_path('app/public/restaurant/' . $businessLogo);
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public static function mentorkhojBrandLogoUrl(): string
    {
        $siteUrl = rtrim((string) config('app.mentorkhoj_site_url', 'https://www.mentorkhoj.com'), '/');

        return $siteUrl . '/logo-512.png';
    }

    public static function mentorkhojBrandLogoPath(): ?string
    {
        $cachePath = storage_path('app/public/invoice/mentorkhoj-logo.png');
        if (is_file($cachePath) && filesize($cachePath) > 512) {
            return $cachePath;
        }

        $dir = dirname($cachePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        try {
            $response = Http::timeout(15)->get(static::mentorkhojBrandLogoUrl());
            if ($response->successful() && strlen($response->body()) > 512) {
                file_put_contents($cachePath, $response->body());

                return $cachePath;
            }
        } catch (\Throwable $e) {
            // Fall through to legacy logo paths.
        }

        return is_file($cachePath) && filesize($cachePath) > 0 ? $cachePath : null;
    }
}
