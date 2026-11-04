<?php

namespace App\CentralLogics;

use App\Model\Invoice\Invoice;
use App\Model\Invoice\InvoiceItem;
use App\Model\Invoice\InvoiceSetting;
use App\Model\Mentor\Mentor;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MentorAgreementLogic
{
    /** @return list<array<string, mixed>> */
    public static function mentorsForAgreement(Invoice $invoice): array
    {
        $snapshot = is_array($invoice->mentor_snapshot) ? $invoice->mentor_snapshot : [];
        $mentorIds = static::mentorIdsFromSnapshot($snapshot);

        if ($mentorIds === []) {
            $mentorIds = static::mentorIdsFromInvoiceItems($invoice);
        }

        if ($mentorIds === []) {
            return [];
        }

        $mentorsById = Mentor::with(['user', 'settings'])
            ->whereIn('id', $mentorIds)
            ->get()
            ->keyBy('id');

        $snapshotById = collect($snapshot)
            ->filter(fn ($entry) => is_array($entry) && !empty($entry['id']))
            ->keyBy(fn ($entry) => (int) $entry['id']);

        $result = [];
        foreach ($mentorIds as $mentorId) {
            $entry = $snapshotById->get($mentorId, []);
            $mentor = $mentorsById->get($mentorId);
            $payout = static::parsePayoutDetails($mentor);
            $upiId = trim((string) ($payout['upi_id'] ?? ''));

            $result[] = [
                'id' => $mentorId,
                'name' => $entry['name'] ?? ($mentor?->display_name ?? 'Mentor'),
                'username' => $entry['username'] ?? $mentor?->username,
                'profile_url' => $entry['profile_url'] ?? static::profileUrl($mentor),
                'has_upi' => $upiId !== '',
                'upi_id' => $upiId !== '' ? $upiId : null,
            ];
        }

        return $result;
    }

    public static function resolveMentor(Invoice $invoice, int $mentorId): Mentor
    {
        $allowed = collect(static::mentorsForAgreement($invoice))->pluck('id')->all();
        if (!in_array($mentorId, $allowed, true)) {
            abort(404, 'Mentor not found on this invoice');
        }

        return Mentor::with(['user', 'settings'])->findOrFail($mentorId);
    }

    /** @param list<array<string, mixed>> $snapshot @return list<int> */
    private static function mentorIdsFromSnapshot(array $snapshot): array
    {
        $ids = [];
        foreach ($snapshot as $entry) {
            if (is_array($entry) && !empty($entry['id'])) {
                $ids[] = (int) $entry['id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return list<int> */
    private static function mentorIdsFromInvoiceItems(Invoice $invoice): array
    {
        $invoice->loadMissing('items');
        $ids = [];

        foreach ($invoice->items as $item) {
            $sku = trim((string) ($item->sku ?? ''));
            if ($sku !== '' && ctype_digit($sku)) {
                $ids[] = (int) $sku;
                continue;
            }

            $serviceName = trim((string) ($item->service_name ?? ''));
            if ($serviceName === '') {
                continue;
            }

            $mentor = Mentor::query()
                ->where('display_name', $serviceName)
                ->orWhere('username', $serviceName)
                ->first();

            if ($mentor) {
                $ids[] = (int) $mentor->id;
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /** @return Collection<int, InvoiceItem> */
    public static function lineItemsForMentor(Invoice $invoice, Mentor $mentor): Collection
    {
        $invoice->loadMissing('items');
        $items = $invoice->items;
        $snapshot = is_array($invoice->mentor_snapshot) ? $invoice->mentor_snapshot : [];
        $mentorName = $mentor->display_name ?? '';
        $mentorIdStr = (string) $mentor->id;

        $matched = $items->filter(function (InvoiceItem $item) use ($mentorIdStr, $mentorName) {
            if ((string) ($item->sku ?? '') === $mentorIdStr) {
                return true;
            }

            if ($mentorName !== '' && stripos((string) $item->service_name, $mentorName) !== false) {
                return true;
            }

            return false;
        });

        if ($matched->isNotEmpty()) {
            return $matched->values();
        }

        if (count($snapshot) === 1) {
            return $items->values();
        }

        return collect();
    }

    /** @return array<string, mixed> */
    public static function commercialBreakdown(Invoice $invoice, Mentor $mentor): array
    {
        $feePercent = MentorLogic::platformFeePercent();
        $lines = [];
        $totalGross = 0.0;
        $totalFee = 0.0;
        $totalNet = 0.0;

        foreach (static::lineItemsForMentor($invoice, $mentor) as $item) {
            $lineGross = round(max(0, (float) $item->line_total - (float) $item->tax_amount), 2);
            $lineFee = round($lineGross * $feePercent / 100, 2);
            $lineNet = round($lineGross - $lineFee, 2);

            $lines[] = [
                'service_name' => $item->service_name,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit' => $item->unit ?? 'Session',
                'unit_price' => (float) $item->unit_price,
                'line_gross' => $lineGross,
                'platform_fee' => $lineFee,
                'net_payable' => $lineNet,
            ];

            $totalGross += $lineGross;
            $totalFee += $lineFee;
            $totalNet += $lineNet;
        }

        return [
            'platform_fee_percent' => $feePercent,
            'lines' => $lines,
            'total_gross' => round($totalGross, 2),
            'total_platform_fee' => round($totalFee, 2),
            'total_net_payable' => round($totalNet, 2),
        ];
    }

    /** @return array<string, mixed> */
    public static function mentorDisplay(Mentor $mentor, bool $maskPii = false): array
    {
        $user = $mentor->user;
        $payout = static::parsePayoutDetails($mentor);
        $aadhaar = $mentor->aadhaar;

        return [
            'id' => $mentor->id,
            'name' => $mentor->display_name,
            'headline' => $mentor->headline,
            'username' => $mentor->username,
            'profile_url' => static::profileUrl($mentor),
            'email' => $user?->email,
            'phone' => $user?->phone,
            'aadhaar' => $maskPii ? InvoicePrivacyLogic::maskAadhaar($aadhaar) : $aadhaar,
            'upi_id' => trim((string) ($payout['upi_id'] ?? '')) ?: null,
            'account_holder' => trim((string) ($payout['account_holder'] ?? '')) ?: null,
            'has_payout' => static::hasPayoutDetails($payout),
        ];
    }

    public static function mergedTerms(Invoice $invoice, Mentor $mentor): string
    {
        $payout = static::parsePayoutDetails($mentor);
        $upiId = trim((string) ($payout['upi_id'] ?? ''));
        $replacements = [
            '{platform_fee_percent}' => (string) MentorLogic::platformFeePercent(),
            '{mentor_name}' => $mentor->display_name ?? 'Mentor',
            '{upi_id}' => $upiId !== '' ? $upiId : 'not yet registered',
            '{invoice_number}' => $invoice->invoice_number,
            '{agreement_date}' => $invoice->invoice_date?->format('d M Y') ?? '—',
        ];

        $sections = [];
        foreach (config('mentor_agreement.clauses', []) as $clause) {
            $heading = strtr((string) ($clause['heading'] ?? ''), $replacements);
            $body = strtr((string) ($clause['body'] ?? ''), $replacements);
            $sections[] = $heading . "\n" . $body;
        }

        return implode("\n\n", $sections);
    }

    /** @return list<array{heading:string,body:string}> */
    public static function termsClauses(Invoice $invoice, Mentor $mentor): array
    {
        $payout = static::parsePayoutDetails($mentor);
        $upiId = trim((string) ($payout['upi_id'] ?? ''));
        $replacements = [
            '{platform_fee_percent}' => (string) MentorLogic::platformFeePercent(),
            '{mentor_name}' => $mentor->display_name ?? 'Mentor',
            '{upi_id}' => $upiId !== '' ? $upiId : 'not yet registered',
            '{invoice_number}' => $invoice->invoice_number,
            '{agreement_date}' => $invoice->invoice_date?->format('d M Y') ?? '—',
        ];

        $out = [];
        foreach (config('mentor_agreement.clauses', []) as $clause) {
            $out[] = [
                'heading' => strtr((string) ($clause['heading'] ?? ''), $replacements),
                'body' => strtr((string) ($clause['body'] ?? ''), $replacements),
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public static function viewData(Invoice $invoice, Mentor $mentor, ?InvoiceSetting $settings = null, bool $maskPii = false): array
    {
        $settings = $settings ?? InvoiceSetting::instance();
        $company = InvoiceCompanyProfile::mergedWithSettings($settings);
        $logoUrl = InvoiceCompanyProfile::logoUrl($settings->logo);
        $logoPath = InvoiceCompanyProfile::logoPathForPdf($settings->logo);
        $commercial = static::commercialBreakdown($invoice, $mentor);
        $mentorDisplay = static::mentorDisplay($mentor, $maskPii);

        return [
            'invoice' => $invoice->loadMissing('items'),
            'mentor' => $mentor,
            'mentor_display' => $mentorDisplay,
            'commercial' => $commercial,
            'terms_clauses' => static::termsClauses($invoice, $mentor),
            'payout_note' => config('mentor_agreement.payout_note'),
            'payout_pending_note' => config('mentor_agreement.payout_pending_note'),
            'agreement_version' => config('mentor_agreement.version'),
            'company' => $company,
            'logo_url' => $logoUrl,
            'logo_path' => $logoPath,
            'mentorkhoj_url' => rtrim((string) ($company['website'] ?? 'https://www.mentorkhoj.com/'), '/'),
            'net_amount_in_words' => IndianAmountInWords::convert(
                (float) ($commercial['total_net_payable'] ?? 0),
                $invoice->currency === 'INR' ? 'Indian Rupees' : $invoice->currency
            ),
        ];
    }

    public static function mentorSlug(Mentor $mentor): string
    {
        $slug = $mentor->username ?: Str::slug($mentor->display_name ?? 'mentor');

        return preg_replace('/[^A-Za-z0-9\-]/', '', $slug) ?: 'mentor-' . $mentor->id;
    }

    /** @return array<string, mixed> */
    private static function parsePayoutDetails(?Mentor $mentor): array
    {
        if (!$mentor?->settings?->payout_details) {
            return [];
        }

        $decoded = json_decode($mentor->settings->payout_details, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function hasPayoutDetails(array $payout): bool
    {
        return trim((string) ($payout['upi_id'] ?? '')) !== ''
            || trim((string) ($payout['account_number'] ?? '')) !== '';
    }

    private static function profileUrl(?Mentor $mentor): ?string
    {
        if (!$mentor?->username) {
            return null;
        }

        return 'https://www.mentorkhoj.com/mentor/' . $mentor->username;
    }
}
