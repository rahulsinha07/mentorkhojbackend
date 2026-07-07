$(document).on('ready', function () {

    $('.js-nav-scroller').each(function () {
        new HsNavScroller($(this)).init()
    });

    $('.js-daterangepicker').daterangepicker();

    $('.js-daterangepicker-times').daterangepicker({
        timePicker: true,
        startDate: moment().startOf('hour'),
        endDate: moment().startOf('hour').add(32, 'hour'),
        locale: {
            format: 'M/DD hh:mm A'
        }
    });

    var start = moment();
    var end = moment();

    function cb(start, end) {
        $('#js-daterangepicker-predefined .js-daterangepicker-predefined-preview').html(start.format('MMM D') + ' - ' + end.format('MMM D, YYYY'));
    }

    $('#js-daterangepicker-predefined').daterangepicker({
        startDate: start,
        endDate: end,
        ranges: {
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        }
    }, cb);

    cb(start, end);

    $('.js-chart').each(function () {
        $.HSCore.components.HSChartJS.init($(this));
    });

    var updatingChart = $.HSCore.components.HSChartJS.init($('#updatingData'));

    $('[data-toggle="chart"]').click(function (e) {
        let keyDataset = $(e.currentTarget).attr('data-datasets')

        updatingChart.data.datasets.forEach(function (dataset, key) {
            dataset.data = updatingChartDatasets[keyDataset][key];
        });
        updatingChart.update();
    })

    function generateHoursData() {
        var data = [];
        var dt = moment().subtract(365, 'days').startOf('day');
        var end = moment().startOf('day');
        while (dt <= end) {
            data.push({
                x: dt.format('YYYY-MM-DD'),
                y: dt.format('e'),
                d: dt.format('YYYY-MM-DD'),
                v: Math.random() * 24
            });
            dt = dt.add(1, 'day');
        }
        return data;
    }

    $.HSCore.components.HSChartMatrixJS.init($('.js-chart-matrix'), {
        data: {
            datasets: [{
                label: 'Commits',
                data: generateHoursData(),
                width: function (ctx) {
                    var a = ctx.chart.chartArea;
                    return (a.right - a.left) / 70;
                },
                height: function (ctx) {
                    var a = ctx.chart.chartArea;
                    return (a.bottom - a.top) / 10;
                }
            }]
        },
        options: {
            tooltips: {
                callbacks: {
                    title: function () {
                        return '';
                    },
                    label: function (item, data) {
                        var v = data.datasets[item.datasetIndex].data[item.index];

                        if (v.v.toFixed() > 0) {
                            return '<span class="font-weight-bold">' + v.v.toFixed() + ' hours</span> on ' + v.d;
                        } else {
                            return '<span class="font-weight-bold">No time</span> on ' + v.d;
                        }
                    }
                }
            },
            scales: {
                xAxes: [{
                    position: 'bottom',
                    type: 'time',
                    offset: true,
                    time: {
                        unit: 'week',
                        round: 'week',
                        displayFormats: {
                            week: 'MMM'
                        }
                    },
                    ticks: {
                        "labelOffset": 20,
                        "maxRotation": 0,
                        "minRotation": 0,
                        "fontSize": 12,
                        "fontColor": "rgba(22, 52, 90, 0.5)",
                        "maxTicksLimit": 12,
                    },
                    gridLines: {
                        display: false
                    }
                }],
                yAxes: [{
                    type: 'time',
                    offset: true,
                    time: {
                        unit: 'day',
                        parser: 'e',
                        displayFormats: {
                            day: 'ddd'
                        }
                    },
                    ticks: {
                        "fontSize": 12,
                        "fontColor": "rgba(22, 52, 90, 0.5)",
                        "maxTicksLimit": 2,
                    },
                    gridLines: {
                        display: false
                    }
                }]
            }
        }
    });

    $('.js-clipboard').each(function () {
        var clipboard = $.HSCore.components.HSClipboard.init(this);
    });

    $('.js-circle').each(function () {
        var circle = $.HSCore.components.HSCircles.init($(this));
    });
});
