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
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3">
                            <select name="status" class="custom-select" onchange="this.form.submit()">
                                <option value="">{{ translate('All statuses') }}</option>
                                @foreach(['requested', 'confirmed', 'completed', 'cancelled', 'refunded'] as $statusOption)
                                    <option value="{{ $statusOption }}" {{ ($status ?? '') === $statusOption ? 'selected' : '' }}>
                                        {{ translate(ucfirst($statusOption)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-9">
                            <div class="input-group">
                                <input type="search" name="search" class="form-control"
                                       placeholder="{{ translate('Search by ID, mentor or mentee') }}"
                                       value="{{ $search ?? '' }}">
                                <div class="input-group-append">
                                    <button type="submit" class="input-group-text">{{ translate('search') }}</button>
                                </div>
                            </div>
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
                            <td>
                                {{ $booking->mentor?->display_name ?? '—' }}
                                @include('admin-views.partials._whatsapp-web-btn', [
                                    'url' => $booking->whatsappMentorUrl(),
                                    'title' => 'Mentor welcome on WhatsApp',
                                ])
                            </td>
                            <td>
                                {{ trim(($booking->mentee?->f_name ?? '') . ' ' . ($booking->mentee?->l_name ?? '')) ?: '—' }}
                                @include('admin-views.partials._whatsapp-web-btn', [
                                    'url' => $booking->whatsappMenteeUrl(),
                                    'title' => 'Student welcome on WhatsApp',
                                ])
                            </td>
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
                                <div class="btn--container justify-content-center">
                                    <a class="action-btn" href="{{ route('admin.mentor.bookings.show', $booking->id) }}" title="{{ translate('View') }}">
                                        <i class="tio-invisible"></i>
                                    </a>
                                    @if(in_array($booking->payment_status, ['pending', 'failed'], true))
                                        <a class="action-btn" href="javascript:" title="{{ translate('Send payment email') }}"
                                           data-toggle="modal" data-target="#paymentReminderModal"
                                           data-booking-id="{{ $booking->id }}"
                                           data-form-action="{{ route('admin.mentor.bookings.send-payment-email', $booking->id) }}"
                                           data-mentor-name="{{ $booking->mentor?->display_name ?? '' }}"
                                           data-session-date="{{ $booking->preferred_date?->format('d M Y') ?? '' }}"
                                           data-payment-link="{{ \App\CentralLogics\MentorBookingMailLogic::defaultPaymentLink($booking) }}">
                                            <i class="tio-email-outlined"></i>
                                        </a>
                                    @endif
                                </div>
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

    <div class="modal fade" id="paymentReminderModal" tabindex="-1" role="dialog" aria-labelledby="paymentReminderModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="post" id="paymentReminderForm" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentReminderModalLabel">{{ translate('Send payment email') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2" id="paymentReminderSummary"></p>
                    <div class="form-group mb-0">
                        <label>{{ translate('Payment link') }}</label>
                        <input type="url" name="payment_link" id="paymentReminderLink" class="form-control">
                        <small class="text-muted">{{ translate('Leave default or paste a custom Razorpay / payment URL') }}</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
                    <button type="submit" class="btn btn--primary">{{ translate('Send email') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        $('#paymentReminderModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const mentorName = button.data('mentor-name') || '—';
            const sessionDate = button.data('session-date') || '—';
            const paymentLink = button.data('payment-link') || '';
            const formAction = button.data('form-action') || '';
            $('#paymentReminderSummary').text('{{ translate('Session with') }} ' + mentorName + ' · ' + sessionDate);
            $('#paymentReminderLink').val(paymentLink);
            if (formAction) $('#paymentReminderForm').attr('action', formAction);
        });
    </script>
@endpush
