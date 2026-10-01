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
                    <button type="button" class="btn btn-sm btn--primary" data-toggle="modal" data-target="#addSessionCreditsModal">
                        <i class="tio-add"></i> {{ translate('Add sessions') }}
                    </button>
                    @if(($sessionCredits ?? collect())->isNotEmpty())
                        <button type="button" class="btn btn-sm btn-soft-info" data-toggle="modal" data-target="#scheduleFromCreditsModal">
                            <i class="tio-calendar"></i> {{ translate('Schedule sessions') }}
                        </button>
                    @endif
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
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="resturant-card bg--2">
                    <img class="resturant-icon" src="{{asset('/public/assets/admin/img/dashboard/1.png')}}" alt="{{ translate('image') }}">
                    <div class="for-card-text font-weight-bold  text-uppercase mb-1">{{translate('wallet')}} {{translate('balance')}}</div>
                    <div class="for-card-count">{{ Helpers::set_symbol($customer->wallet_balance??0)}}</div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="resturant-card bg--3">
                    <img class="resturant-icon" src="{{asset('/public/assets/admin/img/dashboard/3.png')}}" alt="{{ translate('image') }}">
                    <div class="for-card-text font-weight-bold  text-uppercase mb-1">{{translate('Mentor Sessions')}}</div>
                    <div class="for-card-count">{{ $bookingStats['count'] ?? 0 }}</div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="resturant-card bg--4">
                    <img class="resturant-icon" src="{{asset('/public/assets/admin/img/dashboard/2.png')}}" alt="{{ translate('image') }}">
                    <div class="for-card-text font-weight-bold  text-uppercase mb-1">{{ translate('Session amount') }}</div>
                    <div class="for-card-count">{{ Helpers::set_symbol($bookingStats['amount'] ?? 0) }}</div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="resturant-card bg--1">
                    <img class="resturant-icon" src="{{asset('/public/assets/admin/img/dashboard/4.png')}}" alt="{{ translate('image') }}" onerror="this.style.display='none'">
                    <div class="for-card-text font-weight-bold  text-uppercase mb-1">{{ translate('Sessions remaining') }}</div>
                    <div class="for-card-count">{{ (int) ($creditsRemainingTotal ?? 0) }}</div>
                </div>
            </div>
        </div>

        <div class="row" id="printableArea">
            <div class="col-lg-8 mb-3 mb-lg-0">
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">{{ translate('Session packs') }}
                            <span class="badge badge-soft-secondary">{{ ($messagingPairs ?? $sessionCredits ?? collect())->count() }}</span>
                        </h5>
                        <div>
                            <button type="button" class="btn btn-xs btn--primary" data-toggle="modal" data-target="#addSessionCreditsModal">{{ translate('Add') }}</button>
                            @if(($sessionCredits ?? collect())->isNotEmpty())
                                <button type="button" class="btn btn-xs btn-soft-info" data-toggle="modal" data-target="#scheduleFromCreditsModal">{{ translate('Schedule') }}</button>
                            @endif
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-borderless table-thead-bordered table-nowrap card-table mb-0">
                            <thead class="thead-light">
                            <tr>
                                <th>{{ translate('Mentor') }}</th>
                                <th class="text-center">{{ translate('Total') }}</th>
                                <th class="text-center">{{ translate('Used') }}</th>
                                <th class="text-center">{{ translate('Remaining') }}</th>
                                <th class="text-center">{{ translate('Available to schedule') }}</th>
                                <th class="text-right">{{ translate('Fund Total') }}</th>
                                <th class="text-right">{{ translate('Fund Used') }}</th>
                                <th class="text-right">{{ translate('Fund Remaining') }}</th>
                                <th class="text-right">{{ translate('Per Session (net)') }}</th>
                                <th class="text-center">{{ translate('Messaging') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse(($messagingPairs ?? collect()) as $pair)
                                @php
                                    $messagingOn = (bool) ($pair['messaging_active'] ?? false);
                                    $toggleTo = $messagingOn ? 0 : 1;
                                @endphp
                                <tr>
                                    <td>
                                        {{ $pair['mentor_name'] ?? ('#'.$pair['mentor_id']) }}
                                        @if(empty($pair['has_pack']))
                                            <span class="badge badge-soft-secondary">{{ translate('No pack') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $pair['credits_total'] ?? 0 }}</td>
                                    <td class="text-center">{{ $pair['credits_used'] ?? 0 }}</td>
                                    <td class="text-center"><strong>{{ $pair['credits_remaining'] ?? 0 }}</strong></td>
                                    <td class="text-center">{{ $pair['available_to_schedule'] ?? 0 }}</td>
                                    <td class="text-right">{{ Helpers::set_symbol($pair['fund_total'] ?? 0) }}</td>
                                    <td class="text-right">{{ Helpers::set_symbol($pair['fund_used'] ?? 0) }}</td>
                                    <td class="text-right"><strong>{{ Helpers::set_symbol($pair['fund_remaining'] ?? 0) }}</strong></td>
                                    <td class="text-right">{{ Helpers::set_symbol($pair['per_session_net'] ?? 0) }}</td>
                                    <td class="text-center">
                                        <form method="post" action="{{ route('admin.customer.session-messaging.toggle', $customer->id) }}" class="d-inline-flex flex-column align-items-center gap-1">
                                            @csrf
                                            <input type="hidden" name="mentor_id" value="{{ $pair['mentor_id'] }}">
                                            <input type="hidden" name="enabled" value="{{ $toggleTo }}">
                                            <button type="submit"
                                                    class="btn btn-xs btn-{{ $messagingOn ? 'success' : 'secondary' }}"
                                                    onclick="return confirm('{{ $messagingOn ? translate('Disable messaging for this mentor–student pair?') : translate('Enable messaging for this mentor–student pair?') }}');">
                                                {{ $messagingOn ? translate('Enabled') : translate('Disabled') }}
                                            </button>
                                            <small class="text-muted">{{ $pair['messaging_label'] ?? '' }}</small>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center p-3 text-muted">{{ translate('No sessions booked yet') }}</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ translate('Mentor Sessions') }}
                            <span class="badge badge-soft-secondary">{{ $mentorBookings->total() }}</span>
                        </h5>
                    </div>
                    <div class="table-responsive datatable-custom">
                        <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                            <thead class="thead-light">
                            <tr>
                                <th>{{ translate('#') }}</th>
                                <th>{{ translate('Mentor') }}</th>
                                <th>{{ translate('Service') }}</th>
                                <th>{{ translate('Date') }}</th>
                                <th class="text-right">{{ translate('Amount') }}</th>
                                <th class="text-center">{{ translate('Payment') }}</th>
                                <th class="text-center">{{ translate('Status') }}</th>
                                <th class="text-center">{{ translate('Action') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($mentorBookings as $key => $booking)
                                <tr>
                                    <td>{{ $mentorBookings->firstItem() + $key }}</td>
                                    <td>
                                        {{ $booking->mentor?->display_name ?? '—' }}
                                        @if(in_array($booking->booking_source ?? 'paid', ['credit', 'session']))
                                            <span class="badge badge-soft-primary">{{ translate('Session') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $booking->service?->title ?? '—' }}</td>
                                    <td>
                                        {{ $booking->preferred_date?->format('d M Y') ?? '—' }}
                                        @if($booking->preferred_time)
                                            <br><small>{{ substr($booking->preferred_time, 0, 5) }}</small>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        {{ Helpers::set_symbol($booking->amount + $booking->tax_amount) }}
                                        @if(in_array($booking->booking_source ?? 'paid', ['credit', 'session']) && $booking->mentor_net > 0)
                                            <br><small class="text-muted">{{ translate('Mentor net') }}: {{ Helpers::set_symbol($booking->mentor_net) }}</small>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-soft-{{ $booking->payment_status === 'paid' ? 'success' : ($booking->payment_status === 'failed' ? 'danger' : 'warning') }}">
                                            {{ $booking->payment_status }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-soft-info">{{ $booking->status }}</span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn--container justify-content-center">
                                            <a class="action-btn" href="{{ route('admin.mentor.bookings.show', $booking->id) }}" title="{{ translate('View') }}">
                                                <i class="tio-invisible"></i>
                                            </a>
                                            @if(\App\CentralLogics\SessionCreditLogic::canReschedule($booking))
                                                <a class="action-btn" href="javascript:" title="{{ translate('Reschedule') }}"
                                                   data-toggle="modal" data-target="#rescheduleBookingModal"
                                                   data-booking-id="{{ $booking->id }}"
                                                   data-date="{{ $booking->preferred_date?->format('Y-m-d') }}"
                                                   data-time="{{ $booking->preferred_time ? substr($booking->preferred_time, 0, 5) : '' }}">
                                                    <i class="tio-date-range"></i>
                                                </a>
                                            @endif
                                            @if(\App\CentralLogics\SessionCreditLogic::canMarkComplete($booking))
                                                <form method="post" action="{{ route('admin.customer.bookings.complete', $booking->id) }}" class="d-inline" onsubmit="return confirm('Mark this session complete?');">
                                                    @csrf
                                                    <button type="submit" class="action-btn" title="{{ translate('Mark complete') }}">
                                                        <i class="tio-checkmark-circle"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            @if(in_array($booking->payment_status, ['pending', 'failed'], true))
                                                <a class="action-btn" href="javascript:" title="{{ translate('Send payment email') }}"
                                                   data-toggle="modal" data-target="#paymentReminderModal"
                                                   data-booking-id="{{ $booking->id }}"
                                                   data-mentor-name="{{ $booking->mentor?->display_name ?? '' }}"
                                                   data-session-date="{{ $booking->preferred_date?->format('d M Y') ?? '' }}"
                                                   data-payment-link="{{ \App\CentralLogics\MentorBookingMailLogic::defaultPaymentLink($booking) }}">
                                                    <i class="tio-email-outlined"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center p-4 text-muted">{{ translate('No_data_to_show') }}</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                        @if($mentorBookings->hasPages())
                            <div class="card-footer">{!! $mentorBookings->links() !!}</div>
                        @endif
                    </div>
                </div>

                @if(($demoBookings ?? collect())->isNotEmpty())
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">{{ translate('Demo bookings') }}
                                <span class="badge badge-soft-secondary">{{ count($demoBookings) }}</span>
                            </h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-borderless table-thead-bordered table-nowrap card-table">
                                <thead class="thead-light">
                                <tr>
                                    <th>{{ translate('Ref') }}</th>
                                    <th>{{ translate('Vertical') }}</th>
                                    <th>{{ translate('Stage') }}</th>
                                    <th>{{ translate('Phone') }}</th>
                                    <th>{{ translate('Status') }}</th>
                                    <th>{{ translate('Created') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($demoBookings as $demo)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.demo-bookings.show', $demo->id) }}">{{ $demo->booking_ref }}</a>
                                        </td>
                                        <td>{{ strtoupper($demo->vertical ?? $demo->category ?? '—') }}</td>
                                        <td>{{ $demo->stage ?? '—' }}</td>
                                        <td>{{ $demo->phone ?? '—' }}</td>
                                        <td><span class="badge badge-soft-secondary">{{ $demo->status }}</span></td>
                                        <td>{{ $demo->created_at?->format('d M Y H:i') }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @include('admin-views.partials._session-chat-thread')

                @if(count($orders) > 0)
                    <div class="card">
                        <div class="card-header">
                            <div class="card--header">
                                <h5 class="card-title">{{ translate('Legacy orders') }} <span class="badge badge-soft-secondary">{{ count($orders) }}</span></h5>
                                <form action="{{url()->current()}}" method="GET">
                                    <div class="input-group">
                                        <input id="datatableSearch_" type="search" name="search"
                                               class="form-control"
                                               placeholder="{{translate('Search by Order Id or Order Amount')}}" aria-label="Search"
                                               value="{{$search}}" autocomplete="off">
                                        <div class="input-group-append">
                                            <button type="submit" class="input-group-text">{{__('Search')}}</button>
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
                                    <th class="text-center">{{translate('order')}} {{translate('id')}}</th>
                                    <th class="text-center">{{translate('total amount')}}</th>
                                    <th class="text-center">{{translate('action')}}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($orders as $key=>$order)
                                    <tr>
                                        <td>{{$orders->firstItem()+$key}}</td>
                                        <td class="text-center">
                                            <a href="{{route('admin.orders.details',['id'=>$order['id']])}}">{{$order['id']}}</a>
                                        </td>
                                        <td class="text-center">{{ Helpers::set_symbol($order['order_amount']) }}</td>
                                        <td>
                                            <div class="btn--container justify-content-center">
                                                <a class="action-btn" href="{{route('admin.orders.details',['id'=>$order['id']])}}"><i class="tio-invisible"></i></a>
                                                <a class="action-btn btn--primary btn-outline-primary" target="_blank" href="{{route('admin.orders.generate-invoice',[$order['id']])}}"><i class="tio-print"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <div class="card-footer">{!! $orders->links() !!}</div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-header-title">
                            <span class="card-header-icon"><i class="tio-user"></i></span>
                            <span>{{$customer['f_name'].' '.$customer['l_name']}}</span>
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="media align-items-center customer--information-single">
                            <div class="avatar avatar-circle">
                                <img class="avatar-img" src="{{$customer->imageFullPath}}" alt="{{ translate('customer') }}">
                            </div>
                            <div class="media-body">
                                <ul class="list-unstyled m-0">
                                    <li class="pb-1">
                                        <i class="tio-email mr-2"></i>
                                        <a href="mailto:{{$customer['email']}}">{{$customer['email']}}</a>
                                    </li>
                                    <li class="pb-1">
                                        <i class="tio-call-talking-quiet mr-2"></i>
                                        <a href="Tel:{{$customer['phone']}}">{{$customer['phone'] ?: '—'}}</a>
                                        @include('admin-views.partials._whatsapp-web-btn', [
                                            'url' => $customer->whatsappWebUrl(($customer->account_type ?? '') === 'mentor' ? 'mentor' : 'student'),
                                            'title' => (($customer->account_type ?? '') === 'mentor' ? 'Mentor' : 'Student').' welcome on WhatsApp',
                                        ])
                                    </li>
                                    <li class="pb-1">
                                        <i class="tio-shopping-basket-outlined mr-2"></i>
                                        {{ $bookingStats['count'] ?? 0 }} {{translate('sessions')}}
                                    </li>
                                    @if(($demoBookings ?? collect())->isNotEmpty())
                                        <li class="pb-1">
                                            <i class="tio-bookmark-outlined mr-2"></i>
                                            {{ count($demoBookings) }} {{ translate('demo leads') }}
                                        </li>
                                    @endif
                                </ul>
                            </div>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5>{{translate('contact')}} {{translate('info')}}</h5>
                        </div>
                        @forelse($customer->addresses as $address)
                            <ul class="list-unstyled list-unstyled-py-2">
                                @if($address['contact_person_number'])
                                    <li>
                                        <i class="tio-call-talking-quiet mr-2"></i>
                                        {{$address['contact_person_number']}}
                                    </li>
                                @endif
                                <li class="quick--address-bar">
                                    <div class="quick-icon badge-soft-secondary"><i class="tio-home"></i></div>
                                    <div class="info">
                                        <h6>{{ translate($address['address_type'])}}</h6>
                                        <a target="_blank" href="http://maps.google.com/maps?z=12&t=m&q=loc:{{$address['latitude']}}+{{$address['longitude']}}" class="text--title">{{$address['address']}}</a>
                                    </div>
                                </li>
                            </ul>
                        @empty
                            <p class="text-muted mb-0">{{ translate('No saved addresses') }}</p>
                        @endforelse
                    </div>
                </div>

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
            </div>
        </div>
    </div>

    <div class="modal fade" id="resetPasswordModal" tabindex="-1" role="dialog" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="post" id="resetPasswordForm" action="{{ route('admin.customer.reset-password', [$customer->id]) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="resetPasswordModalLabel">{{ translate('Reset password') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">{{ translate('Customer') }}: {{ $customer->f_name }} {{ $customer->l_name }}</p>
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

    <div class="modal fade" id="paymentReminderModal" tabindex="-1" role="dialog" aria-labelledby="paymentReminderModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="post" id="paymentReminderForm" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentReminderModalLabel">{{ translate('Send payment email') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2" id="paymentReminderSummary"></p>
                    <div class="form-group mb-0">
                        <label>{{ translate('Payment link') }}</label>
                        <input type="url" name="payment_link" id="paymentReminderLink" class="form-control"
                               placeholder="https://www.mentorkhoj.com/my-bookings/...">
                        <small class="text-muted">{{ translate('Leave default or paste a custom Razorpay / payment URL') }}</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
                    <button type="submit" class="btn btn--primary">{{ translate('Send email') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="addSessionCreditsModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="post" action="{{ route('admin.customer.session-credits.store', $customer->id) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Add sessions') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">{{ translate('Each session = 1 booked slot with the selected mentor. Remaining count decreases when a session is marked complete.') }}</p>
                    <div class="form-group">
                        <label>{{ translate('Mentor') }}</label>
                        <select name="mentor_id" class="form-control" required>
                            <option value="">{{ translate('Select mentor') }}</option>
                            @foreach(($activeMentors ?? collect()) as $mentor)
                                <option value="{{ $mentor->id }}">{{ $mentor->display_name }} ({{ $mentor->username }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>{{ translate('Sessions') }}</label>
                        <input type="number" name="credits" id="sessionCreditsCount" class="form-control" min="1" max="500" value="10" required>
                    </div>
                    <div class="form-group">
                        <label>{{ translate('Total Amount') }} (₹)</label>
                        <input type="number" name="total_amount" id="sessionCreditsTotalAmount" class="form-control" min="0.01" step="0.01" value="10000" required>
                    </div>
                    <div class="alert alert-soft-info py-2 px-3 mb-3" id="sessionCreditsFundPreview">
                        <div class="small mb-1"><strong>{{ translate('Fund breakdown') }}</strong></div>
                        <div class="small" id="fundPreviewLines"></div>
                        <div class="small text-muted mt-2">{{ translate('Mentor balance increases by the net amount when each session is marked complete.') }}</div>
                    </div>

                    <div class="border rounded p-3 mb-3">
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" class="custom-control-input" id="scheduleNowCheck" name="schedule_now" value="1">
                            <label class="custom-control-label" for="scheduleNowCheck">{{ translate('Schedule sessions now') }}</label>
                        </div>
                        <div id="scheduleNowFields" style="display:none;">
                            <p class="text-muted small mb-2">{{ translate('Assign date and time when creating the pack (IST).') }}</p>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{ translate('Start date') }}</label>
                                        <input type="date" name="start_date" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{ translate('Start time') }}</label>
                                        <input type="time" name="start_time" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group mb-0">
                                <label>{{ translate('Schedule mode') }}</label>
                                <select name="mode" class="form-control">
                                    <option value="one_off">{{ translate('One session only') }}</option>
                                    <option value="weekly">{{ translate('Weekly (all sessions)') }}</option>
                                    <option value="daily">{{ translate('Daily (all sessions)') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label>{{ translate('Notes') }}</label>
                        <textarea name="notes" class="form-control" rows="2" maxlength="2000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
                    <button type="submit" class="btn btn--primary">{{ translate('Add sessions') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="scheduleFromCreditsModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
            <form method="post" action="{{ route('admin.customer.session-credits.schedule', $customer->id) }}" class="modal-content" id="scheduleFromCreditsForm" novalidate>
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Schedule sessions') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>{{ translate('Session pack / mentor') }}</label>
                        <select name="credit_id" id="scheduleCreditId" class="form-control" required>
                            <option value="">{{ translate('Select') }}</option>
                            @foreach(($sessionCredits ?? collect()) as $credit)
                                @php
                                    $packDuration = 20;
                                    $mentorServices = $credit->mentor?->services ?? collect();
                                    if ($mentorServices->isNotEmpty()) {
                                        $packDuration = (int) ($mentorServices->first()->duration_minutes ?: 20);
                                    }
                                @endphp
                                <option value="{{ $credit->id }}"
                                    data-available="{{ $credit->availableToSchedule() }}"
                                    data-duration="{{ $packDuration }}">
                                    {{ $credit->mentor?->display_name ?? ('#'.$credit->mentor_id) }}
                                    — {{ translate('available') }}: {{ $credit->availableToSchedule() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>{{ translate('Schedule mode') }}</label>
                        <select name="mode" id="scheduleMode" class="form-control">
                            <option value="one_off">{{ translate('One-off') }}</option>
                            <option value="daily">{{ translate('Daily connect') }}</option>
                            <option value="weekly">{{ translate('Weekly connect') }}</option>
                        </select>
                        <small class="form-text text-muted" id="scheduleModeHint" style="display:none;">
                            {{ translate('Weekly: same day and meeting time each week (IST)') }}
                        </small>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{ translate('Start date') }}</label>
                                <input type="date" name="start_date" id="scheduleStartDate" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{ translate('Meeting time (IST)') }}</label>
                                <input type="time" name="start_time" id="scheduleStartTime" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group" id="scheduleCountGroup" style="display:none;">
                                <label>{{ translate('Number of sessions') }}</label>
                                <input type="number" name="count" id="scheduleCount" class="form-control" min="1" max="52" value="1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{ translate('Duration per session (minutes)') }}</label>
                                <input type="number" name="duration_minutes" id="scheduleDurationMinutes" class="form-control" min="5" max="480" value="20" required>
                                <small class="form-text text-muted">{{ translate('Shown on mentor and student session pages') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-soft-info py-2 px-3 small mb-3" id="scheduleSlotPreview" style="display:none;"></div>
                    <div class="form-group mb-0">
                        <label>{{ translate('Note') }}</label>
                        <input type="text" name="mentee_note" class="form-control" maxlength="2000" placeholder="{{ translate('Optional') }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
                    <button type="submit" class="btn btn--primary" id="scheduleFromCreditsSubmit">{{ translate('Schedule') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="rescheduleBookingModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="post" id="rescheduleBookingForm" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Reschedule session') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>{{ translate('Date') }}</label>
                        <input type="date" name="preferred_date" id="rescheduleDate" class="form-control" required>
                    </div>
                    <div class="form-group mb-0">
                        <label>{{ translate('Time') }}</label>
                        <input type="time" name="preferred_time" id="rescheduleTime" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
                    <button type="submit" class="btn btn--primary">{{ translate('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        function pad(n) { return String(n).padStart(2, '0'); }
        function nowDateTimeParts() {
            const d = new Date();
            return {
                date: d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()),
                time: pad(d.getHours()) + ':' + pad(d.getMinutes())
            };
        }

        $('#paymentReminderModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const bookingId = button.data('booking-id');
            const mentorName = button.data('mentor-name') || '—';
            const sessionDate = button.data('session-date') || '—';
            const paymentLink = button.data('payment-link') || '';
            $('#paymentReminderSummary').text('{{ translate('Session with') }} ' + mentorName + ' · ' + sessionDate);
            $('#paymentReminderLink').val(paymentLink);
            $('#paymentReminderForm').attr('action', @json(route('admin.customer.send-payment-email', ['id' => '__ID__'])).replace('__ID__', bookingId));
        });

        $('#scheduleFromCreditsModal').on('show.bs.modal', function () {
            const parts = nowDateTimeParts();
            $('#scheduleStartDate').attr('min', parts.date).val(parts.date);
            $('#scheduleStartTime').val(parts.time);
            $('#scheduleMode').val('one_off');
            const $credit = $('#scheduleCreditId');
            const $firstAvailable = $credit.find('option[value!=""]').filter(function () {
                return parseInt($(this).attr('data-available'), 10) > 0;
            }).first();
            if ($firstAvailable.length) {
                $credit.val($firstAvailable.val());
                $('#scheduleDurationMinutes').val($firstAvailable.attr('data-duration') || 20);
            } else {
                $credit.val('');
            }
            syncScheduleFromCreditsForm();
        });

        function scheduleSelectedCreditOption() {
            return $('#scheduleCreditId option:selected');
        }

        function scheduleAvailableCount() {
            const n = parseInt(scheduleSelectedCreditOption().attr('data-available'), 10);
            return Number.isFinite(n) && n > 0 ? n : 0;
        }

        function scheduleDefaultDuration() {
            const d = parseInt(scheduleSelectedCreditOption().attr('data-duration'), 10);
            return Number.isFinite(d) && d > 0 ? d : 20;
        }

        function formatIstTimeLabel(timeVal) {
            if (!timeVal) return '';
            const parts = timeVal.split(':');
            let h = parseInt(parts[0], 10);
            const m = parts[1] || '00';
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12;
            if (h === 0) h = 12;
            return h + ':' + m + ' ' + ampm + ' IST';
        }

        function formatIstDateLabel(dateVal) {
            if (!dateVal) return '';
            const d = new Date(dateVal + 'T12:00:00');
            if (Number.isNaN(d.getTime())) return dateVal;
            return d.toLocaleDateString('en-IN', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
        }

        function addScheduleDays(dateVal, days) {
            const d = new Date(dateVal + 'T12:00:00');
            d.setDate(d.getDate() + days);
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
        }

        function addScheduleWeeks(dateVal, weeks) {
            return addScheduleDays(dateVal, weeks * 7);
        }

        function syncScheduleFromCreditsForm() {
            const mode = $('#scheduleMode').val() || 'one_off';
            const isSeries = mode !== 'one_off';
            const available = scheduleAvailableCount();
            const maxCount = Math.min(52, Math.max(available, 1));
            const $count = $('#scheduleCount');

            $('#scheduleCountGroup').toggle(isSeries);
            $('#scheduleModeHint').toggle(mode === 'weekly');

            if (isSeries) {
                $count.prop('disabled', false);
                $count.attr('max', maxCount);
                const current = parseInt($count.val(), 10) || 1;
                $count.val(Math.min(Math.max(1, current), maxCount));
            } else {
                $count.prop('disabled', true);
                $count.removeAttr('max');
                $count.val(1);
            }

            const canSubmit = available > 0 && $('#scheduleCreditId').val();
            $('#scheduleFromCreditsSubmit').prop('disabled', !canSubmit);

            const dateVal = $('#scheduleStartDate').val();
            const timeVal = $('#scheduleStartTime').val();
            const durationVal = parseInt($('#scheduleDurationMinutes').val(), 10) || 20;
            const count = isSeries ? Math.min(parseInt($count.val(), 10) || 1, maxCount) : 1;

            if (!dateVal || !timeVal || mode === 'one_off') {
                $('#scheduleSlotPreview').hide().empty();
                return;
            }

            const lines = [];
            const previewN = Math.min(count, 3);
            for (let i = 0; i < previewN; i++) {
                let slotDate = dateVal;
                if (mode === 'daily') slotDate = addScheduleDays(dateVal, i);
                if (mode === 'weekly') slotDate = addScheduleWeeks(dateVal, i);
                lines.push('{{ translate('Session') }} ' + (i + 1) + ': ' + formatIstDateLabel(slotDate) + ' · ' + formatIstTimeLabel(timeVal) + ' · ' + durationVal + ' min');
            }
            if (count > previewN) {
                lines.push('… ' + (count - previewN) + ' {{ translate('more at the same meeting time') }}');
            }
            $('#scheduleSlotPreview').html(lines.join('<br>')).show();
        }

        $('#scheduleCreditId, #scheduleMode, #scheduleStartDate, #scheduleStartTime, #scheduleCount, #scheduleDurationMinutes').on('change input', syncScheduleFromCreditsForm);

        $('#scheduleCreditId').on('change', function () {
            const maxCount = Math.min(52, Math.max(scheduleAvailableCount(), 1));
            $('#scheduleCount').val(maxCount > 0 ? maxCount : 1);
            $('#scheduleDurationMinutes').val(scheduleDefaultDuration());
            syncScheduleFromCreditsForm();
        });

        $('#scheduleFromCreditsForm').on('submit', function (e) {
            const creditId = $('#scheduleCreditId').val();
            const available = scheduleAvailableCount();
            const mode = $('#scheduleMode').val() || 'one_off';
            const isSeries = mode !== 'one_off';
            const count = isSeries ? parseInt($('#scheduleCount').val(), 10) || 1 : 1;

            if (!creditId) {
                e.preventDefault();
                alert('{{ translate('Please select a session pack') }}');
                return false;
            }
            if (available < 1) {
                e.preventDefault();
                alert('{{ translate('No sessions available to schedule for this pack') }}');
                return false;
            }
            if (isSeries && count > available) {
                e.preventDefault();
                alert('{{ translate('Only') }} ' + available + ' {{ translate('session(s) available to schedule') }}.');
                return false;
            }
            if (!$('#scheduleStartDate').val() || !$('#scheduleStartTime').val()) {
                e.preventDefault();
                alert('{{ translate('Start date and meeting time are required') }}');
                return false;
            }
            if (isSeries) {
                $('#scheduleCount').prop('disabled', false);
            }
            $('#scheduleFromCreditsSubmit').prop('disabled', true).text('{{ translate('Scheduling') }}…');
            return true;
        });

        $('#rescheduleBookingModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const bookingId = button.data('booking-id');
            const parts = nowDateTimeParts();
            $('#rescheduleDate').attr('min', parts.date).val(button.data('date') || parts.date);
            $('#rescheduleTime').val(button.data('time') || parts.time);
            $('#rescheduleBookingForm').attr('action', @json(route('admin.customer.bookings.reschedule', ['id' => '__ID__'])).replace('__ID__', bookingId));
        });

        const platformFeePercent = {{ (float) \App\CentralLogics\MentorLogic::platformFeePercent() }};

        function formatRupee(amount) {
            return '₹' + (Number(amount) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function updateSessionCreditsFundPreview() {
            const credits = Math.max(1, parseInt($('#sessionCreditsCount').val(), 10) || 1);
            const total = Math.max(0, parseFloat($('#sessionCreditsTotalAmount').val()) || 0);
            const perSessionGross = total / credits;
            const perSessionFee = perSessionGross * (platformFeePercent / 100);
            const perSessionNet = perSessionGross - perSessionFee;

            $('#fundPreviewLines').html(
                '{{ translate('Total fund') }}: <strong>' + formatRupee(total) + '</strong><br>' +
                '{{ translate('Sessions') }}: <strong>' + credits + '</strong><br>' +
                '{{ translate('Per session gross') }}: <strong>' + formatRupee(perSessionGross) + '</strong><br>' +
                '{{ translate('Platform fee') }} (' + platformFeePercent + '%): <strong>− ' + formatRupee(perSessionFee) + '</strong><br>' +
                '{{ translate('Mentor earns per session') }}: <strong>' + formatRupee(perSessionNet) + '</strong>'
            );
        }

        $('#sessionCreditsCount, #sessionCreditsTotalAmount').on('input change', updateSessionCreditsFundPreview);
        updateSessionCreditsFundPreview();
    </script>
@endpush
<script>
(function(){
  var cb = document.getElementById('scheduleNowCheck');
  var fields = document.getElementById('scheduleNowFields');
  if (!cb || !fields) return;
  function sync(){ fields.style.display = cb.checked ? 'block' : 'none'; }
  cb.addEventListener('change', sync);
  sync();
})();
</script>
