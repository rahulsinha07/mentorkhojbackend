@extends('layouts.admin.app')

@section('title', translate('Session Packs'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/order.png') }}" class="w--24" alt="">
                </span>
                <span>
                    {{ translate('Mentor Session Packs') }}
                    <span class="badge badge-soft-secondary">{{ $packs->total() }}</span>
                    <a href="{{ route('admin.earnings.index') }}" class="btn btn-sm btn-outline-primary ml-2">{{ translate('View Earnings') }}</a>
                </span>
            </h1>
        </div>

        <div class="card">
            <div class="card-header border-0">
                <form action="{{ route('admin.session-packs.index') }}" method="GET" class="w-100">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="input-label">{{ translate('Search') }}</label>
                            <div class="input-group">
                                <input type="search" name="search" class="form-control"
                                       placeholder="{{ translate('Mentor, student name, phone, email, pack ID') }}"
                                       value="{{ $search ?? '' }}">
                                <div class="input-group-append">
                                    <button type="submit" class="input-group-text">{{ translate('Search') }}</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="input-label">{{ translate('Payment') }}</label>
                            <select name="payment" class="custom-select" onchange="this.form.submit()">
                                <option value="">{{ translate('All') }}</option>
                                <option value="paid" {{ ($paymentFilter ?? '') === 'paid' ? 'selected' : '' }}>{{ translate('Paid') }}</option>
                                <option value="pending" {{ ($paymentFilter ?? '') === 'pending' ? 'selected' : '' }}>{{ translate('Payment pending') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <a href="{{ route('admin.session-packs.index') }}" class="btn btn-outline-secondary btn-block">
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
                        <th>{{ translate('Pack') }}</th>
                        <th>{{ translate('Mentor') }}</th>
                        <th>{{ translate('Student') }}</th>
                        <th>{{ translate('Progress') }}</th>
                        <th class="text-right">{{ translate('Amount') }}</th>
                        <th class="text-center">{{ translate('Status') }}</th>
                        <th class="text-center">{{ translate('Payment') }}</th>
                        <th class="text-center">{{ translate('Action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($packs as $pack)
                        @php
                            $menteeName = trim(($pack->mentee?->f_name ?? '') . ' ' . ($pack->mentee?->l_name ?? '')) ?: '—';
                            $total = (int) $pack->credits_total;
                            $used = (int) $pack->credits_used;
                            $pct = $total > 0 ? min(100, round(($used / $total) * 100)) : 0;
                            $isActive = $used < $total;
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.session-packs.show', $pack->id) }}">#{{ $pack->id }}</a>
                            </td>
                            <td>
                                @if($pack->mentor)
                                    <a href="{{ route('admin.mentor.edit', $pack->mentor->id) }}">{{ $pack->mentor->display_name ?? '—' }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if($pack->mentee_user_id)
                                    <a href="{{ route('admin.customer.view', $pack->mentee_user_id) }}">{{ $menteeName }}</a>
                                @else
                                    {{ $menteeName }}
                                @endif
                            </td>
                            <td style="min-width: 180px;">
                                <div class="d-flex justify-content-between">
                                    <small>{{ $used }} / {{ $total }} {{ translate('done') }}</small>
                                    <small class="text-muted">{{ $pct }}%</small>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar {{ $isActive ? 'bg-primary' : 'bg-success' }}" style="width: {{ $pct }}%"></div>
                                </div>
                            </td>
                            <td class="text-right">
                                {{ Helpers::set_symbol($pack->fund_total) }}
                            </td>
                            <td class="text-center">
                                @if($isActive)
                                    <span class="badge badge-soft-primary">{{ translate('Active') }}</span>
                                @else
                                    <span class="badge badge-soft-success">{{ translate('Completed') }}</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($pack->isPaymentConfirmed())
                                    <span class="badge badge-soft-success">{{ translate('Paid') }}</span>
                                @else
                                    <span class="badge badge-soft-warning">{{ translate('Pending') }}</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <a class="action-btn" href="{{ route('admin.session-packs.show', $pack->id) }}" title="{{ translate('View sessions') }}">
                                    <i class="tio-invisible"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center p-4 text-muted">
                                {{ translate('No session packs found') }}
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                {!! $packs->links() !!}
            </div>
        </div>
    </div>
@endsection
