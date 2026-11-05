@extends('layouts.admin.app')

@section('title', translate('Earnings'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/order.png') }}" class="w--24" alt="">
                </span>
                <span>{{ translate('Mentor Coach Earnings') }}</span>
            </h1>
            <a href="{{ route('admin.session-packs.index') }}" class="btn btn-sm btn-outline-secondary">
                {{ translate('Back to session packs') }}
            </a>
        </div>

        <div class="row">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <small class="text-muted">{{ translate('Total Revenue — funds collected, incl. GST (all time)') }}</small>
                        <h4 class="mb-0">{{ Helpers::set_symbol($totalRevenue) }}</h4>
                        <small class="text-muted">{{ translate('This month') }}: {{ Helpers::set_symbol($thisMonthRevenue) }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card h-100 border-primary">
                    <div class="card-body">
                        <small class="text-muted">{{ translate('Platform fee — on session price, excl. GST') }}</small>
                        <h4 class="mb-0 text-primary">{{ Helpers::set_symbol($platformEarning) }}</h4>
                        <small class="text-muted">{{ translate('This month') }}: {{ Helpers::set_symbol($thisMonthEarning) }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <small class="text-muted">{{ translate('GST collected — included in Revenue above') }} ({{ rtrim(rtrim(number_format($gstRate, 2), '0'), '.') }}%)</small>
                        <h4 class="mb-0">{{ Helpers::set_symbol($gstOnEarning) }}</h4>
                        <small class="text-muted">{{ translate('This month') }}: {{ Helpers::set_symbol($thisMonthGst) }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <small class="text-muted">{{ translate('Paid out to mentors — delivered sessions only') }}</small>
                        <h4 class="mb-0">{{ Helpers::set_symbol($mentorPayout) }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <p class="text-muted small mb-3">
            {{ translate('Revenue and platform fee count the moment a pack\'s payment is confirmed — i.e. funds already collected — whether or not its sessions have been delivered yet. Total Revenue includes GST collected on top of the session price (actual cash in); for a manually-added session pack, the amount entered is the mentor\'s session price, and GST is computed on top of it here. Platform fee is taken only on the session price, never on GST, so GST never reduces what the mentor earns. "Paid out to mentors" only counts sessions actually delivered, since a mentor can\'t be paid for undelivered work.') }}
        </p>

        <div class="card mb-3">
            <div class="card-header border-0">
                <h5 class="mb-0">{{ translate('Session packs — overall, till now') }}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-2 col-sm-6 mb-3">
                        <small class="text-muted d-block">{{ translate('Active packs') }}</small>
                        <h5 class="mb-0">{{ $activePacksCount }}</h5>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-3">
                        <small class="text-muted d-block">{{ translate('Total packs (paid)') }}</small>
                        <h5 class="mb-0">{{ $totalPacksCount }}</h5>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-3">
                        <small class="text-muted d-block">{{ translate('Total amount added') }}</small>
                        <h5 class="mb-0">{{ Helpers::set_symbol($totalAmountAdded) }}</h5>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-3">
                        <small class="text-muted d-block">{{ translate('Sessions added') }}</small>
                        <h5 class="mb-0">{{ $totalSessionsAdded }}</h5>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-3">
                        <small class="text-muted d-block">{{ translate('Sessions completed') }}</small>
                        <h5 class="mb-0 text-success">{{ $totalSessionsCompleted }}</h5>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-3">
                        <small class="text-muted d-block">{{ translate('Sessions pending') }}</small>
                        <h5 class="mb-0 text-warning">{{ $totalSessionsPending }}</h5>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header border-0">
                <h5 class="mb-0">{{ translate('Monthly trend (last 12 months)') }}</h5>
            </div>
            <div class="card-body">
                <div id="earnings-line-chart" style="min-height: 320px;"></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-0">
                <h5 class="mb-0">{{ translate('Monthly breakdown') }}</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-borderless table-thead-bordered table-nowrap card-table">
                    <thead class="thead-light">
                    <tr>
                        <th>{{ translate('Month') }}</th>
                        <th class="text-right">{{ translate('Sessions added') }}</th>
                        <th class="text-right">{{ translate('Completed') }}</th>
                        <th class="text-right">{{ translate('Pending') }}</th>
                        <th class="text-right">{{ translate('Revenue') }}</th>
                        <th class="text-right">{{ translate('Platform fee') }}</th>
                        <th class="text-right">{{ translate('Mentor share') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach(array_reverse($monthlyBreakdown) as $row)
                        <tr @if($row['label'] === now()->format('M Y')) class="table-active" @endif>
                            <td>{{ $row['label'] }}</td>
                            <td class="text-right">{{ $row['sessions_added'] }}</td>
                            <td class="text-right">{{ $row['sessions_completed'] }}</td>
                            <td class="text-right">{{ $row['sessions_pending'] }}</td>
                            <td class="text-right">{{ Helpers::set_symbol($row['revenue']) }}</td>
                            <td class="text-right">{{ Helpers::set_symbol($row['fee']) }}</td>
                            <td class="text-right">{{ Helpers::set_symbol($row['net']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script src="{{asset('/public/assets/admin/js/apex-charts/apexcharts.js')}}"></script>
@endpush

@push('script_2')
    <script>
        "use strict";
        var earningsChartOptions = {
            series: [
                { name: "{{ translate('Total Revenue') }}", data: @json($revenueSeries) },
                { name: "{{ translate('Mentor Coach Earning') }}", data: @json($earningSeries) },
                { name: "{{ translate('Paid to Mentors') }}", data: @json($payoutSeries) }
            ],
            chart: {
                height: 320,
                type: 'line',
                zoom: { enabled: false },
                toolbar: { show: false },
            },
            dataLabels: { enabled: false },
            colors: ['#107980', '#2c7be5', '#87bcbf'],
            stroke: { curve: 'smooth', width: 3 },
            xaxis: { categories: @json($months) },
            grid: {
                show: true,
                padding: { bottom: 0 },
                borderColor: "#d9e7ef",
                strokeDashArray: 7,
                xaxis: { lines: { show: true } }
            },
            yaxis: { tickAmount: 4 }
        };
        var earningsChart = new ApexCharts(document.querySelector("#earnings-line-chart"), earningsChartOptions);
        earningsChart.render();
    </script>
@endpush
