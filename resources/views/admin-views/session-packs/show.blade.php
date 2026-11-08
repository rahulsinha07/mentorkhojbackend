@extends('layouts.admin.app')

@section('title', translate('Session Pack'))

@section('content')
    @php
        $menteeName = trim(($credit->mentee?->f_name ?? '') . ' ' . ($credit->mentee?->l_name ?? '')) ?: '—';
        $total = (int) $credit->credits_total;
        $used = (int) $credit->credits_used;
        $pct = $total > 0 ? min(100, round(($used / $total) * 100)) : 0;
        $completedBookings = $bookings->where('status', 'completed');
        $paidOutCount = $completedBookings->where('mentor_payout_status', 'paid')->count();
    @endphp
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/order.png') }}" class="w--24" alt="">
                </span>
                <span>{{ translate('Session Pack') }} #{{ $credit->id }}</span>
            </h1>
            <a href="{{ route('admin.session-packs.index') }}" class="btn btn-sm btn-outline-secondary">
                {{ translate('Back to all packs') }}
            </a>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <small class="text-muted">{{ translate('Mentor') }}</small>
                        <div>
                            @if($credit->mentor)
                                <a href="{{ route('admin.mentor.edit', $credit->mentor->id) }}">{{ $credit->mentor->display_name ?? '—' }}</a>
                            @else
                                —
                            @endif
                        </div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">{{ translate('Student') }}</small>
                        <div>
                            @if($credit->mentee_user_id)
                                <a href="{{ route('admin.customer.view', $credit->mentee_user_id) }}">{{ $menteeName }}</a>
                            @else
                                {{ $menteeName }}
                            @endif
                        </div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">{{ translate('Amount') }}</small>
                        <div>{{ Helpers::set_symbol($credit->fund_total) }}</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">{{ translate('Platform fee') }}</small>
                        <div>{{ rtrim(rtrim(number_format($credit->effectiveFeePercent(), 2), '0'), '.') }}%</div>
                    </div>
                </div>
                <hr>
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <small class="text-muted d-block">{{ translate('Payment status') }}</small>
                        @if($credit->isPaymentConfirmed())
                            <span class="badge badge-soft-success">{{ translate('Paid') }}</span>
                            @if($credit->payment_confirmed_at)
                                <small class="text-muted ml-1">{{ translate('on') }} {{ $credit->payment_confirmed_at->format('d M Y, h:i A') }}</small>
                            @endif
                        @else
                            <span class="badge badge-soft-warning">{{ translate('Payment pending') }}</span>
                        @endif
                    </div>
                    <div class="col-md-6 text-md-right">
                        <form method="post" action="{{ route('admin.session-packs.toggle-payment', $credit->id) }}" class="d-inline"
                              onsubmit="return confirm('{{ $credit->isPaymentConfirmed() ? translate('Mark this pack\'s payment as pending again?') : translate('Confirm payment has been received for this pack?') }}');">
                            @csrf
                            @if($credit->isPaymentConfirmed())
                                <button type="submit" class="btn btn-sm btn-outline-warning">{{ translate('Mark payment pending') }}</button>
                            @else
                                <button type="submit" class="btn btn-sm btn-success">{{ translate('Mark payment received') }}</button>
                            @endif
                        </form>
                    </div>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <strong>{{ $used }} {{ translate('of') }} {{ $total }} {{ translate('sessions done') }}</strong>
                    <span class="text-muted">{{ $pct }}%</span>
                </div>
                <div class="progress mt-1" style="height: 8px;">
                    <div class="progress-bar {{ $used < $total ? 'bg-primary' : 'bg-success' }}" style="width: {{ $pct }}%"></div>
                </div>
                @if($completedBookings->count() > 0)
                    <div class="mt-2">
                        <small class="text-muted">
                            {{ translate('Mentor payout') }}:
                            <strong>{{ $paidOutCount }} {{ translate('of') }} {{ $completedBookings->count() }}</strong>
                            {{ translate('completed sessions paid out') }}
                        </small>
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header border-0">
                <h5 class="mb-0">{{ translate('Sessions') }}</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-borderless table-thead-bordered table-nowrap card-table">
                    <thead class="thead-light">
                    <tr>
                        <th>{{ translate('#') }}</th>
                        <th>{{ translate('Date') }}</th>
                        <th>{{ translate('Service') }}</th>
                        <th class="text-center">{{ translate('Status') }}</th>
                        <th class="text-center">{{ translate('Mentor payout') }}</th>
                        <th class="text-center">{{ translate('Action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($bookings as $booking)
                        <tr>
                            <td>
                                <a href="{{ route('admin.mentor.bookings.show', $booking->id) }}">#{{ $booking->id }}</a>
                                @if($booking->session_number)
                                    <br><small class="text-muted">{{ translate('Session') }} {{ $booking->session_number }}@if($booking->total_sessions)/{{ $booking->total_sessions }}@endif</small>
                                @endif
                            </td>
                            <td>
                                {{ $booking->scheduled_at?->format('d M Y, h:i A') ?? ($booking->preferred_date?->format('d M Y') ?? '—') }}
                            </td>
                            <td>{{ $booking->service?->title ?? '—' }}</td>
                            <td class="text-center">
                                <span class="badge badge-soft-{{ $booking->status === 'completed' ? 'success' : ($booking->status === 'cancelled' || $booking->status === 'no_show' ? 'danger' : 'info') }}">
                                    {{ str_replace('_', ' ', (string) $booking->status) }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if($booking->status !== 'completed')
                                    <span class="text-muted">—</span>
                                @else
                                    <div>
                                        @if($booking->mentor_payout_status === 'paid')
                                            <span class="badge badge-soft-success">{{ translate('Paid') }}</span>
                                            @if($booking->mentor_paid_at)
                                                <br><small class="text-muted">{{ $booking->mentor_paid_at->format('d M Y') }}</small>
                                            @endif
                                        @else
                                            <span class="badge badge-soft-warning">{{ translate('Pending') }}</span>
                                        @endif
                                    </div>
                                    <form method="post" action="{{ route('admin.session-packs.toggle-mentor-payout', $booking->id) }}" class="d-inline"
                                          onsubmit="return confirm('{{ $booking->mentor_payout_status === 'paid' ? translate('Mark this session\'s mentor payout as pending again?') : translate('Confirm the mentor has been paid for this session?') }}');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm {{ $booking->mentor_payout_status === 'paid' ? 'btn-outline-warning' : 'btn-outline-success' }} mt-1">
                                            {{ $booking->mentor_payout_status === 'paid' ? translate('Mark pending') : translate('Mark payout done') }}
                                        </button>
                                    </form>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($booking->status === 'completed' && \App\CentralLogics\SessionCreditLogic::canUnmarkComplete($booking))
                                    <form method="post" action="{{ route('admin.session-packs.toggle-complete', $booking->id) }}" class="d-inline"
                                          onsubmit="return confirm('{{ translate('Mark this session back as incomplete? This reverses the credit it consumed.') }}');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-warning" title="{{ translate('Mark incomplete') }}">
                                            {{ translate('Mark incomplete') }}
                                        </button>
                                    </form>
                                @elseif($booking->status === 'completed')
                                    <span class="text-muted" title="{{ translate('Mentor already paid — mark payout pending first to reverse') }}">—</span>
                                @elseif(\App\CentralLogics\SessionCreditLogic::canAdminMarkComplete($booking))
                                    <form method="post" action="{{ route('admin.session-packs.toggle-complete', $booking->id) }}" class="d-inline"
                                          onsubmit="return confirm('{{ translate('Mark this session complete?') }}');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success" title="{{ translate('Mark complete') }}">
                                            {{ translate('Mark complete') }}
                                        </button>
                                    </form>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center p-4 text-muted">
                                {{ translate('No sessions scheduled yet for this pack') }}
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
