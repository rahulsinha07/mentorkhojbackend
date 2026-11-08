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
                                            @if($booking->mentor_payout_reference)
                                                <br><small class="text-muted" style="font-family: monospace;">{{ strtoupper($booking->mentor_payout_method ?? 'ref') }}: {{ $booking->mentor_payout_reference }}</small>
                                            @endif
                                        @else
                                            <span class="badge badge-soft-warning">{{ translate('Pending') }}</span>
                                        @endif
                                    </div>
                                    @if($booking->mentor_payout_status === 'paid')
                                        <form method="post" action="{{ route('admin.session-packs.toggle-mentor-payout', $booking->id) }}" class="d-inline"
                                              onsubmit="return confirm('{{ translate('Mark this session\'s mentor payout as pending again? This clears the recorded reference.') }}');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-warning mt-1">{{ translate('Mark pending') }}</button>
                                        </form>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-success mt-1" data-toggle="modal" data-target="#payoutModal-{{ $booking->id }}">
                                            {{ translate('Mark payout done') }}
                                        </button>
                                    @endif
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

        @php $mentorUpi = $credit->mentor?->upiId(); @endphp
        @foreach($bookings as $booking)
            @if($booking->status === 'completed' && $booking->mentor_payout_status !== 'paid')
                @php
                    $upiAmount = number_format((float) $booking->mentor_net, 2, '.', '');
                    $upiNote = 'MentorKhoj session payout #' . $booking->id;
                    $upiLink = $mentorUpi
                        ? 'upi://pay?pa=' . urlencode($mentorUpi)
                            . '&pn=' . urlencode($credit->mentor->display_name ?? 'Mentor')
                            . '&am=' . urlencode($upiAmount)
                            . '&cu=INR&tn=' . urlencode($upiNote)
                        : null;
                @endphp
                <div class="modal fade" id="payoutModal-{{ $booking->id }}" tabindex="-1" role="dialog">
                    <div class="modal-dialog" role="document">
                        <form method="post" action="{{ route('admin.session-packs.toggle-mentor-payout', $booking->id) }}">
                            @csrf
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">{{ translate('Record mentor payout') }} — {{ translate('Session') }} #{{ $booking->id }}</h5>
                                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                </div>
                                <div class="modal-body">
                                    <p class="mb-2">{{ translate('Amount owed for this session') }}: <strong>{{ Helpers::set_symbol($booking->mentor_net) }}</strong></p>

                                    @if($mentorUpi)
                                        <p class="mb-2">
                                            <small class="text-muted d-block">{{ translate('Mentor UPI ID') }}: <span style="font-family: monospace;">{{ $mentorUpi }}</span></small>
                                            <a href="{{ $upiLink }}" class="btn btn-sm btn-outline-primary mt-1" target="_blank" rel="noopener">
                                                {{ translate('Open in UPI app (GPay/PhonePe) to pay') }} {{ Helpers::set_symbol($booking->mentor_net) }}
                                            </a>
                                            <br><small class="text-muted">{{ translate('On mobile, this opens your UPI app with the amount pre-filled. After paying, copy the reference/UTR number it shows and paste it below.') }}</small>
                                        </p>
                                    @else
                                        <p class="text-muted"><small>{{ translate('This mentor has not saved a UPI ID yet — pay via your usual method and record the reference below.') }}</small></p>
                                    @endif

                                    <div class="form-group mt-3">
                                        <label>{{ translate('Payment method') }}</label>
                                        <select name="method" class="form-control">
                                            <option value="upi" selected>{{ translate('UPI') }}</option>
                                            <option value="bank">{{ translate('Bank transfer') }}</option>
                                            <option value="cash">{{ translate('Cash') }}</option>
                                            <option value="other">{{ translate('Other') }}</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>{{ translate('UPI reference / UTR / transaction ID') }} <small class="text-muted">({{ translate('optional, but recommended') }})</small></label>
                                        <input type="text" name="reference" class="form-control" placeholder="{{ translate('e.g. 123456789012') }}" maxlength="255">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                                    <button type="submit" class="btn btn-success">{{ translate('Confirm payout') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endforeach
    </div>
@endsection
