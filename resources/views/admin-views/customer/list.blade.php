@extends('layouts.admin.app')

@section('title', translate('Customer List'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/employee.png')}}" class="w--20" alt="{{ translate('customer') }}">
                </span>
                <span>
                    {{translate('customers list')}} <span class="badge badge-soft-primary ml-2 badge-pill">{{ $customers->total() }}</span>
                </span>
            </h1>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card--header">
                    <form action="{{url()->current()}}" method="GET">
                        <div class="input-group">
                            <input id="datatableSearch_" type="search" name="search"
                                class="form-control"
                                placeholder="{{translate('Search by Name or Phone or Email')}}" aria-label="Search"
                                value="{{$search}}" required autocomplete="off">
                            <div class="input-group-append">
                                <button type="submit" class="input-group-text">
                                    {{ translate('search') }}
                                </button>
                            </div>
                        </div>
                    </form>

                    <div class="hs-unfold ml-auto">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-outline-primary-2 dropdown-toggle min-height-40" href="javascript:;"
                           data-hs-unfold-options='{
                            "target": "#usersExportDropdown",
                            "type": "css-animation"
                            }'>
                            <i class="tio-download-to mr-1"></i> {{ translate('export') }}
                        </a>

                        <div id="usersExportDropdown"
                            class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                            <span class="dropdown-header">{{ translate('download') }}
                                {{ translate('options') }}</span>
                            <a id="export-excel" class="dropdown-item" href="{{route('admin.customer.export', ['search'=>Request::get('search')])}}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                    alt="{{ translate('excel') }}">
                                {{ translate('excel') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive datatable-custom">
                <table class="table table-borderless table-hover table-align-middle m-0 text-14px">
                    <thead class="thead-light">
                    <tr class="word-nobreak">
                        <th>{{translate('#')}}</th>
                        <th class="table-column-pl-0">{{translate('customer name')}}</th>
                        <th>{{translate('contact info')}}</th>
                        <th class="text-center">{{translate('Account type')}}</th>
                        <th class="text-center">{{translate('Mentor profile')}}</th>
                        <th class="text-center">{{translate('Last login')}}</th>
                        <th class="text-center">{{translate('Auth method')}}</th>
                        <th class="text-center">{{translate('Total Orders')}}</th>
                        <th class="text-center">{{translate('Total Order Amount')}}</th>
                        <th class="text-center">{{translate('status')}}</th>
                        <th class="text-center">{{translate('action')}}</th>
                    </tr>
                    </thead>
                    <tbody id="set-rows">
                    @foreach($customers as $key=>$customer)
                        <tr>
                            <td>{{$customers->firstItem()+$key}}</td>
                            <td class="table-column-pl-0">
                                <a href="{{route('admin.customer.view',[$customer['id']])}}" class="product-list-media">
                                    <img class="rounded-full"
                                         src="{{$customer->imageFullPath}}"
                                        alt="{{ translate('customer') }}">
                                    <div class="table--media-body">
                                        <h5 class="title m-0">
                                            {{$customer['f_name']." ".$customer['l_name']}}
                                        </h5>
                                    </div>
                                </a>
                            </td>
                            <td>
                                <h5 class="m-0">
                                    <a href="mailto:{{$customer['email']}}">{{$customer['email']}}</a>
                                </h5>
                                <div>
                                    <a href="Tel:{{$customer['phone']}}">{{$customer['phone']}}</a>
                                </div>
                            </td>
                            <td class="text-center">
                                @php($accountType = $customer->account_type ?? 'mentee')
                                <span class="badge badge-soft-{{ $accountType === 'mentor' ? 'primary' : 'secondary' }} py-2 px-3 font-medium">
                                    {{ \App\CentralLogics\AccountTypeLogic::accountTypeLabel($accountType) }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if($customer->mentorProfile)
                                    <a href="{{ route('admin.mentor.edit', [$customer->mentorProfile->id]) }}" class="badge badge-soft-info py-2 px-3 font-medium">
                                        @{{ $customer->mentorProfile->username }}
                                    </a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div>{{ \App\CentralLogics\AccountTypeLogic::loginPortalLabel($customer->last_login_as ?? null) }}</div>
                                @if($customer->last_login_at)
                                    <small class="text-muted">{{ $customer->last_login_at->diffForHumans() }}</small>
                                @endif
                            </td>
                            <td class="text-center">
                                {{ \App\CentralLogics\AccountTypeLogic::loginMediumLabel($customer->login_medium ?? null) }}
                            </td>
                            <td>
                                <div class="text-center">
                                    <a href="{{route('admin.customer.view',[$customer['id']])}}">
                                        <span class="badge badge-soft-info py-2 px-3 font-medium">
                                            {{ (int) ($customer->bookings_count ?? 0) }}
                                        </span>
                                    </a>
                                </div>
                            </td>
                            <td>
                                <div class="text-center">
                                    {{ Helpers::set_symbol($customer->bookings_amount ?? 0) }}
                                </div>
                            </td>
                            <td>
                                <label class="toggle-switch my-0">
                                    <input type="checkbox"
                                           class="toggle-switch-input status-change-alert" id="stocksCheckbox{{ $customer->id }}"
                                           data-route="{{ route('admin.customer.status', [$customer->id, $customer->is_block ? 0 : 1]) }}"
                                           data-message="{{ translate('you_want_to_change_the_status_for_this_customer') }}"
                                        {{ $customer->is_block ? '' : 'checked' }}>
                                    <span class="toggle-switch-label mx-auto text">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>
                            </td>
                            <td>
                                <div class="btn--container justify-content-center">
                                    <a class="action-btn" href="{{route('admin.customer.view',[$customer['id']])}}" title="{{ translate('View') }}">
                                        <i class="tio-invisible"></i>
                                    </a>
                                    <a class="action-btn" href="javascript:" title="{{ translate('Reset password') }}"
                                       data-toggle="modal" data-target="#resetPasswordModal"
                                       data-customer-id="{{ $customer->id }}"
                                       data-customer-name="{{ $customer->f_name }} {{ $customer->l_name }}">
                                        <i class="tio-key"></i>
                                    </a>
                                    @if($customer->mentorProfile)
                                        <a class="action-btn" href="{{ route('admin.mentor.edit', [$customer->mentorProfile->id]) }}" title="{{ translate('Edit mentor profile') }}">
                                            <i class="tio-edit"></i>
                                        </a>
                                    @endif
                                    <a class="action-btn btn--danger btn-outline-danger form-alert" href="javascript:"
                                       data-id="customer-{{$customer['id']}}"
                                       data-message="{{translate('Want to remove this customer')}}?">
                                        <i class="tio-delete-outlined"></i>
                                    </a>
                                    <form action="{{route('admin.customer.delete',[$customer['id']])}}"
                                          method="post" id="customer-{{$customer['id']}}">
                                        @csrf @method('delete')
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if(count($customers) == 0)
            <div class="text-center p-4">
                <img class="w-120px mb-3" src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="{{ translate('image') }}">
                <p class="mb-0">{{translate('No_data_to_show')}}</p>
            </div>
            @endif

            <div class="card-footer">
                {!! $customers->links() !!}
            </div>

        </div>
    </div>

    <div class="modal fade" id="resetPasswordModal" tabindex="-1" role="dialog" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="post" id="resetPasswordForm" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="resetPasswordModalLabel">{{ translate('Reset password') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-3" id="resetPasswordCustomerName"></p>
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

@push('script_2')
    <script>
        $('#resetPasswordModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const customerId = button.data('customer-id');
            const customerName = button.data('customer-name');
            $('#resetPasswordCustomerName').text('{{ translate('Customer') }}: ' + customerName);
            $('#resetPasswordForm').attr('action', @json(route('admin.customer.reset-password', ['id' => '__ID__'])).replace('__ID__', customerId));
            $('#resetPasswordForm')[0].reset();
        });
    </script>
@endpush
