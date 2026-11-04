@extends('layouts.admin.app')

@section('title', translate('Mentor Bookings'))

@section('content')
    @php
        $tab = $tab ?? 'all';
        $filterQuery = array_filter([
            'mentor_id' => $mentorId ?? null,
            'mentee_id' => $menteeId ?? null,
            'search' => $search ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    @endphp
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/order.png') }}" class="w--24" alt="">
                </span>
                <span>
                    {{ translate('Mentor Sessions') }}
                    <a href="{{ route('admin.mentor.bookings.analytics') }}" class="btn btn-sm btn-outline-primary ml-2">{{ translate('Analytics') }}</a>
                    <span class="badge badge-soft-secondary">{{ $bookings->total() }}</span>
                </span>
            </h1>
        </div>

        <ul class="nav nav-tabs page-header-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'all' ? 'active' : '' }}"
                   href="{{ route('admin.mentor.bookings.list', array_merge($filterQuery, ['tab' => 'all'])) }}">
                    {{ translate('All') }}
                    <span class="badge badge-soft-dark ml-1">{{ $allCount ?? 0 }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'upcoming' ? 'active' : '' }}"
                   href="{{ route('admin.mentor.bookings.list', array_merge($filterQuery, ['tab' => 'upcoming'])) }}">
                    {{ translate('Upcoming') }}
                    <span class="badge badge-soft-primary ml-1">{{ $upcomingCount ?? 0 }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'past' ? 'active' : '' }}"
                   href="{{ route('admin.mentor.bookings.list', array_merge($filterQuery, ['tab' => 'past'])) }}">
                    {{ translate('Past / closed') }}
                    <span class="badge badge-soft-secondary ml-1">{{ $pastCount ?? 0 }}</span>
                </a>
            </li>
        </ul>

        <div class="card">
            <div class="card-header border-0">
                <form action="{{ route('admin.mentor.bookings.list') }}" method="GET" class="w-100">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="input-label">{{ translate('Mentor') }}</label>
                            <select name="mentor_id" class="custom-select js-select2-custom" onchange="this.form.submit()">
                                <option value="">{{ translate('All mentors') }}</option>
                                @foreach($mentors ?? [] as $mentor)
                                    <option value="{{ $mentor->id }}" {{ (int)($mentorId ?? 0) === (int)$mentor->id ? 'selected' : '' }}>
                                        {{ $mentor->display_name }}@if($mentor->username) ({{ $mentor->username }})@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="input-label">{{ translate('Student') }}</label>
                            <select name="mentee_id" class="custom-select js-select2-custom" onchange="this.form.submit()">
                                <option value="">{{ translate('All students') }}</option>
                                @foreach($students ?? [] as $student)
                                    @php
                                        $studentName = trim(($student->f_name ?? '') . ' ' . ($student->l_name ?? '')) ?: ('User #'.$student->id);
                                    @endphp
                                    <option value="{{ $student->id }}" {{ (int)($menteeId ?? 0) === (int)$student->id ? 'selected' : '' }}>
                                        {{ $studentName }}@if($student->phone) — {{ $student->phone }}@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="input-label">{{ translate('Search') }}</label>
                            <div class="input-group">
                                <input type="search" name="search" class="form-control"
                                       placeholder="{{ translate('ID, mentor, student name, phone, email') }}"
                                       value="{{ $search ?? '' }}">
                                <div class="input-group-append">
                                    <button type="submit" class="input-group-text">{{ translate('Search') }}</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <a href="{{ route('admin.mentor.bookings.list', ['tab' => $tab]) }}" class="btn btn-outline-secondary btn-block">
                                {{ translate('Clear') }}
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-borderless table-thead-bordered table-nowrap card-table">
                    <thead class="thead-light">
                    <tr>
                        <th>{{ translate('ID') }}</th>
                        <th>{{ translate('Mentor') }}</th>
                        <th>{{ translate('Student') }}</th>
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
                    @forelse($bookings as $key => $booking)
                        @php
                            $menteeName = trim(($booking->mentee?->f_name ?? '') . ' ' . ($booking->mentee?->l_name ?? '')) ?: '—';
                            $menteePhone = $booking->mentee?->phone ?? '';
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.mentor.bookings.show', $booking->id) }}">#{{ $booking->id }}</a>
                            </td>
                            <td>
                                @if($booking->mentor)
                                    <a href="{{ route('admin.mentor.edit', $booking->mentor->id) }}">
                                        {{ $booking->mentor->display_name ?? '—' }}
                                    </a>
                                @else
                                    —
                                @endif
                                @include('admin-views.partials._whatsapp-web-btn', [
                                    'url' => $booking->whatsappMentorUrl(),
                                    'title' => 'Contact mentor on WhatsApp',
                                ])
                            </td>
                            <td>
                                @if($booking->mentee_user_id)
                                    <a href="{{ route('admin.customer.view', $booking->mentee_user_id) }}">
                                        {{ $menteeName }}
                                    </a>
                                    @if($menteePhone)
                                        <br><small><a href="tel:{{ $menteePhone }}">{{ $menteePhone }}</a></small>
                                    @endif
                                @else
                                    {{ $menteeName }}
                                @endif
                                @include('admin-views.partials._whatsapp-web-btn', [
                                    'url' => $booking->whatsappMenteeUrl(),
                                    'title' => 'Contact student on WhatsApp',
                                ])
                            </td>
                            <td>{{ $booking->service?->title ?? '—' }}</td>
                            <td>
                                {{ $booking->preferred_date?->format('d M Y') ?? '—' }}
                                @if($booking->preferred_time)
                                    <br><small>{{ substr((string) $booking->preferred_time, 0, 5) }}</small>
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
                                <span class="badge badge-soft-info">{{ str_replace('_', ' ', (string) $booking->status) }}</span>
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
                                    @if($booking->mentee_user_id)
                                        <a class="action-btn" href="{{ route('admin.customer.view', $booking->mentee_user_id) }}" title="{{ translate('Open student') }}">
                                            <i class="tio-user"></i>
                                        </a>
                                    @endif
                                    @if(\App\CentralLogics\SessionCreditLogic::canReschedule($booking))
                                        <a class="action-btn" href="javascript:" title="{{ translate('Reschedule') }}"
                                           data-toggle="modal" data-target="#rescheduleBookingModal"
                                           data-booking-id="{{ $booking->id }}"
                                           data-date="{{ $booking->preferred_date?->format('Y-m-d') }}"
                                           data-time="{{ $booking->preferred_time ? substr((string) $booking->preferred_time, 0, 5) : '' }}">
                                            <i class="tio-date-range"></i>
                                        </a>
                                    @endif
                                    @if(\App\CentralLogics\SessionCreditLogic::canAdminMarkComplete($booking))
                                        <form method="post" action="{{ route('admin.mentor.bookings.complete', $booking->id) }}" class="d-inline" onsubmit="return confirm('Mark this session complete?');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success" title="{{ translate('Mark complete') }}">
                                                <i class="tio-checkmark-circle"></i> {{ translate('Complete') }}
                                            </button>
                                        </form>
                                    @endif
                                    @if(\App\CentralLogics\SessionCreditLogic::canMarkNoShow($booking))
                                        <form method="post" action="{{ route('admin.mentor.bookings.no-show', $booking->id) }}" class="d-inline" onsubmit="return confirm('Mark this session as no-show?');">
                                            @csrf
                                            <button type="submit" class="action-btn text-warning" title="{{ translate('Mark no-show') }}">
                                                <i class="tio-clear-circle-outlined"></i>
                                            </button>
                                        </form>
                                    @endif
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
                    @empty
                        <tr>
                            <td colspan="10" class="text-center p-4 text-muted">
                                {{ $tab === 'past' ? translate('No completed sessions found') : translate('No upcoming sessions found') }}
                            </td>
                        </tr>
                    @endforelse
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

    <div class="modal fade" id="rescheduleBookingModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="post" id="rescheduleBookingForm" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Reschedule session') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>{{ translate('Date') }}</label>
                        <input type="date" name="preferred_date" id="rescheduleDate" class="form-control" required>
                    </div>
                    <div class="form-group mb-0">
                        <label>{{ translate('Time') }}</label>
                        <input type="time" name="preferred_time" id="rescheduleTime" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
                    <button type="submit" class="btn btn--primary">{{ translate('Save') }}</button>
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

        $('#rescheduleBookingModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const bookingId = button.data('booking-id');
            const date = button.data('date') || '';
            const time = button.data('time') || '';
            const today = new Date().toISOString().slice(0, 10);
            $('#rescheduleDate').attr('min', today).val(date || today);
            $('#rescheduleTime').val(time);
            const action = @json(route('admin.mentor.bookings.reschedule', ['id' => '__ID__'])).replace('__ID__', bookingId);
            $('#rescheduleBookingForm').attr('action', action);
        });

        if ($.fn.select2) {
            $('.js-select2-custom').select2({ width: '100%', placeholder: 'Select…', allowClear: true });
        }
    </script>
@endpush
