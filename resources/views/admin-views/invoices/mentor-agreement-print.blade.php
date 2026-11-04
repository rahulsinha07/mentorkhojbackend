<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Mentor Agreement — {{ $mentor_display['name'] ?? 'Mentor' }} — {{ $invoice->invoice_number }}</title>
    <style>@media print { .no-print { display:none } }</style>
</head>
<body>
<div class="no-print" style="padding:16px;text-align:center;">
    <button onclick="window.print()">Print</button>
</div>
@include('admin-views.invoices.pdf.mentor-agreement-a4')
</body>
</html>
