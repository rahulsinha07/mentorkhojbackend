<?php

namespace App\CentralLogics;

use App\CentralLogics\WhatsAppCloudApi;
use App\Model\Invoice\Invoice;
use App\Model\Mentor\Mentor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InvoiceWhatsAppLogic
{
    /** Default ops WhatsApp: +91 73669 39888 */
    public const DEFAULT_NOTIFY_PHONE = '7366939888';

    public static function notifyPhone(): string
    {
        $phone = trim((string) env('INVOICE_WHATSAPP_PHONE', self::DEFAULT_NOTIFY_PHONE));

        return $phone !== '' ? $phone : self::DEFAULT_NOTIFY_PHONE;
    }

    /** E.164 digits for API / wa.me (e.g. 917366939888). */
    public static function recipientDigits(?string $phone = null): string
    {
        $digits = preg_replace('/\D/', '', (string) ($phone ?: static::notifyPhone())) ?? '';
        $digits = ltrim($digits, '0');

        if ($digits === '') {
            $digits = self::DEFAULT_NOTIFY_PHONE;
        }

        if (strlen($digits) === 10) {
            return '91' . $digits;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return $digits;
        }

        if (strlen($digits) > 10) {
            return $digits;
        }

        return '91' . $digits;
    }

    /** Human-readable: +91 73669 39888 */
    public static function recipientDisplay(?string $phone = null): string
    {
        $digits = static::recipientDigits($phone);
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $local = substr($digits, 2);

            return '+91 ' . substr($local, 0, 5) . ' ' . substr($local, 5);
        }

        return '+' . $digits;
    }

    public static function pdfFilename(Invoice $invoice): string
    {
        return 'Mentorkhoj-Invoice-' . preg_replace('/[^A-Za-z0-9\-]/', '', $invoice->invoice_number) . '.pdf';
    }

    /** Public HTTPS URL for WhatsApp document API (Meta must fetch this). */
    public static function publicPdfUrl(Invoice $invoice): string
    {
        $filename = static::pdfFilename($invoice);
        $relativePath = 'invoice/whatsapp/' . $filename;

        Storage::disk('public')->makeDirectory('invoice/whatsapp');
        Storage::disk('public')->put($relativePath, InvoicePdfLogic::rawBytes($invoice, true));

        $url = asset('storage/app/public/' . $relativePath);
        if (str_starts_with($url, 'http://')) {
            $url = 'https://' . substr($url, 7);
        }

        return $url;
    }

    public static function shareText(Invoice $invoice, ?string $pdfUrl = null): string
    {
        $amount = Helpers::set_symbol((float) $invoice->total_amount);
        $date = $invoice->invoice_date?->format('d M Y') ?? '—';
        $ref = $invoice->reference_number ? "\n*Reference:* {$invoice->reference_number}" : '';
        $balance = (float) $invoice->balance_due > 0
            ? "\n*Balance due:* " . Helpers::set_symbol((float) $invoice->balance_due)
            : '';

        $text = "Hi,\n\n"
            . "MentorKhoj invoice details:\n\n"
            . "*Invoice No:* {$invoice->invoice_number}\n"
            . "*Customer:* {$invoice->customer_name}\n"
            . "*Amount:* {$amount}\n"
            . "*Date:* {$date}"
            . $ref
            . $balance;

        if ($pdfUrl) {
            $text .= "\n\n*Download PDF:*\n{$pdfUrl}";
        }

        $text .= "\n\n— MentorKhoj";

        return $text;
    }

    public static function webUrl(Invoice $invoice, ?string $phone = null): ?string
    {
        $pdfUrl = static::publicPdfUrl($invoice);
        $localPhone = static::notifyPhone();
        if ($phone) {
            $localPhone = $phone;
        }

        return WhatsAppWebLink::url($localPhone, static::shareText($invoice, $pdfUrl));
    }

    public static function mentorAgreementPdfFilename(Invoice $invoice, Mentor $mentor): string
    {
        return MentorAgreementPdfLogic::filename($invoice, $mentor);
    }

    /** Public HTTPS URL for mentor agreement PDF (Meta must fetch this). */
    public static function mentorAgreementPublicPdfUrl(Invoice $invoice, Mentor $mentor): string
    {
        $filename = static::mentorAgreementPdfFilename($invoice, $mentor);
        $relativePath = 'invoice/whatsapp/mentor-agreements/' . $filename;

        Storage::disk('public')->makeDirectory('invoice/whatsapp/mentor-agreements');
        Storage::disk('public')->put(
            $relativePath,
            MentorAgreementPdfLogic::rawBytes($invoice, $mentor, true)
        );

        $url = asset('storage/app/public/' . $relativePath);
        if (str_starts_with($url, 'http://')) {
            $url = 'https://' . substr($url, 7);
        }

        return $url;
    }

    public static function mentorAgreementShareText(Invoice $invoice, Mentor $mentor, ?string $pdfUrl = null): string
    {
        $mentorName = $mentor->display_name ?? 'Mentor';
        $feePercent = MentorLogic::platformFeePercent();

        $text = "Mentor Service Agreement — {$mentorName}\n"
            . "Invoice: {$invoice->invoice_number}\n"
            . "Platform fee: {$feePercent}% | Payout: on session completion to UPI on file";

        if ($pdfUrl) {
            $text .= "\n\n{$pdfUrl}";
        }

        $text .= "\n\n— MentorKhoj";

        return $text;
    }

    public static function mentorAgreementWebUrl(Invoice $invoice, Mentor $mentor, ?string $phone = null): ?string
    {
        $pdfUrl = static::mentorAgreementPublicPdfUrl($invoice, $mentor);
        $localPhone = static::notifyPhone();
        if ($phone) {
            $localPhone = $phone;
        }

        return WhatsAppWebLink::url(
            $localPhone,
            static::mentorAgreementShareText($invoice, $mentor, $pdfUrl)
        );
    }

    /** @return array{status:string,message?:string,phone?:string} */
    public static function sendMentorAgreement(Invoice $invoice, Mentor $mentor, ?string $phone = null): array
    {
        $cfg = WhatsAppDemoBookingModule::messagingConfig();
        $to = static::recipientDigits($phone);
        $displayPhone = static::recipientDisplay($phone);

        if (!$cfg['enabled']) {
            return [
                'status' => 'not_configured',
                'message' => 'WhatsApp API not configured — opening WhatsApp Web',
                'phone' => $displayPhone,
            ];
        }

        $pdfUrl = static::mentorAgreementPublicPdfUrl($invoice, $mentor);
        $filename = static::mentorAgreementPdfFilename($invoice, $mentor);
        $caption = static::mentorAgreementShareText($invoice, $mentor);
        $textBody = static::mentorAgreementShareText($invoice, $mentor, $pdfUrl);

        $documentResult = static::postMessage($cfg, $to, [
            'type' => 'document',
            'document' => [
                'link' => $pdfUrl,
                'filename' => $filename,
                'caption' => mb_substr($caption, 0, 1024),
            ],
        ]);

        if ($documentResult['status'] === 'success') {
            return ['status' => 'success', 'phone' => $displayPhone];
        }

        Log::warning('WhatsApp mentor agreement document send failed, trying text', [
            'invoice' => $invoice->invoice_number,
            'mentor' => $mentor->id,
            'to' => $to,
            'error' => $documentResult['message'] ?? null,
        ]);

        $textResult = static::postMessage($cfg, $to, [
            'type' => 'text',
            'text' => [
                'preview_url' => true,
                'body' => mb_substr($textBody, 0, 4096),
            ],
        ]);

        if ($textResult['status'] === 'success') {
            return ['status' => 'success', 'phone' => $displayPhone];
        }

        return [
            'status' => 'fallback',
            'message' => $textResult['message'] ?? $documentResult['message'] ?? 'WhatsApp API unavailable',
            'phone' => $displayPhone,
        ];
    }

    /** @return array{status:string,message?:string,phone?:string} */
    public static function send(Invoice $invoice, ?string $phone = null): array
    {
        $cfg = WhatsAppDemoBookingModule::messagingConfig();
        $to = static::recipientDigits($phone);
        $displayPhone = static::recipientDisplay($phone);

        if (!$cfg['enabled']) {
            return [
                'status' => 'not_configured',
                'message' => 'WhatsApp API not configured — opening WhatsApp Web',
                'phone' => $displayPhone,
            ];
        }

        $pdfUrl = static::publicPdfUrl($invoice);
        $filename = static::pdfFilename($invoice);
        $caption = static::shareText($invoice);
        $textBody = static::shareText($invoice, $pdfUrl);

        $documentResult = static::postMessage($cfg, $to, [
            'type' => 'document',
            'document' => [
                'link' => $pdfUrl,
                'filename' => $filename,
                'caption' => mb_substr($caption, 0, 1024),
            ],
        ]);

        if ($documentResult['status'] === 'success') {
            return ['status' => 'success', 'phone' => $displayPhone];
        }

        Log::warning('WhatsApp invoice document send failed, trying text', [
            'invoice' => $invoice->invoice_number,
            'to' => $to,
            'error' => $documentResult['message'] ?? null,
        ]);

        $textResult = static::postMessage($cfg, $to, [
            'type' => 'text',
            'text' => [
                'preview_url' => true,
                'body' => mb_substr($textBody, 0, 4096),
            ],
        ]);

        if ($textResult['status'] === 'success') {
            return ['status' => 'success', 'phone' => $displayPhone];
        }

        return [
            'status' => 'fallback',
            'message' => $textResult['message'] ?? $documentResult['message'] ?? 'WhatsApp API unavailable',
            'phone' => $displayPhone,
        ];
    }

    /** @param array<string, mixed> $cfg @param array<string, mixed> $payload */
    private static function postMessage(array $cfg, string $to, array $payload): array
    {
        $type = (string) ($payload['type'] ?? 'text');
        $preview = $type === 'text'
            ? (string) data_get($payload, 'text.body', 'Invoice WhatsApp')
            : ($type === 'document' ? 'Invoice document' : 'Invoice WhatsApp');

        $result = WhatsAppCloudApi::send($to, $payload, 'invoice', $preview, $type);

        if (($result['status'] ?? '') === 'success') {
            return ['status' => 'success'];
        }

        return [
            'status' => $result['status'] ?? 'error',
            'message' => $result['message'] ?? 'WhatsApp send failed',
        ];
    }
}
