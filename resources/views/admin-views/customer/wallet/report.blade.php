@extends('layouts.admin.app')

@section('title',translate('customer_wallet').' '.translate('report'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title text-capitalize">
                <div class="card-header-icon d-inline-flex mr-2 img">
                    <img src="{{asset('/public/assets/admin/img/wallet.png')}}" alt="{{ translate('wallet') }}" class="width-24">
                </div>
                <span>
                    {{translate('customer_wallet')}} {{translate('report')}}
                </span>
            </h1>
        </div>

        <div class="card mb-3">
            <div class="card-header text-capitalize">
                <h5 class="card-title">
                    <span class="card-header-icon">
                        <i class="tio-filter-outlined"></i>
                    </span>
                    <span>{{translate('filter')}} {{translate('options')}}</span>
                </h5>
            </div>
            <div class="card-body">
                <form action="{{route('admin.customer.wallet.report')}}" method="get">
                    <div class="row">
                        <div class="col-sm-6 col-12">
                            <div class="mb-3">
                                <input type="date" name="from" id="from_date" value="{{request()->get('from')}}" class="form-control h--45px" title="{{translate('from')}} {{translate('date')}}">
                            </div>
                        </div>
                        <div class="col-sm-6 col-12">
                            <div class="mb-3">
                                <input type="date" name="to" id="to_date" value="{{request()->get('to')}}" class="form-control h--45px" title="{{ucfirst(translate('to'))}} {{translate('date')}}">
                            </div>
                        </div>
                        <div class="col-sm-6 col-12">
                            <div class="mb-3">
                                @php
                                    $transactionStatus=request()->get('transaction_type');
                                @endphp
                                <select name="transaction_type" id="" class="form-control h--45px" title="{{translate('select')}} {{translate('transaction_type')}}">
                                    <option value="">{{translate('all')}}</option>
                                    <option value="add_fund_by_admin" {{isset($transactionStatus) && $transactionStatus=='add_fund_by_admin'?'selected':''}} >{{translate('add_fund_by_admin')}}</option>
                                    <option value="referral_order_place" {{isset($transactionStatus) && $transactionStatus=='referral_order_place	'?'selected':''}}>{{translate('referral_order_place')}}</option>
                                    <option value="loyalty_point_to_wallet" {{isset($transactionStatus) && $transactionStatus=='loyalty_point_to_wallet'?'selected':''}}>{{translate('loyalty_point_to_wallet')}}</option>
                                    <option value="order_place" {{isset($transactionStatus) && $transactionStatus=='order_place'?'selected':''}}>{{translate('order_place')}}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6 col-12">
                            <div class="mb-3">
                                <select id='customer' name="customer_id" data-placeholder="{{translate('select_customer')}}" class="js-data-example-ajax form-control h--45px" title="{{translate('select_customer')}}">
                                    @if (request()->get('customer_id') && $customerInfo = \App\User::find(request()->get('customer_id')))
                                        <option value="{{$customerInfo->id}}" selected>{{$customerInfo->f_name.' '.$customerInfo->l_name}}({{$customerInfo->phone}})</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="btn--container justify-content-end">
                        <button type="reset" id="reset_btn" class="btn btn-secondary">
                            {{translate('reset')}}
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="tio-filter-list mr-1"></i>{{translate('filter')}}
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="row g-2">
            @php
                $credit = $data[0]->total_credit;
                $debit = $data[0]->total_debit;
                $balance = $credit - $debit;
            @endphp
            <div class="col-sm-4">
                <div class="resturant-card dashboard--card bg--2">
                    <h4 class="title">{{translate('debit')}}</h4>
                    <span class="subtitle">
                        {{Helpers::set_symbol($debit)}}
                    </span>
                    <img class="resturant-icon" src="{{asset('/public/assets/admin/img/dashboard/3.png')}}" alt="{{ translate('image') }}">
                </div>
            </div>

            <div class="col-sm-4">
                <div class="resturant-card dashboard--card bg--3">
                    <h4 class="title">{{translate('credit')}}</h4>
                    <span class="subtitle">
                        {{Helpers::set_symbol($credit)}}
                    </span>
                    <img class="resturant-icon" src="{{asset('/public/assets/admin/img/dashboard/4.png')}}" alt="{{ translate('image') }}">
                </div>
            </div>
            <div class="col-sm-4">
                <div class="resturant-card dashboard--card bg--1">
                    <h4 class="title">{{translate('balance')}}</h4>
                    <span class="subtitle">
                        {{Helpers::set_symbol($balance)}}
                    </span>
                    <img class="resturant-icon" src="{{asset('/public/assets/admin/img/dashboard/1.png')}}" alt="{{ translate('image') }}">
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header text-capitalize border-0">
                <h4 class="card-title">
                    <span class="card-header-icon"><i class="tio-money"></i></span>
                    <span>{{translate('transactions')}}</span>
                </h4>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="datatable"
                           class="table table-thead-bordered table-align-middle card-table table-nowrap">
                        <thead class="thead-light">
                        <tr>
                            <th>{{ translate('sl') }}</th>
                            <th>{{translate('transaction')}} {{translate('id')}}</th>
                            <th>{{translate('customer')}}</th>
                            <th>{{translate('credit')}}</th>
                            <th>{{translate('debit')}}</th>
                            <th>{{translate('balance')}}</th>
                            <th>{{translate('transaction_type')}}</th>
                            <th>{{translate('created_at')}}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($transactions as $k=>$walletTransaction)
                            <tr scope="row">
                                <td >{{$k+$transactions->firstItem()}}</td>
                                <td>{{$walletTransaction->transaction_id}}</td>
                                <td>
                                    <a href="{{route('admin.customer.view',['user_id'=>$walletTransaction->user_id])}}">
                                        {{Str::limit($walletTransaction->user?$walletTransaction->user->f_name.' '.$walletTransaction->user->l_name:translate('not_found'),20,'...')}}
                                    </a>
                                </td>
                                <td>{{$walletTransaction->credit}}</td>
                                <td>{{$walletTransaction->debit}}</td>
                                <td>{{Helpers::set_symbol($walletTransaction->balance)}}</td>
                                <td>
                                    <span class="badge badge-soft-{{$walletTransaction->transaction_type=='order_refund'
                                        ?'danger'
                                        :($walletTransaction->transaction_type=='loyalty_point'?'warning'
                                            :($walletTransaction->transaction_type=='order_place'
                                                ?'info'
                                                :'success'))
                                        }}">
                                        {{ translate($walletTransaction->transaction_type)}}
                                    </span>
                                </td>
                                <td>{{date('Y/m/d '.config('timeformat'), strtotime($walletTransaction->created_at))}}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    @if(!$transactions)
                        <div class="empty--data">
                            <img src="{{asset('/public/assets/admin/img/empty.png')}}" alt="public">
                            <h5>
                                {{translate('no_data_found')}}
                            </h5>
                        </div>
                    @endif
                </div>

                <div class="card-footer">
                    {!! $transactions->links() !!}
                </div>
            </div>
        </div>
        @endsection

        @push('script_2')

            <script>
                "use strict";

                $(document).on('ready', function () {
                    $('.js-data-example-ajax').select2({
                        ajax: {
                            url: '{{route('admin.customer.select-list')}}',
                            data: function (params) {
                                return {
                                    q: params.term, // search term
                                    all:true,
                                    page: params.page
                                };
                            },
                            processResults: function (data) {
                                return {
                                    results: data
                                };
                            },
                            __port: function (params, success, failure) {
                                var $request = $.ajax(params);

                                $request.then(success);
                                $request.fail(failure);

                                return $request;
                            }
                        }
                    });
                });

                $('#from_date,#to_date').change(function () {
                    let fr = $('#from_date').val();
                    let to = $('#to_date').val();
                    if (fr != '' && to != '') {
                        if (fr > to) {
                            $('#from_date').val('');
                            $('#to_date').val('');
                            toastr.error('Invalid date range!', Error, {
                                CloseButton: true,
                                ProgressBar: true
                            });
                        }
                    }

                })

                $('#reset_btn').click(function(){
                    $('#customer').val(null).trigger('change');
                })
            </script>
    @endpush
