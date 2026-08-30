@extends('layouts.admin.app')

@section('title', translate('Booking') . ' #' . $booking->id)

@section('content')
    <div class="content container-fluid">
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h1 class="page-header-title mb-0">
                <span>{{ translate('Mentor Booking') }} #{{ $booking->id }}</span>
            </h1>
            <a href="{{ route('admin.invoices.create', ['booking_id' => $booking->id]) }}" class="btn btn--primary">{{ translate('Generate Tax Invoice') }}</a>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="card-title mb-0">{{ translate('Session details') }}</h5></div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">{{ translate('Mentor') }}</dt>
                            <dd class="col-sm-8">
                                {{ $booking->mentor?->display_name ?? '—' }}
                                @include('admin-views.partials._whatsapp-web-btn', [
                                    'url' => $booking->whatsappMentorUrl(),
                                    'title' => 'Mentor welcome on WhatsApp',
                                ])
                            </dd>
                            <dt class="col-sm-4">{{ translate('Mentee') }}</dt>
                            <dd class="col-sm-8">
                                @if($booking->mentee)
                                    <a href="{{ route('admin.customer.view', $booking->mentee->id) }}">
                                        {{ trim(($booking->mentee->f_name ?? '') . ' ' . ($booking->mentee->l_name ?? '')) }}
                                    </a>
                                    @if($booking->mentee->email)
                                        <br><small class="text-muted">{{ $booking->mentee->email }}</small>
                                    @endif
                                    @include('admin-views.partials._whatsapp-web-btn', [
                                        'url' => $booking->whatsappMenteeUrl(),
                                        'title' => 'Student welcome on WhatsApp',
                                    ])
                                @else
                                    —
                                @endif
                            </dd>
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
                            <dd><span class="badge badge-soft-{{ $booking->payment_status === 'paid' ? 'success' : ($booking->payment_status === 'failed' ? 'danger' : 'warning') }}">{{ $booking->payment_status }}</span></dd>
                            <dt>{{ translate('Booking status') }}</dt>
                            <dd><span class="badge badge-soft-info">{{ $booking->status }}</span></dd>
                            <dt>{{ translate('Source') }}</dt>
                            <dd>
                                <span class="badge badge-soft-{{ ($booking->booking_source ?? 'paid') === 'credit' ? 'primary' : 'secondary' }}">
                                    {{ $booking->booking_source ?? 'paid' }}
                                </span>
                            </dd>
                            @if($booking->payment_reminder_email_sent_at ?? null)
                                <dt>{{ translate('Payment email sent') }}</dt>
                                <dd>{{ $booking->payment_reminder_email_sent_at->format('d M Y H:i') }}</dd>
                            @endif
                            @if($booking->legacy_order_id)
                                <dt>{{ translate('Legacy order') }}</dt>
                                <dd><a href="{{ route('admin.orders.details', $booking->legacy_order_id) }}">#{{ $booking->legacy_order_id }}</a></dd>
                            @endif
                        </dl>
                        @if(\App\CentralLogics\SessionCreditLogic::canMarkComplete($booking))
                            <form method="post" action="{{ route('admin.mentor.bookings.complete', $booking->id) }}" class="mt-3" onsubmit="return confirm('Mark this session complete?');">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="tio-checkmark-circle"></i> {{ translate('Mark complete') }}
                                </button>
                            </form>
                        @endif
                        @if(\App\CentralLogics\SessionCreditLogic::canReschedule($booking))
                            <button type="button" class="btn btn-sm btn-soft-info mt-2" data-toggle="modal" data-target="#adminRescheduleModal">
                                <i class="tio-date-range"></i> {{ translate('Reschedule') }}
                            </button>
                        @endif
                        @if(in_array($booking->payment_status, ['pending', 'failed'], true))
                            <button type="button" class="btn btn-sm btn--primary mt-3" data-toggle="modal" data-target="#paymentReminderModal"
                                    data-booking-id="{{ $booking->id }}"
                                    data-mentor-name="{{ $booking->mentor?->display_name ?? '' }}"
                                    data-session-date="{{ $booking->preferred_date?->format('d M Y') ?? '' }}"
                                    data-payment-link="{{ \App\CentralLogics\MentorBookingMailLogic::defaultPaymentLink($booking) }}">
                                <i class="tio-email-outlined"></i> {{ translate('Send payment email') }}
                            </button>
                        @endif
                    </div>
                </div>
                <a href="{{ route('admin.mentor.bookings.list') }}" class="btn btn-secondary">{{ translate('Back') }}</a>
            </div>
        </div>
    </div>

    <div class="modal fade" id="adminRescheduleModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="post" action="{{ route('admin.mentor.bookings.reschedule', $booking->id) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Reschedule session') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>{{ translate('Date') }}</label>
                        <input type="date" name="preferred_date" class="form-control" id="adminRescheduleDate"
                               value="{{ $booking->preferred_date?->format('Y-m-d') }}" required>
                    </div>
                    <div class="form-group mb-0">
                        <label>{{ translate('Time') }}</label>
                        <input type="time" name="preferred_time" class="form-control"
                               value="{{ $booking->preferred_time ? substr($booking->preferred_time, 0, 5) : '' }}" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
                    <button type="submit" class="btn btn--primary">{{ translate('Save') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="paymentReminderModal" tabindex="-1" role="dialog" aria-labelledby="paymentReminderModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="post" id="paymentReminderForm" action="{{ route('admin.mentor.bookings.send-payment-email', $booking->id) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentReminderModalLabel">{{ translate('Send payment email') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2" id="paymentReminderSummary"></p>
                    <div class="form-group mb-0">
                        <label>{{ translate('Payment link') }}</label>
                        <input type="url" name="payment_link" id="paymentReminderLink" class="form-control"
                               value="{{ \App\CentralLogics\MentorBookingMailLogic::defaultPaymentLink($booking) }}">
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
        (function () {
            const d = new Date();
            const pad = (n) => String(n).padStart(2, '0');
            const min = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
            const el = document.getElementById('adminRescheduleDate');
            if (el) el.setAttribute('min', min);
        })();
        $('#paymentReminderModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            if (!button || !button.length) return;
            const mentorName = button.data('mentor-name') || '—';
            const sessionDate = button.data('session-date') || '—';
            const paymentLink = button.data('payment-link') || '';
            $('#paymentReminderSummary').text('{{ translate('Session with') }} ' + mentorName + ' · ' + sessionDate);
            if (paymentLink) $('#paymentReminderLink').val(paymentLink);
        });
    </script>
@endpush
