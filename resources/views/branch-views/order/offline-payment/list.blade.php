@extends('layouts.branch.app')

@section('title', translate('verify_offline_payments'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="mb-0 page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/all_orders.png')}}" class="w--20" alt="{{ translate('orders') }}">
                </span>
                <span class="">
                    {{translate('verify_offline_payments')}}
                    <span class="badge badge-pill badge-soft-secondary ml-2">{{ $orders->total() }}</span>
                </span>
            </h1>
            <ul class="nav nav-tabs border-0 my-2">
                <li class="nav-item">
                    <a class="nav-link {{Request::is('branch/verify-offline-payment/pending')?'active':''}}"  href="{{route('branch.verify-offline-payment', ['pending'])}}">{{ translate('Pending Orders') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{Request::is('branch/verify-offline-payment/denied')?'active':''}}"  href="{{route('branch.verify-offline-payment', ['denied'])}}">{{ translate('Denied Orders') }}</a>
                </li>
            </ul>
        </div>

        <div class="card">
            <div class="card-body p-20px">
                <div class="order-top">
                    <div class="card--header">
                        <form action="{{url()->current()}}" method="GET">
                            <div class="input-group">
                                <input id="datatableSearch_" type="search" name="search"
                                       class="form-control"
                                       placeholder="{{translate('Search by order ID')}}" aria-label="Search"
                                       value="{{$search}}" required autocomplete="off">
                                <div class="input-group-append">
                                    <button type="submit" class="input-group-text">
                                        {{translate('Search')}}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="table-responsive datatable-custom">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table w-100">
                        <thead class="thead-light">
                        <tr>
                            <th class="">
                                {{translate('#')}}
                            </th>
                            <th class="table-column-pl-0">{{translate('order ID')}}</th>
                            <th>{{translate('Delivery')}} {{translate('date')}}</th>
                            <th>{{translate('customer')}}</th>
                            <th>{{translate('total amount')}}</th>
                            <th>{{translate('Payment_Method')}}</th>
                            <th>{{translate('Verification_Status')}}</th>
                            <th>
                                <div class="text-center">
                                    {{translate('action')}}
                                </div>
                            </th>
                        </tr>
                        </thead>

                        <tbody id="set-rows">
                        @foreach($orders as $key=>$order)

                            <tr class="status-{{$order['order_status']}} class-all">
                                <td class="">
                                    {{$orders->firstItem()+$key}}
                                </td>
                                <td class="table-column-pl-0">
                                    <a href="{{route('branch.orders.details',['id'=>$order['id']])}}">{{$order['id']}}</a>
                                </td>
                                <td>
                                    <div>
                                        {{date('d M Y',strtotime($order['delivery_date']))}}
                                        <span>{{$order->time_slot?date(config('time_format'), strtotime($order->time_slot['start_time'])).' - ' .date(config('time_format'), strtotime($order->time_slot['end_time'])) :'No Time Slot'}}</span>
                                    </div>
                                </td>
                                <td>
                                    @if($order->is_guest == 0)
                                        @if(isset($order->customer))
                                            <div>
                                                <a class="text-body text-capitalize font-medium"
                                                   href="#">{{$order->customer['f_name'].' '.$order->customer['l_name']}}</a>
                                            </div>
                                            <div class="text-sm">
                                                <a href="Tel:{{$order->customer['phone']}}">{{$order->customer['phone']}}</a>
                                            </div>
                                        @elseif($order->user_id != null && !isset($order->customer))
                                            <label
                                                class="text-danger">{{translate('Customer_not_available')}}
                                            </label>
                                        @else
                                            <label
                                                class="text-success">{{translate('Walking Customer')}}
                                            </label>
                                        @endif
                                    @else
                                        <label
                                            class="text-success">{{translate('Guest Customer')}}
                                        </label>
                                    @endif
                                </td>
                                <td>
                                    <div class="mw-90">
                                        <div>
                                                <?php
                                                $vat_status = $order->details[0] ? $order->details[0]->vat_status : '';
                                                if($vat_status == 'included'){
                                                    $order_amount = $order['order_amount'] - $order['total_tax_amount'];
                                                }else{
                                                    $order_amount = $order['order_amount'];
                                                }
                                                ?>
                                            {{ Helpers::set_symbol($order_amount) }}
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                        $payment_info = json_decode($order->offline_payment?->payment_info, true);
                                    ?>
                                    {{ $payment_info['payment_name'] }}
                                </td>
                                <td class="text-capitalize">
                                    @if($order->offline_payment?->status == 0)
                                        <span class="badge badge-soft-info">
                                            {{translate('pending')}}
                                        </span>
                                    @elseif($order->offline_payment?->status == 2)
                                        <span class="badge badge-soft-danger">
                                            {{translate('denied')}}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn--container justify-content-center">
                                        <button class="btn btn--primary offline-payment-data" type="button" id="offline_details"
                                                data-id="{{ $order['id'] }}"
                                                data-target="" data-toggle="modal">
                                            {{ translate('Verify_Payment') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @if(count($orders)==0)
                    <div class="text-center p-4">
                        <img class="w-120px mb-3" src="{{asset('public/assets/admin/svg/illustrations/sorry.svg')}}" alt="{{ translate('image') }}">
                        <p class="mb-0">{{ translate('No_data_to_show')}}</p>
                    </div>
                @endif
            </div>
            <div class="card-footer border-0">
                <div class="d-flex justify-content-center justify-content-sm-end">
                    {!! $orders->links() !!}
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="quick-view" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered coupon-details modal-lg" role="document">
            <div class="modal-content" id="quick-view-modal">
            </div>
        </div>
    </div>
@endsection

@push('script_2')

    <script>
        "use strict";

        $('.offline-payment-data').on('click', function() {
            let id = $(this).data('id');
            get_offline_payment(id);
        });

        function get_offline_payment(id){
            $.ajax({
                type: 'GET',
                url: '{{route('branch.offline-modal-view')}}',
                data: {
                    id: id
                },
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function (data) {
                    $('#loading').hide();
                    $('#quick-view').modal('show');
                    $('#quick-view-modal').empty().html(data.view);
                }
            });
        }

        $('.update-verification-status').on('click', function() {
            let id = $(this).data('id');
            let status = $(this).data('status');
            verify_offline_payment(order_id, status);
        });

        function verify_offline_payment(order_id, status) {
            $.ajax({
                type: "GET",
                url: '{{url('/')}}/branch/orders/verify-offline-payment/'+ order_id+ '/' + status,
                success: function (data) {
                    location.reload();
                    if(data.status == true) {
                        toastr.success(data.message, {
                            CloseButton: true,
                            ProgressBar: true
                        });
                    }else{
                        toastr.error(data.message, {
                            CloseButton: true,
                            ProgressBar: true
                        });
                    }
                },
                error: function () {
                }
            });
        }

    </script>

@endpush
