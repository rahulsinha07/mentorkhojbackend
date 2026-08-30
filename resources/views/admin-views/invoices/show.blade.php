@extends('layouts.admin.app')
@section('title', translate('Invoice') . ' ' . $invoice->invoice_number)
@section('content')
<div class="content container-fluid">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h1 class="page-header-title mb-0">{{ translate('Invoice') }} {{ $invoice->invoice_number }}</h1>
        <div class="d-flex flex-wrap gap-2">
            @if($invoice->isEditable())
                <a href="{{ route('admin.invoices.edit', $invoice->id) }}" class="btn btn--secondary">{{ translate('Edit') }}</a>
            @endif
            <a href="{{ route('admin.invoices.pdf', $invoice->id) }}" class="btn btn--primary">{{ translate('Download PDF') }}</a>
            <a href="{{ route('admin.invoices.print', $invoice->id) }}" target="_blank" class="btn btn--info">{{ translate('Print') }}</a>
            <form action="{{ route('admin.invoices.duplicate', $invoice->id) }}" method="post" class="d-inline">@csrf<button class="btn btn--secondary">{{ translate('Duplicate') }}</button></form>
            @if($invoice->customer_email)
                <form action="{{ route('admin.invoices.send', $invoice->id) }}" method="post" class="d-inline">@csrf<button class="btn btn--success">{{ translate('Send Email') }}</button></form>
            @endif
            @if($invoice->status !== 'cancelled')
                <form action="{{ route('admin.invoices.cancel', $invoice->id) }}" method="post" class="d-inline">@csrf<button class="btn btn--danger" onclick="return confirm('Cancel invoice?')">{{ translate('Cancel') }}</button></form>
            @endif
            <a href="{{ route('admin.invoices.create') }}" class="btn btn--primary">{{ translate('New Invoice') }}</a>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="card">
        <div class="card-body">
            @include('admin-views.invoices.pdf.invoice-a4', $pdfData)
        </div>
    </div>
</div>
@endsection
