@extends('layouts.admin.app')

@section('title', translate('Bookings'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/order.png') }}" class="w--20" alt="">
                </span>
                <span>
                    {{ translate('All Bookings') }}
                    <span class="badge badge-pill badge-soft-secondary ml-2">{{ $counts['all'] ?? $bookings->total() }}</span>
                </span>
            </h1>
            <p class="text-muted mb-0">{{ translate('Mentor sessions and demo booking leads in one place.') }}</p>
        </div>

        <div class="row mb-3">
            @foreach([
                'all' => translate('All'),
                'mentor' => translate('Mentor sessions'),
                'demo' => translate('Demo bookings'),
            ] as $tabKey => $tabLabel)
                <div class="col-md-4 col-12 mb-2">
                    <a href="{{ route('admin.pos.orders', array_filter([
                        'type' => $tabKey === 'all' ? null : $tabKey,
                        'search' => $search ?: null,
                        'start_date' => $startDate ?: null,
                        'end_date' => $endDate ?: null,
                    ])) }}"
                       class="card text-decoration-none h-100 {{ ($type ?? 'all') === $tabKey ? 'border-primary' : '' }}">
                        <div class="card-body py-3">
                            <div class="text-muted small">{{ $tabLabel }}</div>
                            <div class="h4 mb-0">{{ $counts[$tabKey] ?? 0 }}</div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-header shadow flex-wrap p-20px border-0">
                <h5 class="form-bold w-100 mb-3">{{ translate('Select Date Range') }}</h5>
                <form class="w-100" method="GET">
                    @if(($type ?? 'all') !== 'all')
                        <input type="hidden" name="type" value="{{ $type }}">
                    @endif
                    <div class="row g-3 g-sm-4 g-md-3 g-lg-4">
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <div class="input-date-group">
                                <label class="input-label" for="start_date">{{ translate('Start Date') }}</label>
                                <label class="input-date">
                                    <input type="text" id="start_date" name="start_date" value="{{ $startDate }}"
                                           class="js-flatpickr form-control flatpickr-custom min-h-45px" placeholder="yy-mm-dd"
                                           data-hs-flatpickr-options='{ "dateFormat": "Y-m-d"}'>
                                </label>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <div class="input-date-group">
                                <label class="input-label" for="end_date">{{ translate('End Date') }}</label>
                                <label class="input-date">
                                    <input type="text" id="end_date" name="end_date" value="{{ $endDate }}"
                                           class="js-flatpickr form-control flatpickr-custom min-h-45px" placeholder="yy-mm-dd"
                                           data-hs-flatpickr-options='{ "dateFormat": "Y-m-d"}'>
                                </label>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-12 col-lg-6 __btn-row">
                            <a href="{{ route('admin.pos.orders', ($type ?? 'all') !== 'all' ? ['type' => $type] : []) }}"
                               class="btn w-100 btn--reset min-h-45px">{{ translate('clear') }}</a>
                            <button type="submit" class="btn w-100 btn--primary min-h-45px">{{ translate('show data') }}</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card-body p-20px">
                <div class="order-top">
                    <div class="card--header">
                        <form action="{{ url()->current() }}" method="GET">
                            @if(($type ?? 'all') !== 'all')
                                <input type="hidden" name="type" value="{{ $type }}">
                            @endif
                            @if($startDate)
                                <input type="hidden" name="start_date" value="{{ $startDate }}">
                            @endif
                            @if($endDate)
                                <input type="hidden" name="end_date" value="{{ $endDate }}">
                            @endif
                            <div class="input-group">
                                <input id="datatableSearch_" type="search" name="search"
                                       class="form-control"
                                       placeholder="{{ translate('Search by ref, name, phone, email, mentor or status') }}"
                                       aria-label="Search"
                                       value="{{ $search }}" autocomplete="off">
                                <div class="input-group-append">
                                    <button type="submit" class="input-group-text">
                                        {{ translate('Search') }}
                                    </button>
                                </div>
                            </div>
                        </form>

                        <div class="hs-unfold mr-2">
                            <a class="js-hs-unfold-invoker btn btn-sm btn-outline-primary-2 dropdown-toggle min-height-40"
                               href="javascript:;"
                               data-hs-unfold-options='{"target": "#usersExportDropdown","type": "css-animation"}'>
                                <i class="tio-download-to mr-1"></i> {{ translate('export') }}
                            </a>
                            <div id="usersExportDropdown"
                                 class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                                <span class="dropdown-header">{{ translate('download') }} {{ translate('options') }}</span>
                                <a class="dropdown-item"
                                   href="{{ route('admin.pos.orders.export', array_filter([
                                       'type' => ($type ?? 'all') !== 'all' ? $type : null,
                                       'start_date' => Request::get('start_date'),
                                       'end_date' => Request::get('end_date'),
                                       'search' => Request::get('search'),
                                   ])) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                         src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                         alt="Image Description">
                                    {{ translate('excel') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive datatable-custom">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                        <tr>
                            <th>{{ translate('#') }}</th>
                            <th>{{ translate('Type') }}</th>
                            <th>{{ translate('Reference') }}</th>
                            <th>{{ translate('Created') }}</th>
                            <th>{{ translate('Customer') }}</th>
                            <th>{{ translate('Mentor') }} / {{ translate('Category') }}</th>
                            <th>{{ translate('Service') }} / {{ translate('Stage') }}</th>
                            <th>{{ translate('Session Date') }}</th>
                            <th class="text-right">{{ translate('Amount') }}</th>
                            <th class="text-center">{{ translate('Status') }}</th>
                            <th class="text-center">{{ translate('Payment') }}</th>
                            <th class="text-center">{{ translate('actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($bookings as $key => $row)
                            <tr>
                                <td>{{ $bookings->firstItem() + $key }}</td>
                                <td>
                                    @if($row['kind'] === 'mentor')
                                        <span class="badge badge-soft-primary">{{ translate('Mentor session') }}</span>
                                    @else
                                        <span class="badge badge-soft-info">{{ translate('Demo booking') }}</span>
                                    @endif
                                </td>
                                <td><a href="{{ $row['show_url'] }}">{{ $row['ref'] }}</a></td>
                                <td>{{ $row['created_at']?->format('d M Y') ?? '—' }}</td>
                                <td>
                                    @if($row['customer_url'])
                                        <a href="{{ $row['customer_url'] }}">{{ $row['customer_name'] }}</a>
                                    @else
                                        {{ $row['customer_name'] }}
                                    @endif
                                    @if($row['customer_phone'])
                                        <div class="text-sm">
                                            <a href="tel:{{ $row['customer_phone'] }}">{{ $row['customer_phone'] }}</a>
                                        </div>
                                    @endif
                                    @if($row['customer_email'])
                                        <div class="text-sm text-muted">{{ $row['customer_email'] }}</div>
                                    @endif
                                </td>
                                <td>{{ $row['mentor_or_category'] }}</td>
                                <td>{{ $row['service_or_stage'] }}</td>
                                <td>{{ $row['session_date'] ?: '—' }}</td>
                                <td class="text-right">
                                    @if($row['amount'] !== null)
                                        {{ Helpers::set_symbol($row['amount']) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-soft-secondary">{{ $row['status'] }}</span>
                                </td>
                                <td class="text-center">
                                    @if($row['payment_status'])
                                        <span class="badge badge-soft-{{ $row['payment_status'] === 'paid' ? 'success' : ($row['payment_status'] === 'failed' ? 'danger' : 'warning') }}">
                                            {{ $row['payment_status'] }}
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <div class="btn--container justify-content-center">
                                        <a class="action-btn btn--primary btn-outline-primary"
                                           href="{{ $row['show_url'] }}" title="{{ translate('View') }}">
                                            <i class="tio-visible-outlined"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12">
                                    <div class="text-center p-4">
                                        <img class="w-120px mb-3"
                                             src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}"
                                             alt="{{ translate('Image Description') }}">
                                        <p class="mb-0">{{ translate('No_data_to_show') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="card-footer px-0">
                    {!! $bookings->links() !!}
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin/js/flatpicker.js') }}"></script>
@endpush
