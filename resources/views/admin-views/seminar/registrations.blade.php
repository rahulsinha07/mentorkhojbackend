@extends('layouts.admin.app')

@section('title', 'Seminar Registrations')

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-group-equal"></i></span>
                <span>Seminar registrations <span class="badge badge-soft-secondary">{{ $bookings->total() }}</span></span>
            </h1>
        </div>

        <div class="card">
            <div class="card-header border-0">
                <form action="{{ url()->current() }}" method="GET" class="row g-2 mb-3">
                    <div class="col-md-4">
                        <select name="seminar_id" class="form-control" onchange="this.form.submit()">
                            <option value="">All seminars</option>
                            @foreach($seminars as $seminar)
                                <option value="{{ $seminar->id }}" {{ (string)$seminarId === (string)$seminar->id ? 'selected' : '' }}>
                                    {{ $seminar->title }}
                                    @if((float) ($seminar->fee_amount ?? 0) > 0)
                                        (₹{{ number_format($seminar->fee_amount, 0) }})
                                    @else
                                        (Free)
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-8">
                        <div class="input-group">
                            <input type="search" name="search" class="form-control" placeholder="Search name, email, phone, booking ref, college"
                                   value="{{ $search }}" autocomplete="off">
                            @if($paymentFilter)
                                <input type="hidden" name="payment_status" value="{{ $paymentFilter }}">
                            @endif
                            <div class="input-group-append">
                                <button type="submit" class="input-group-text">{{ translate('search') }}</button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="d-flex flex-wrap gap-2">
                    @php
                        $filterBase = array_filter(['seminar_id' => $seminarId, 'search' => $search ?: null]);
                    @endphp
                    <a href="{{ route('admin.seminar.registrations', $filterBase) }}"
                       class="btn btn-sm {{ empty($paymentFilter) ? 'btn-primary' : 'btn-outline-primary' }}">All</a>
                    <a href="{{ route('admin.seminar.registrations', array_merge($filterBase, ['payment_status' => 'paid'])) }}"
                       class="btn btn-sm {{ $paymentFilter === 'paid' ? 'btn-primary' : 'btn-outline-primary' }}">Paid</a>
                    <a href="{{ route('admin.seminar.registrations', array_merge($filterBase, ['payment_status' => 'pending'])) }}"
                       class="btn btn-sm {{ $paymentFilter === 'pending' ? 'btn-primary' : 'btn-outline-primary' }}">Pending</a>
                    <a href="{{ route('admin.seminar.registrations', array_merge($filterBase, ['payment_status' => 'failed'])) }}"
                       class="btn btn-sm {{ $paymentFilter === 'failed' ? 'btn-primary' : 'btn-outline-primary' }}">Failed</a>
                    <a href="{{ route('admin.seminar.registrations', array_merge($filterBase, ['payment_status' => 'free'])) }}"
                       class="btn btn-sm {{ $paymentFilter === 'free' ? 'btn-primary' : 'btn-outline-primary' }}">Free</a>
                </div>
            </div>

            <div class="table-responsive datatable-custom">
                <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Booking ref</th>
                        <th>Seminar</th>
                        <th>Student</th>
                        <th>College / Org</th>
                        <th>Amount</th>
                        <th>Booking</th>
                        <th>Payment</th>
                        <th>Date</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($bookings as $key => $booking)
                        @php
                            $isFree = (float) $booking->amount <= 0;
                            $paymentBadges = [
                                'paid' => 'badge-soft-success',
                                'pending' => 'badge-soft-warning',
                                'failed' => 'badge-soft-danger',
                                'not_required' => 'badge-soft-info',
                            ];
                            $statusBadges = [
                                'confirmed' => 'badge-soft-success',
                                'pending' => 'badge-soft-warning',
                                'cancelled' => 'badge-soft-danger',
                            ];
                        @endphp
                        <tr>
                            <td>{{ $bookings->firstItem() + $key }}</td>
                            <td><code>{{ $booking->booking_ref }}</code></td>
                            <td>
                                {{ $booking->seminar?->title ?? '—' }}
                                @if($booking->seminar && (float) ($booking->seminar->fee_amount ?? 0) <= 0)
                                    <span class="badge badge-soft-info ml-1">Free</span>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $booking->name }}</strong><br>
                                <small>{{ $booking->email }}</small><br>
                                <small class="text-muted">{{ $booking->phone }}</small>
                            </td>
                            <td>
                                {{ $booking->org ?: '—' }}
                                @if($booking->details)
                                    <small class="d-block text-muted">{{ \Illuminate\Support\Str::limit($booking->details, 80) }}</small>
                                @endif
                            </td>
                            <td>
                                @if($isFree)
                                    FREE
                                @else
                                    ₹{{ number_format($booking->amount, 2) }}
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $statusBadges[$booking->status] ?? 'badge-soft-secondary' }}">
                                    {{ $booking->status }}
                                </span>
                            </td>
                            <td>
                                @if($isFree)
                                    <span class="text-muted">—</span>
                                @else
                                    <span class="badge {{ $paymentBadges[$booking->payment_status] ?? 'badge-soft-secondary' }}">
                                        {{ $booking->payment_status }}
                                    </span>
                                    @if($booking->razorpay_payment_id)
                                        <small class="d-block text-muted">{{ $booking->razorpay_payment_id }}</small>
                                    @endif
                                @endif
                            </td>
                            <td>{{ $booking->created_at?->format('d M Y, h:i A') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center py-4">No registrations yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($legacyRegistrations->isNotEmpty())
                <div class="card-header border-top">
                    <h6 class="mb-0 text-muted">Legacy registrations (old form, before online booking)</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-borderless mb-0">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>College</th>
                            <th>Date</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($legacyRegistrations as $legacy)
                            <tr>
                                <td><code>{{ $legacy->registration_id }}</code></td>
                                <td>{{ $legacy->name }}</td>
                                <td>{{ $legacy->email }}</td>
                                <td>{{ $legacy->college }}</td>
                                <td>{{ $legacy->created_at?->format('d M Y') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="card-footer">{!! $bookings->links() !!}</div>
        </div>
    </div>
@endsection
