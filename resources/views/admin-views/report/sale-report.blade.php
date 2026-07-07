@extends('layouts.admin.app')

@section('title', translate('Sale Report'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="media align-items-center">
                <img class="w--20" src="{{asset('public/assets/admin')}}/svg/illustrations/credit-card.svg"
                     alt="Image Description">
                <div class="media-body pl-3">
                    <h1 class="page-header-title mb-1">{{translate('sale')}} {{translate('report')}} {{translate('overview')}}</h1>
                    <div>
                        <span>{{translate('admin')}}:</span>
                        <a href="#" class="text--primary-2">{{auth('admin')->user()->f_name.' '.auth('admin')->user()->l_name}}</a>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <div class="card-header border-0">
                    <div class="w-100 pt-3">
                        <form class="w-100">
                            <div class="row g-3 g-sm-4 g-md-3 g-lg-4">
                                <div class="col-sm-6 col-md-4 col-lg-2">
                                    <select class="custom-select custom-select-sm text-capitalize min-h-45px" name="branch_id">
                                        <option disabled selected>--- {{translate('select')}} {{translate('branch')}} ---</option>
                                        <option value="all" {{ is_null($branchId) || $branchId == 'all' ? 'selected': ''}}>{{translate('all')}} {{translate('branch')}}</option>
                                        @foreach($branches as $branch)
                                            <option value="{{$branch['id']}}" {{ $branch['id'] == $branchId ? 'selected' : ''}}>{{$branch['name']}}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-6 col-md-4 col-lg-3">
                                    <div class="input-date-group">
                                        <label class="input-label" for="start_date">{{ translate('Start Date') }}</label>
                                        <label class="input-date">
                                            <input type="text" id="start_date" name="start_date" value="{{$startDate}}" class="js-flatpickr form-control flatpickr-custom min-h-45px" placeholder="{{ translate('yy-mm-dd')}}" data-hs-flatpickr-options='{ "dateFormat": "Y-m-d"}'>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4 col-lg-3">
                                    <div class="input-date-group">
                                        <label class="input-label" for="end_date">{{ translate('End Date') }}</label>
                                        <label class="input-date">
                                            <input type="text" id="end_date" name="end_date" value="{{$endDate}}" class="js-flatpickr form-control flatpickr-custom min-h-45px" placeholder="{{ translate('yy-mm-dd')}}" data-hs-flatpickr-options='{ "dateFormat": "Y-m-d"}'>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-12 col-lg-4 __btn-row">
                                    <a href="{{route('admin.report.sale-report')}}" id="" class="btn w-100 btn--reset min-h-45px">{{translate('clear')}}</a>
                                    <button type="submit" id="show_filter_data" class="btn w-100 btn--primary min-h-45px">{{translate('show data')}}</button>
                                </div>
                            </div>
                        </form>

                        <div class="col-md-12 pt-4">
                            <div class="report--data">
                                <div class="row g-3">
                                    <div class="col-sm-4">
                                        <div class="order--card h-100">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="card-subtitle d-flex justify-content-between m-0 align-items-center">
                                                    <span>{{translate('total orders')}}</span>
                                                </h6>
                                                <span class="card-title text-success" id="order_count">{{ count($orders) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-4">
                                        <div class="order--card h-100">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="card-subtitle d-flex justify-content-between m-0 align-items-center">
                                                    <span>{{translate('total item qty')}}</span>
                                                </h6>
                                                <span class="card-title text-success" id="item_count">{{ $totalQty }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-4">
                                        <div class="order--card h-100">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="card-subtitle d-flex justify-content-between m-0 align-items-center">
                                                    <span>{{translate('total amount')}}</span>
                                                </h6>
                                                <span class="card-title text-success" id="order_amount">{{ $totalSold }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive" id="set-rows">
                    <table class="table table-borderless table-align-middle">
                        <thead class="thead-light">
                        <tr>
                            <th>{{translate('#')}} </th>
                            <th>{{translate('product info')}}</th>
                            <th>{{translate('qty')}}</th>
                            <th>{{translate('date')}}</th>
                            <th>{{translate('amount')}}</th>
                        </tr>
                        </thead>
                        <tbody>

                        @foreach($orderDetails as $key=>$detail)
                           <?php
                                $price = $detail['price'] - $detail['discount_on_product'];
                                $orderTotal = $price * $detail['quantity'];
                                $product = json_decode($detail->product_details, true);
                                $images = $product['image'] != null ? (gettype($product['image'])!='array'?json_decode($product['image'],true):$product['image']) : [];
                                $productImage = count($images) > 0 ? $images[0] : null;

                            ?>
                            <tr>
                                <td>
                                    {{$orderDetails->firstItem()+$key}}
                                </td>
                                <td>
                                    <a href="{{route('admin.product.view',[$product['id']])}}" target="_blank" class="product-list-media">
                                        <img src="{{$detail->product? $detail->product->identityImageFullPath[0] : asset('public/assets/admin/img/160x160/2.png')}}">
                                        <h6 class="name line--limit-2">
                                            {{$product['name']}}
                                        </h6>
                                    </a>
                                </td>
                                <td>
                                    <span class="badge badge-soft-primary">{{$detail['quantity']}}</span>
                                </td>
                                <td>
                                    <div class="word-nobreak">
                                        {{date('d M Y',strtotime($detail['created_at']))}}
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        {{ Helpers::set_symbol($orderTotal) }}
                                    </div>
                                </td>
                            </tr>
                        @endforeach

                        </tbody>
                    </table>
                    <div class="card-footer border-0">
                        <div class="d-flex justify-content-center justify-content-sm-end">
                            {!! $orderDetails->links() !!}
                        </div>
                    </div>
                    @if(count($orderDetails) === 0)
                        <div class="text-center p-4">
                            <img class="mb-3 w-120px" src="{{asset('public/assets/admin/svg/illustrations/sorry.svg')}}" alt="{{translate('Image Description')}}">
                            <p class="mb-0">{{translate('No data to show')}}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin/js/flatpicker.js')}}"></script>
@endpush
