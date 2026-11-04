<?php

namespace App\CentralLogics;

use App\Model\Invoice\Invoice;
use App\Model\Mentor\Mentor;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;

class MentorAgreementPdfLogic
{
    /** @return array<string, mixed> */
    public static function viewData(Invoice $invoice, Mentor $mentor, bool $maskPii = false): array
    {
        return MentorAgreementLogic::viewData($invoice, $mentor, null, $maskPii);
    }

    public static function renderHtml(Invoice $invoice, Mentor $mentor, bool $maskPii = false): string
    {
        return View::make(
            'admin-views.invoices.pdf.mentor-agreement-a4',
            static::viewData($invoice, $mentor, $maskPii)
        )->render();
    }

    public static function download(Invoice $invoice, Mentor $mentor): Response
    {
        $html = static::renderHtml($invoice, $mentor, true);
        $mpdf = static::createMpdf();
        $mpdf->WriteHTML($html);

        $filename = static::filename($invoice, $mentor);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public static function inline(Invoice $invoice, Mentor $mentor): Response
    {
        $html = static::renderHtml($invoice, $mentor, false);
        $mpdf = static::createMpdf();
        $mpdf->WriteHTML($html);

        $filename = static::filename($invoice, $mentor);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /** @return string Raw PDF bytes */
    public static function rawBytes(Invoice $invoice, Mentor $mentor, bool $maskPii = false): string
    {
        $html = static::renderHtml($invoice, $mentor, $maskPii);
        $mpdf = static::createMpdf();
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    public static function filename(Invoice $invoice, Mentor $mentor): string
    {
        $slug = MentorAgreementLogic::mentorSlug($mentor);
        $invNum = preg_replace('/[^A-Za-z0-9\-]/', '', $invoice->invoice_number);

        return 'Mentorkhoj-Mentor-Agreement-' . $slug . '-' . $invNum . '.pdf';
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
