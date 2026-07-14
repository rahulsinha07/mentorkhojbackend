@extends('layouts.admin.app')

@section('title', translate('Customer Details'))

@section('content')
    <div class="content container-fluid">
        <div class="d-print-none pb-2">
            <div class="page-header border-bottom">
                <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/employee.png')}}" class="w--20" alt="{{ translate('customer') }}">
                </span>
                    <span class="page-header-title pt-2">
                        {{translate('customer_Details')}}
                    </span>
                </h1>
            </div>
        </div>

        <div class="d-print-none pb-2">
            <div class="row align-items-center">
                <div class="col-auto mb-2 mb-sm-0">
                    <h1 class="page-header-title">{{translate('customer')}} {{translate('id')}} #{{$customer['id']}}</h1>
                    <span class="d-block">
                        <i class="tio-date-range"></i> {{translate('joined_at')}} : {{date('d M Y '.config('timeformat'),strtotime($customer['created_at']))}}
                    </span>
                </div>

                <div class="col-auto ml-auto d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn--primary" data-toggle="modal" data-target="#resetPasswordModal"
                            data-customer-id="{{ $customer->id }}"
                            data-customer-name="{{ $customer->f_name }} {{ $customer->l_name }}">
                        <i class="tio-key"></i> {{ translate('Reset password') }}
                    </button>
                    @if($customer->mentorProfile)
                        <a class="btn btn-sm btn-soft-info" href="{{ route('admin.mentor.edit', [$customer->mentorProfile->id]) }}">
                            <i class="tio-edit"></i> {{ translate('Edit mentor profile') }}
                        </a>
                    @endif
                    <a class="btn btn-icon btn-sm btn-soft-secondary rounded-circle mr-1"
                       href="{{route('admin.customer.view',[$customer['id']-1])}}"
                       data-toggle="tooltip" data-placement="top" title="{{ translate('Previous customer') }}">
                        <i class="tio-arrow-backward"></i>
                    </a>
                    <a class="btn btn-icon btn-sm btn-soft-secondary rounded-circle"
                       href="{{route('admin.customer.view',[$customer['id']+1])}}" data-toggle="tooltip"
                       data-placement="top" title="{{ translate('Next customer') }}">
                        <i class="tio-arrow-forward"></i>
                    </a>
                </div>
            </div>
        </div>
        <div class="row mb-2 g-2">


            <div class="col-lg-4 col-md-4 col-sm-6">
                <div class="resturant-card bg--2">
                    <img class="resturant-icon" src="{{asset('/public/assets/admin/img/dashboard/1.png')}}" alt="{{ translate('image') }}">
                    <div class="for-card-text font-weight-bold  text-uppercase mb-1">{{translate('wallet')}} {{translate('balance')}}</div>
                    <div class="for-card-count">{{ Helpers::set_symbol($customer->wallet_balance??0)}}</div>
                </div>
            </div>

            <div class="col-lg-4 col-md-4 col-sm-6">
                <div class="resturant-card bg--3">
                    <img class="resturant-icon" src="{{asset('/public/assets/admin/img/dashboard/3.png')}}" alt="{{ translate('image') }}">
                    <div class="for-card-text font-weight-bold  text-uppercase mb-1">{{translate('Mentor Sessions')}}</div>
                    <div class="for-card-count">{{ $bookingStats['count'] ?? 0 }}</div>
                </div>
            </div>

            <div class="col-lg-4 col-md-4 col-sm-6">
                <div class="resturant-card bg--4">
                    <img class="resturant-icon" src="{{asset('/public/assets/admin/img/dashboard/2.png')}}" alt="{{ translate('image') }}">
                    <div class="for-card-text font-weight-bold  text-uppercase mb-1">{{translate('Total Order Amount')}}</div>
                    <div class="for-card-count">{{ Helpers::set_symbol($bookingStats['amount'] ?? 0) }}</div>
                </div>
            </div>
        </div>


        <div class="row" id="printableArea">
            <div class="col-lg-8 mb-3 mb-lg-0">
                <div class="card">
                    <div class="card-header">
                        <div class="card--header">
                        <h5 class="card-title">{{ translate('Order List') }} <span class="badge badge-soft-secondary">{{ count($orders) }}</span></h5>
                            <form action="{{url()->current()}}" method="GET">
                                <div class="input-group">
                                    <input id="datatableSearch_" type="search" name="search"
                                           class="form-control"
                                           placeholder="{{translate('Search by Order Id or Order Amount')}}" aria-label="Search"
                                           value="{{$search}}" required autocomplete="off">
                                    <div class="input-group-append">
                                        <button type="submit" class="input-group-text">
                                            {{__('Search')}}
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <h5 class="card-header-title">
                        </h5>
                    </div>
                    <div class="table-responsive datatable-custom">
                        <table id="columnSearchDatatable"
                               class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                            <thead class="thead-light">
                            <tr>
                                <th>{{translate('#')}}</th>
                                <th class="text-center">{{translate('order')}} {{translate('id')}}</th>
                                <th class="text-center">{{translate('total amount')}}</th>
                                <th class="text-center">{{translate('action')}}</th>
                            </tr>
                            </thead>

                            <tbody>
                            @foreach($orders as $key=>$order)
                                <tr>
                                    <td>{{$orders->firstItem()+$key}}</td>
                                    <td class=" text-center">
                                        <a href="{{route('admin.orders.details',['id'=>$order['id']])}}">{{$order['id']}}</a>
                                    </td>
                                    <td class="text-center">{{ Helpers::set_symbol($order['order_amount']) }}</td>
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="action-btn"
                                                href="{{route('admin.orders.details',['id'=>$order['id']])}}"><i
                                                    class="tio-invisible"></i></a>
                                            <a class="action-btn btn--primary btn-outline-primary" target="_blank"
                                                href="{{route('admin.orders.generate-invoice',[$order['id']])}}">
                                                <i class="tio-print"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <div class="card-footer">
                        {!! $orders->links() !!}
                        </div>
                        @if(count($orders)==0)
                            <div class="text-center p-4">
                                <img class="w-120px mb-3" src="{{asset('public/assets/admin')}}/svg/illustrations/sorry.svg" alt="{{ translate('image') }}">
                                <p class="mb-0">{{ translate('No_data_to_show')}}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>



            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-header-title">
                            <span class="card-header-icon">
                                <i class="tio-user"></i>
                            </span>
                            <span>
                                @if($customer)
                                    {{$customer['f_name'].' '.$customer['l_name']}}
                                    @else
                                    {{ translate('customer') }}
                                @endif
                            </span>
                        </h4>
                    </div>

                    @if($customer)
                        <div class="card-body">
                            <div class="media align-items-center customer--information-single" href="javascript:">
                                <div class="avatar avatar-circle">
                                    <img
                                        class="avatar-img"
                                        src="{{$customer->imageFullPath}}"
                                        alt="{{ translate('customer') }}">
                                </div>
                                <div class="media-body">
                                    <ul class="list-unstyled m-0">
                                        <li class="pb-1">
                                            <i class="tio-email mr-2"></i>
                                            <a href="mailto:{{$customer['email']}}">{{$customer['email']}}</a>
                                        </li>
                                        <li class="pb-1">
                                            <i class="tio-call-talking-quiet mr-2"></i>
                                            <a href="Tel:{{$customer['phone']}}">{{$customer['phone']}}</a>
                                        </li>
                                        <li class="pb-1">
                                            <i class="tio-shopping-basket-outlined mr-2"></i>
                                            {{ $bookingStats['count'] ?? 0 }} {{translate('bookings')}}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5>{{translate('contact')}} {{translate('info')}}</h5>
                            </div>
                            @foreach($customer->addresses as $address)
                                <ul class="list-unstyled list-unstyled-py-2">
                                    @if($address['contact_person_number'])
                                        <li>
                                            <i class="tio-call-talking-quiet mr-2"></i>
                                            {{$address['contact_person_number']}}
                                        </li>
                                    @endif
                                    <li class="quick--address-bar">
                                        <div class="quick-icon badge-soft-secondary">
                                            <i class="tio-home"></i>
                                        </div>
                                        <div class="info">
                                            <h6>{{ translate($address['address_type'])}}</h6>
                                            <a target="_blank" href="http://maps.google.com/maps?z=12&t=m&q=loc:{{$address['latitude']}}+{{$address['longitude']}}" class="text--title">
                                                {{$address['address']}}
                                            </a>
                                        </div>
                                    </li>
                                </ul>
                            @endforeach

                        </div>
                @endif
                </div>

                @if($customer)
                <div class="card mt-3">
                    <div class="card-header">
                        <h4 class="card-header-title">{{ translate('Account & login') }}</h4>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled m-0">
                            <li class="pb-2"><strong>{{ translate('Account type') }}:</strong> {{ \App\CentralLogics\AccountTypeLogic::accountTypeLabel($customer->account_type ?? null) }}</li>
                            <li class="pb-2"><strong>{{ translate('Last login portal') }}:</strong> {{ \App\CentralLogics\AccountTypeLogic::loginPortalLabel($customer->last_login_as ?? null) }}</li>
                            <li class="pb-2"><strong>{{ translate('Last login') }}:</strong> {{ $customer->last_login_at ? $customer->last_login_at->format('d M Y H:i') : '—' }}</li>
                            <li class="pb-2"><strong>{{ translate('Auth method') }}:</strong> {{ \App\CentralLogics\AccountTypeLogic::loginMediumLabel($customer->login_medium ?? null) }}</li>
                            @if($customer->referral_code)
                                <li class="pb-2"><strong>{{ translate('Referral code') }}:</strong> {{ $customer->referral_code }}</li>
                            @endif
                            <li class="pb-2"><strong>{{ translate('Email verified') }}:</strong> {{ $customer->email_verified_at ? translate('yes') : translate('no') }}</li>
                            @if($customer->mentorProfile)
                                <li class="pb-2">
                                    <strong>{{ translate('Public profile') }}:</strong>
                                    <a href="{{ rtrim(config('app.mentorkhoj_site_url'), '/') . '/mentor/' . $customer->mentorProfile->username }}" target="_blank" rel="noopener">
                                        @{{ $customer->mentorProfile->username }}
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </div>
                </div>
                @endif
            </div>

        </div>
    </div>

    <div class="modal fade" id="resetPasswordModal" tabindex="-1" role="dialog" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="post" id="resetPasswordForm" action="{{ route('admin.customer.reset-password', [$customer->id]) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="resetPasswordModalLabel">{{ translate('Reset password') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-3" id="resetPasswordCustomerName">{{ translate('Customer') }}: {{ $customer->f_name }} {{ $customer->l_name }}</p>
                    <div class="form-group">
                        <label>{{ translate('New password') }}</label>
                        <input type="password" name="password" class="form-control" minlength="8" required autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label>{{ translate('Confirm password') }}</label>
                        <input type="password" name="password_confirmation" class="form-control" minlength="8" required autocomplete="new-password">
                    </div>
                    <div class="form-group mb-0">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="notifyCustomerPassword" name="notify_customer" value="1">
                            <label class="custom-control-label" for="notifyCustomerPassword">{{ translate('Email new password to customer') }}</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
                    <button type="submit" class="btn btn--primary">{{ translate('Update password') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
