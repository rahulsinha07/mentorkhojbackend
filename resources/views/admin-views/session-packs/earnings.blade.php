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
                        <small class="text-muted">{{ translate('Total Revenue (all time)') }}</small>
                        <h4 class="mb-0">{{ Helpers::set_symbol($totalRevenue) }}</h4>
                        <small class="text-muted">{{ translate('This month') }}: {{ Helpers::set_symbol($thisMonthRevenue) }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card h-100 border-primary">
                    <div class="card-body">
                        <small class="text-muted">{{ translate('Mentor Coach Earning (platform fee)') }}</small>
                        <h4 class="mb-0 text-primary">{{ Helpers::set_symbol($platformEarning) }}</h4>
                        <small class="text-muted">{{ translate('This month') }}: {{ Helpers::set_symbol($thisMonthEarning) }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <small class="text-muted">{{ translate('GST on session price') }} ({{ rtrim(rtrim(number_format($gstRate, 2), '0'), '.') }}%)</small>
                        <h4 class="mb-0">{{ Helpers::set_symbol($gstOnEarning) }}</h4>
                        <small class="text-muted">{{ translate('This month') }}: {{ Helpers::set_symbol($thisMonthGst) }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <small class="text-muted">{{ translate('Paid out to mentors') }}</small>
                        <h4 class="mb-0">{{ Helpers::set_symbol($mentorPayout) }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-0">
                <h5 class="mb-0">{{ translate('Monthly trend (last 12 months)') }}</h5>
            </div>
            <div class="card-body">
                <div id="earnings-line-chart" style="min-height: 320px;"></div>
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
