@extends('layouts.admin.app')

@section('title', translate('Invoice History'))

@section('content')
<div class="content container-fluid">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h1 class="page-header-title mb-0">{{ translate('Invoice History') }}</h1>
        <a href="{{ route('admin.invoices.create') }}" class="btn btn--primary">{{ translate('Create Invoice') }}</a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-2">
                <div class="col-md-3"><input type="text" name="search" class="form-control" placeholder="{{ translate('Search...') }}" value="{{ request('search') }}"></div>
                <div class="col-md-2">
                    <select name="payment_status" class="form-control">
                        <option value="">{{ translate('All statuses') }}</option>
                        @foreach(['paid','partially_paid','pending','cancelled','refunded'] as $st)
                            <option value="{{ $st }}" @selected(request('payment_status')===$st)>{{ ucfirst(str_replace('_',' ',$st)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2"><input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}"></div>
                <div class="col-md-2"><input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}"></div>
                <div class="col-md-1"><input type="number" step="0.01" name="min_amount" class="form-control" placeholder="Min" value="{{ request('min_amount') }}"></div>
                <div class="col-md-1"><input type="number" step="0.01" name="max_amount" class="form-control" placeholder="Max" value="{{ request('max_amount') }}"></div>
                <div class="col-md-1"><button class="btn btn--primary w-100">{{ translate('Filter') }}</button></div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr>
                    <th>{{ translate('Invoice #') }}</th>
                    <th>{{ translate('Date') }}</th>
                    <th>{{ translate('Customer') }}</th>
                    <th>{{ translate('Amount') }}</th>
                    <th>{{ translate('Payment') }}</th>
                    <th>{{ translate('Method') }}</th>
                    <th>{{ translate('Created By') }}</th>
                    <th>{{ translate('Actions') }}</th>
                </tr></thead>
                <tbody>
                @forelse($invoices as $inv)
                    <tr>
                        <td><a href="{{ route('admin.invoices.show', $inv->id) }}">{{ $inv->invoice_number }}</a></td>
                        <td>{{ $inv->invoice_date?->format('d M Y') }}</td>
                        <td>{{ $inv->customer_name }}</td>
                        <td>{{ \App\CentralLogics\Helpers::set_symbol($inv->total_amount) }}</td>
                        <td><span class="badge badge-soft-info">{{ ucfirst(str_replace('_',' ', $inv->payment_status)) }}</span></td>
                        <td>{{ $inv->payment_method ? ucfirst(str_replace('_',' ', $inv->payment_method)) : '—' }}</td>
                        <td>{{ $inv->createdBy?->f_name ?? '—' }}</td>
                        <td class="text-nowrap">
                            <a href="{{ route('admin.invoices.show', $inv->id) }}" class="btn btn-xs btn--primary">{{ translate('View') }}</a>
                            @if($inv->isEditable())
                                <a href="{{ route('admin.invoices.edit', $inv->id) }}" class="btn btn-xs btn--secondary">{{ translate('Edit') }}</a>
                            @endif
                            <a href="{{ route('admin.invoices.pdf', $inv->id) }}" class="btn btn-xs btn--success">{{ translate('PDF') }}</a>
                            <form action="{{ route('admin.invoices.duplicate', $inv->id) }}" method="post" class="d-inline">@csrf<button class="btn btn-xs btn--info">{{ translate('Duplicate') }}</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center py-4">{{ translate('No invoices found') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($invoices->hasPages())
            <div class="card-footer">{{ $invoices->links() }}</div>
        @endif
    </div>
</div>
@endsection
