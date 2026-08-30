<?php

namespace App\CentralLogics;

use App\Model\Invoice\Invoice;
use App\Model\Invoice\InvoiceSetting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;

class InvoicePdfLogic
{
    /** @return array<string, mixed> */
    public static function viewData(Invoice $invoice, ?InvoiceSetting $settings = null): array
    {
        $settings = $settings ?? InvoiceSetting::instance();
        $company = InvoiceCompanyProfile::mergedWithSettings($settings);
        $logoUrl = InvoiceCompanyProfile::logoUrl($settings->logo);
        $logoPath = InvoiceCompanyProfile::logoPathForPdf($settings->logo);

        return [
            'invoice' => $invoice->loadMissing('items'),
            'company' => $company,
            'logo_url' => $logoUrl,
            'logo_path' => $logoPath,
            'amount_in_words' => IndianAmountInWords::convert(
                (float) $invoice->total_amount,
                $invoice->currency === 'INR' ? 'Indian Rupees' : $invoice->currency
            ),
        ];
    }

    public static function renderHtml(Invoice $invoice): string
    {
        return View::make('admin-views.invoices.pdf.invoice-a4', static::viewData($invoice))->render();
    }

    public static function download(Invoice $invoice): Response
    {
        $html = static::renderHtml($invoice);
        $mpdf = static::createMpdf();
        $mpdf->WriteHTML($html);

        $filename = 'Mentorkhoj-Invoice-' . preg_replace('/[^A-Za-z0-9\-]/', '', $invoice->invoice_number) . '.pdf';

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public static function inline(Invoice $invoice): Response
    {
        $html = static::renderHtml($invoice);
        $mpdf = static::createMpdf();
        $mpdf->WriteHTML($html);

        $filename = 'Mentorkhoj-Invoice-' . preg_replace('/[^A-Za-z0-9\-]/', '', $invoice->invoice_number) . '.pdf';

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /** @return string Raw PDF bytes for email attachment */
    public static function rawBytes(Invoice $invoice): string
    {
        $html = static::renderHtml($invoice);
        $mpdf = static::createMpdf();
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    private static function createMpdf(): Mpdf
    {
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 14,
            'margin_bottom' => 14,
            'default_font' => 'dejavusans',
        ]);
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->shrink_tables_to_fit = 1;

        return $mpdf;
    }
}
