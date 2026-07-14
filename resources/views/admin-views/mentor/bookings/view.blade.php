@extends('layouts.admin.app')

@section('title', translate('Booking') . ' #' . $booking->id)

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span>{{ translate('Mentor Booking') }} #{{ $booking->id }}</span>
            </h1>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="card-title mb-0">{{ translate('Session details') }}</h5></div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">{{ translate('Mentor') }}</dt>
                            <dd class="col-sm-8">{{ $booking->mentor?->display_name ?? '—' }}</dd>
                            <dt class="col-sm-4">{{ translate('Mentee') }}</dt>
                            <dd class="col-sm-8">{{ trim(($booking->mentee?->f_name ?? '') . ' ' . ($booking->mentee?->l_name ?? '')) ?: '—' }}</dd>
                            <dt class="col-sm-4">{{ translate('Service') }}</dt>
                            <dd class="col-sm-8">{{ $booking->service?->title ?? '—' }}</dd>
                            <dt class="col-sm-4">{{ translate('Preferred date') }}</dt>
                            <dd class="col-sm-8">{{ $booking->preferred_date?->format('Y-m-d') ?? '—' }} {{ $booking->preferred_time }}</dd>
                            <dt class="col-sm-4">{{ translate('Note') }}</dt>
                            <dd class="col-sm-8">{{ $booking->mentee_note ?: '—' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="card-title mb-0">{{ translate('Payment') }}</h5></div>
                    <div class="card-body">
                        <dl class="mb-0">
                            <dt>{{ translate('Amount') }}</dt>
                            <dd>{{ Helpers::set_symbol($booking->amount) }}</dd>
                            <dt>{{ translate('Tax') }}</dt>
                            <dd>{{ Helpers::set_symbol($booking->tax_amount) }}</dd>
                            <dt>{{ translate('Platform fee') }}</dt>
                            <dd>{{ Helpers::set_symbol($booking->platform_fee) }}</dd>
                            <dt>{{ translate('Mentor net') }}</dt>
                            <dd>{{ Helpers::set_symbol($booking->mentor_net) }}</dd>
                            <dt>{{ translate('Payment status') }}</dt>
                            <dd><span class="badge badge-soft-{{ $booking->payment_status === 'paid' ? 'success' : 'warning' }}">{{ $booking->payment_status }}</span></dd>
                            <dt>{{ translate('Booking status') }}</dt>
                            <dd><span class="badge badge-soft-info">{{ $booking->status }}</span></dd>
                            @if($booking->legacy_order_id)
                                <dt>{{ translate('Legacy order') }}</dt>
                                <dd><a href="{{ route('admin.orders.details', $booking->legacy_order_id) }}">#{{ $booking->legacy_order_id }}</a></dd>
                            @endif
                        </dl>
                    </div>
                </div>
                <a href="{{ route('admin.mentor.bookings.list') }}" class="btn btn-secondary">{{ translate('Back') }}</a>
            </div>
        </div>
    </div>
@endsection
