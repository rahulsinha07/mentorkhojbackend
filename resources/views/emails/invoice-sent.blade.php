<p>Dear {{ $invoice->customer_name }},</p>
<p>Please find attached invoice <strong>{{ $invoice->invoice_number }}</strong> from Mentorkhoj.</p>
<p>Total amount: {{ \App\CentralLogics\Helpers::set_symbol($invoice->total_amount) }}</p>
<p>Thank you for choosing Mentorkhoj.</p>
<p>— BetterWits Software Private Limited<br>GSTIN: 10AALCB4748G1ZU</p>
