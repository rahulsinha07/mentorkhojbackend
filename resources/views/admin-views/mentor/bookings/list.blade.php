@extends('layouts.admin.app')

@section('title', translate('Mentor Bookings'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/order.png') }}" class="w--24" alt="">
                </span>
                <span>
                    {{ translate('Mentor Bookings') }}
                    <span class="badge badge-soft-secondary">{{ $bookings->total() }}</span>
                </span>
            </h1>
        </div>

        <div class="card">
            <div class="card-header border-0">
                <form action="{{ url()->current() }}" method="GET" class="w-100">
                    <div class="input-group">
                        <input type="search" name="search" class="form-control"
                               placeholder="{{ translate('Search by ID, mentor or mentee') }}"
                               value="{{ $search ?? '' }}">
                        <div class="input-group-append">
                            <button type="submit" class="input-group-text">{{ translate('search') }}</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-borderless table-thead-bordered table-nowrap card-table">
                    <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>{{ translate('Mentor') }}</th>
                        <th>{{ translate('Mentee') }}</th>
                        <th>{{ translate('Service') }}</th>
                        <th>{{ translate('Date') }}</th>
                        <th class="text-right">{{ translate('Amount') }}</th>
                        <th class="text-center">{{ translate('Payment') }}</th>
                        <th class="text-center">{{ translate('Status') }}</th>
                        <th class="text-center">{{ translate('Order') }}</th>
                        <th class="text-center">{{ translate('Action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($bookings as $key => $booking)
                        <tr>
                            <td>{{ $bookings->firstItem() + $key }}</td>
                            <td>{{ $booking->mentor?->display_name ?? '—' }}</td>
                            <td>{{ trim(($booking->mentee?->f_name ?? '') . ' ' . ($booking->mentee?->l_name ?? '')) ?: '—' }}</td>
                            <td>{{ $booking->service?->title ?? '—' }}</td>
                            <td>
                                {{ $booking->preferred_date?->format('Y-m-d') ?? '—' }}
                                @if($booking->preferred_time)
                                    <br><small>{{ $booking->preferred_time }}</small>
                                @endif
                            </td>
                            <td class="text-right">
                                {{ Helpers::set_symbol($booking->amount + $booking->tax_amount) }}
                            </td>
                            <td class="text-center">
                                <span class="badge badge-soft-{{ $booking->payment_status === 'paid' ? 'success' : ($booking->payment_status === 'failed' ? 'danger' : 'warning') }}">
                                    {{ $booking->payment_status }}
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-soft-info">{{ $booking->status }}</span>
                            </td>
                            <td class="text-center">
                                @if($booking->legacy_order_id)
                                    <a href="{{ route('admin.orders.details', $booking->legacy_order_id) }}">#{{ $booking->legacy_order_id }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-center">
                                <a class="btn btn-sm btn-white" href="{{ route('admin.mentor.bookings.show', $booking->id) }}">
                                    {{ translate('View') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                {!! $bookings->links() !!}
            </div>
        </div>
    </div>
@endsection
