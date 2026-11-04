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
            <form action="{{ route('admin.invoices.send-whatsapp', $invoice->id) }}" method="post" class="d-inline">@csrf
                <button type="submit" class="btn btn-success" title="{{ translate('Send on WhatsApp to') }} {{ $whatsappNotifyPhone ?? '+91 73669 39888' }}">
                    <i class="tio-whatsapp"></i> {{ translate('Send on WhatsApp') }} ({{ $whatsappNotifyPhone ?? '+91 73669 39888' }})
                </button>
            </form>
            <a href="{{ route('admin.invoices.whatsapp', $invoice->id) }}" class="btn btn-outline-success" target="_blank" rel="noopener noreferrer" title="{{ translate('Open WhatsApp chat with invoice link') }}">
                <i class="tio-whatsapp"></i> {{ translate('Open WhatsApp') }}
            </a>
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

    @if(!empty($mentorsForAgreement))
    <div class="card mt-3">
        <div class="card-header">
            <h5 class="card-title mb-0">{{ translate('Mentor Service Agreement') }}</h5>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">{{ translate('Generate a separate legal agreement for each mentor on this invoice. Customer invoice actions above are unchanged.') }}</p>

            <form method="get" action="{{ route('admin.invoices.show', $invoice->id) }}" class="row g-3 align-items-end mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="mentor_id">{{ translate('Select mentor') }}</label>
                    <select name="mentor_id" id="mentor_id" class="form-control" onchange="this.form.submit()">
                        @foreach($mentorsForAgreement as $mentorOption)
                            <option value="{{ $mentorOption['id'] }}" @selected((int)($selectedMentorId ?? 0) === (int)$mentorOption['id'])>
                                {{ $mentorOption['name'] }}
                                @if(empty($mentorOption['has_upi'])) — {{ translate('UPI pending') }} @endif
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>

            @if(!empty($selectedMentorForAgreement) && empty($selectedMentorForAgreement['has_upi']))
                <div class="alert alert-warning">{{ translate('This mentor has no UPI on file. They must complete Payouts setup on the MentorKhoj dashboard before payout.') }}</div>
            @endif

            @if(!empty($selectedMentorId))
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <a href="{{ route('admin.invoices.mentor-agreement.pdf', [$invoice->id, $selectedMentorId]) }}" class="btn btn--primary">
                    {{ translate('Download Mentor Agreement PDF') }}
                </a>
                <a href="{{ route('admin.invoices.mentor-agreement.print', [$invoice->id, $selectedMentorId]) }}" target="_blank" class="btn btn--info">
                    {{ translate('Print Mentor Agreement') }}
                </a>
                <form action="{{ route('admin.invoices.mentor-agreement.send-whatsapp', [$invoice->id, $selectedMentorId]) }}" method="post" class="d-inline">@csrf
                    <button type="submit" class="btn btn-success" title="{{ translate('Send PDF on WhatsApp to MentorKhoj ops') }} {{ $whatsappNotifyPhone ?? '+91 73669 39888' }}">
                        <i class="tio-whatsapp"></i>
                        {{ translate('Send on WhatsApp') }} ({{ $whatsappNotifyPhone ?? '+91 73669 39888' }})
                        — {{ $selectedMentorForAgreement['name'] ?? translate('Mentor') }}
                    </button>
                </form>
                <a href="{{ route('admin.invoices.mentor-agreement.whatsapp', [$invoice->id, $selectedMentorId]) }}" class="btn btn-outline-success" target="_blank" rel="noopener noreferrer" title="{{ translate('Open WhatsApp chat with mentor agreement PDF link') }}">
                    <i class="tio-whatsapp"></i> {{ translate('Open WhatsApp') }} ({{ $whatsappNotifyPhone ?? '+91 73669 39888' }})
                </a>
            </div>
            <p class="text-muted small mt-2 mb-0">{{ translate('Sends the mentor agreement PDF to the MentorKhoj ops WhatsApp number') }} {{ $whatsappNotifyPhone ?? '+91 73669 39888' }}.</p>
            @endif
        </div>
    </div>
    @endif
</div>
@endsection
