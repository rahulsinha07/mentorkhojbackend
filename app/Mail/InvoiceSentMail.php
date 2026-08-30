<?php

namespace App\Mail;

use App\CentralLogics\InvoicePdfLogic;
use App\Model\Invoice\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceSentMail extends Mailable
{
    use Queueable, SerializesModels;

    public Invoice $invoice;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice->loadMissing('items');
    }

    public function build(): self
    {
        $filename = 'Mentorkhoj-Invoice-' . preg_replace('/[^A-Za-z0-9\-]/', '', $this->invoice->invoice_number) . '.pdf';

        return $this->subject('Invoice ' . $this->invoice->invoice_number . ' from Mentorkhoj')
            ->view('emails.invoice-sent')
            ->with(['invoice' => $this->invoice])
            ->attachData(InvoicePdfLogic::rawBytes($this->invoice), $filename, [
                'mime' => 'application/pdf',
            ]);
    }
}
