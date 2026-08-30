@extends('layouts.admin.app')

@section('title', translate('Invoice Dashboard'))

@section('content')
<div class="content container-fluid">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h1 class="page-header-title mb-0">{{ translate('Invoice Dashboard') }}</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.invoices.create') }}" class="btn btn--primary">{{ translate('Create Invoice') }}</a>
            <a href="{{ route('admin.invoices.list') }}" class="btn btn--secondary">{{ translate('Invoice History') }}</a>
        </div>
    </div>

    <div class="row g-2 mb-4">
        @foreach([
            ['Total Invoices', $stats['total_invoices'], 'primary'],
            ['Total Revenue', \App\CentralLogics\Helpers::set_symbol($stats['total_revenue']), 'success'],
            ['Paid', $stats['paid'], 'success'],
            ['Pending', $stats['pending'], 'warning'],
            ['Partially Paid', $stats['partially_paid'], 'info'],
            ['Cancelled', $stats['cancelled'], 'danger'],
            ['Outstanding', \App\CentralLogics\Helpers::set_symbol($stats['outstanding']), 'dark'],
        ] as [$label, $value, $color])
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100 border-{{ $color }}">
                    <div class="card-body">
                        <h6 class="text-muted mb-1">{{ translate($label) }}</h6>
                        <h3 class="mb-0">{{ $value }}</h3>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">{{ translate('Recent Invoices') }}</h5></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr>
                    <th>{{ translate('Invoice #') }}</th>
                    <th>{{ translate('Date') }}</th>
                    <th>{{ translate('Customer') }}</th>
                    <th>{{ translate('Amount') }}</th>
                    <th>{{ translate('Status') }}</th>
                    <th></th>
                </tr></thead>
                <tbody>
                @forelse($recent as $inv)
                    <tr>
                        <td>{{ $inv->invoice_number }}</td>
                        <td>{{ $inv->invoice_date?->format('d M Y') }}</td>
                        <td>{{ $inv->customer_name }}</td>
                        <td>{{ \App\CentralLogics\Helpers::set_symbol($inv->total_amount) }}</td>
                        <td><span class="badge badge-soft-info">{{ ucfirst(str_replace('_',' ', $inv->payment_status)) }}</span></td>
                        <td><a href="{{ route('admin.invoices.show', $inv->id) }}" class="btn btn-sm btn--primary">{{ translate('View') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-4">{{ translate('No invoices yet') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
