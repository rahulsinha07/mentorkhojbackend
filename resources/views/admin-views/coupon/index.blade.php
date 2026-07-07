@extends('layouts.admin.app')

@section('title', translate('Add new coupon'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/coupon.png')}}" class="w--20" alt="{{ translate('coupon') }}">
                </span>
                <span>
                    {{translate('Coupon Setup')}}
                </span>
            </h1>
        </div>
        <div class="row gx-2 gx-lg-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body p-xl-30">
                        <form action="{{route('admin.coupon.store')}}" method="post">
                            @csrf
                            <div class="row gx--3">
                                <div class="col-md-4 col-sm-6">
                                    <div class="form-group">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('coupon type')}}</label>
                                        <select name="coupon_type" class="form-control coupon-type">
                                            <option value="default">{{translate('default')}}</option>
                                            <option value="first_order">{{translate('first order')}}</option>
                                            <option value="free_delivery">{{translate('free delivery')}}</option>
                                            <option value="customer_wise">{{translate('customer wise')}}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-6">
                                    <div class="form-group">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('coupon title')}}</label>
                                        <input type="text" name="title" value="{{old('title')}}" class="form-control" placeholder="{{ translate('New coupon') }}" required>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-6">
                                    <div class="form-group">
                                        <div class="d-flex justify-content-between">
                                            <label class="input-label" for="exampleFormControlInput1">{{translate('coupon code')}}</label>
                                            <a href="javascript:void(0)" class="float-right c1 fz-12 generate-code">{{translate('generate_code')}}</a>
                                        </div>
                                        <input type="text" name="code" class="form-control" id="code"
                                            placeholder="{{\Illuminate\Support\Str::random(8)}}" required>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-6" id="limit-for-user">
                                    <div class="form-group">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('limit')}} {{translate('for')}} {{translate('same')}} {{translate('user')}}</label>
                                        <input type="number" name="limit" value="{{old('limit')}}" id="user-limit" min="1" class="form-control" placeholder="{{ translate('EX: 10') }}" required>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-6" id="discount_type_div">
                                    <div class="form-group">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('discount')}} {{translate('type')}}</label>
                                        <select name="discount_type" id="discount_type" class="form-control">
                                            <option value="percent">{{translate('percent')}}</option>
                                            <option value="amount">{{translate('amount')}}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-6" id="discount_amount_div">
                                    <div class="form-group">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('discount amount')}}</label>
                                        <input type="number" step="any" min="1" max="10000" name="discount" id="discount_amount" value="{{old('discount') ? old('discount') : 0 }}" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-6">
                                    <div class="form-group">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('minimum')}} {{translate('purchase')}}</label>
                                        <input type="number" step="any" name="min_purchase" value="{{ old('min_purchase') ? old('min_purchase') : 0 }}" min="0" max="100000" class="form-control"
                                            placeholder="{{ translate('100') }}">
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-6" id="max_discount_div">
                                    <div class="form-group">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('maximum')}} {{translate('discount')}}</label>
                                        <input type="number" step="any" min="0" value="{{ old('max_discount') ? old('max_discount') : 0 }}" max="1000000" name="max_discount" id="max_discount" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-4 col-6">
                                    <div class="form-group">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('start')}} {{translate('date')}}</label>
                                        <label class="input-date">
                                            <input type="text" name="start_date" id="start_date" value="{{ old('start_date') }}" class="js-flatpickr form-control flatpickr-custom" placeholder="{{ translate('dd/mm/yy') }}" data-hs-flatpickr-options='{ "dateFormat": "Y/m/d", "minDate": "today" }'>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4 col-6">
                                    <div class="form-group">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('expire')}} {{translate('date')}}</label>
                                        <label class="input-date">
                                            <input type="text" name="expire_date" id="expire_date" value="{{ old('expire_date') }}" class="js-flatpickr form-control flatpickr-custom" placeholder="{{ translate('dd/mm/yy') }}" data-hs-flatpickr-options='{ "dateFormat": "Y/m/d", "minDate": "today" }'>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4 col-6 d-none" id="customer_div">
                                    <div class="form-group">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('customer')}}</label>
                                        <select name="customer_id" id="customer_id" class="form-control js-select2-custom">
                                            <option value="">{{translate('select customer')}}</option>
                                            @foreach($customers as $customer)
                                                <option value="{{$customer->id}}">{{$customer->f_name.' '. $customer->l_name}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="btn--container justify-content-end">
                                <button type="reset" class="btn btn--reset">{{translate('reset')}}</button>
                                <button type="submit" class="btn btn--primary">{{translate('submit')}}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12 mt-2">
                <div class="card">
                    <div class="card-header flex-between border-0">
                        <div class="card--header">
                            <h5 class="card-title">{{translate('Coupon Table')}} <span class="ml-2 badge badge-pill badge-soft-secondary">{{ $coupons->total() }}</span> </h5>
                            <form action="{{url()->current()}}" method="GET">
                                <div class="input-group">
                                    <input id="datatableSearch_" type="search" name="search"
                                            class="form-control"
                                            placeholder="{{translate('Search by title or coupon code')}}" aria-label="Search"
                                            value="{{$search}}" required autocomplete="off">
                                    <div class="input-group-append">
                                        <button type="submit" class="input-group-text">
                                            {{translate('search')}}
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="table-responsive datatable-custom">
                        <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                            <thead class="thead-light">
                            <tr>
                                <th>{{translate('#')}}</th>
                                <th>{{translate('coupon')}}</th>
                                <th>{{translate('coupon_type')}}</th>
                                <th>{{translate('discount_type')}}</th>
                                <th>{{translate('duration')}}</th>
                                <th>{{translate('User')}} {{'Limit'}}</th>
                                <th class="text-center">{{translate('status')}}</th>
                                <th class="text-center">{{translate('action')}}</th>
                            </tr>
                            </thead>

                            <tbody>
                            @foreach($coupons as $key=>$coupon)
                                <tr>
                                    <td>{{$coupons->firstItem()+$key}}</td>
                                    <td>
                                        <span  id="coupon_details">
                                            <strong class="text--title">{{ translate('Code')}} : {{$coupon['code']}}</strong>
                                        </span>
                                        <span id="coupon_details">
                                            <span class="d-block font-size-sm text-body">{{$coupon['title']}}</span>
                                        </span>
                                    </td>
                                    <td>{{ translate($coupon->coupon_type) }}</td>
                                    <td class="text-capitalize">
                                        <div>{{ translate($coupon->coupon_type === 'free_delivery' ? translate('Free Delivery') : translate('discount in '). $coupon['discount_type']) }}</div>
                                    </td>
                                    <td>
                                        {{$coupon->start_date->format('d M, Y')}} - {{$coupon->expire_date->format('d M, Y')}}
                                    </td>
                                    <td>
                                        <span>{{ translate('Limit') }} : <strong>{{ $coupon->coupon_type === 'first_order' ? '-' : $coupon['limit'] }},</strong></span>
                                        <span>{{ translate('Used') }} : <strong>{{ $coupon['order_count'] }}</strong></span>
                                    </td>
                                    <td>
                                        <label class="toggle-switch my-0">
                                            <input type="checkbox"
                                                class="toggle-switch-input status-change-alert" id="stocksCheckbox{{ $coupon->id }}"
                                                   data-route="{{ route('admin.coupon.status', [$coupon->id, $coupon->status ? 0 : 1]) }}"
                                                   data-message="{{ $coupon->status? translate('you_want_to_disable_this_coupon'): translate('you_want_to_active_this_coupon') }}"
                                                {{ $coupon->status ? 'checked' : '' }}>
                                            <span class="toggle-switch-label mx-auto text">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </td>
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="action-btn btn--warning btn-outline-warning get-coupon-details" id="get-coupon-details"
                                               href="#" data-id="{{ $coupon['id'] }}" data-toggle="modal" data-target="#exampleModalCenter">
                                                <i class="tio-invisible"></i></a>
                                            <a class="action-btn" href="{{route('admin.coupon.update',[$coupon['id']])}}"><i class="tio-edit"></i></a>
                                            <a class="action-btn btn--danger btn-outline-danger form-alert" href="javascript:"
                                               data-id="coupon-{{$coupon['id']}}"
                                               data-message="{{ translate('Want to delete this coupon') }}?">
                                                <i class="tio-delete-outlined"></i>
                                            </a>
                                            <form action="{{route('admin.coupon.delete',[$coupon['id']])}}"
                                                    method="post" id="coupon-{{$coupon['id']}}">
                                                @csrf @method('delete')
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <table>
                            <tfoot>
                            {!! $coupons->links() !!}
                            </tfoot>
                        </table>
                        @if(count($coupons) == 0)
                        <div class="text-center p-4">
                            <img class="w-120px mb-3" src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="Image Description">
                            <p class="mb-0">{{translate('No_data_to_show')}}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="quick-view" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered coupon-details" role="document">
            <div class="modal-content" id="quick-view-modal">
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin/js/coupon-index.js') }}"></script>
    <script>
    "use strict";

        $('.get-coupon-details').on('click', function (){
            let id = $(this).data('id');
            $.ajax({
                type: 'GET',
                url: '{{route('admin.coupon.quick-view-details')}}',
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
        })

    </script>
@endpush
