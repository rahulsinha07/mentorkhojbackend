@extends('layouts.admin.app')

@section('title', translate('New Joining Request'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/employee.png')}}" class="w--24" alt="{{ translate('deliveryman') }}">
                </span>
                <span>
                    {{translate('New Joining Request')}}
                </span>
                <span class="badge badge-soft-info badge-pill">{{ $deliverymen->total() }}</span>
            </h1>
            <ul class="nav nav-tabs border-0">
                <li class="nav-item">
                    <a class="nav-link {{Request::is('admin/delivery-man/pending/list')?'active':''}}"  href="{{ route('admin.delivery-man.pending') }}">{{ translate('Pending Delivery Man') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{Request::is('admin/delivery-man/denied/list')?'active':''}}"  href="{{ route('admin.delivery-man.denied') }}">{{ translate('Denied Delivery Man') }}</a>
                </li>
            </ul>
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
                                    {{translate('Search')}}
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
                        <th class="">{{translate('name')}}</th>
                        <th class="">{{translate('Contact Info')}}</th>
                        <th class="text-center">{{translate('Branch')}}</th>
                        <th class="text-center">{{translate('Identity Type')}}</th>
                        <th class="text-center">{{translate('Identity Number')}}</th>
                        <th class="text-center">{{translate('Identity Image')}}</th>
                        <th class="text-center">{{translate('Status')}}</th>
                        <th class="text-center">{{translate('action')}}</th>
                    </tr>
                    </thead>

                    <tbody id="set-rows">
                    @foreach($deliverymen as $key=>$deliveryman)
                        <tr>
                            <td>{{$deliverymen->firstItem()+$key}}</td>
                            <td class="">
                                <div class="table--media">
                                    <img class="rounded-full"
                                         src="{{$deliveryman->imageFullPath}}"
                                         alt="{{ translate('deliveryman') }}">
                                    <div class="table--media-body">
                                        <h5 class="title">
                                            {{$deliveryman['f_name'] }} {{$deliveryman['l_name'] }}
                                        </h5>
                                    </div>
                                </div>
                            </td>
                            <td class="">
                                <h5 class="m-0">
                                    <a href="mailto:{{$deliveryman['email']}}" class="text-hover">{{$deliveryman['email']}}</a>
                                </h5>
                                <div>
                                    <a href="tel:{{$deliveryman['phone']}}" class="text-hover">{{$deliveryman['phone']}}</a>
                                </div>
                            </td>
                            <td class="text-center">
                                @if($deliveryman->branch_id == 0)
                                    <label class="badge badge-soft-primary">{{translate('All Branch')}}</label>
                                @else
                                    <label class="badge badge-soft-primary">{{$deliveryman->branch?$deliveryman->branch->name:'Branch deleted!'}}</label>
                               @endif
                            </td>
                            <td class="text-center">{{ translate($deliveryman->identity_type) }}</td>
                            <td class="text-center">{{ $deliveryman->identity_number }}</td>
                            <td class="text-center">
                                <div class="d-flex gap-2" data-toggle="" data-placement="top" title="{{translate('click for bigger view')}}">
                                    @foreach($deliveryman->identityImageFullPath as $identification_image)
                                        <div class="mx-h80 overflow-hidden">
                                            <img class="cursor-pointer rounded img-fit p-2 w-100px mh-80px show-image-modal"
                                                 src="{{$identification_image}}"
                                                 data-image="{{$identification_image}}"
                                                 alt="{{ translate('identity_image') }}">
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                            <td class="text-center">
                                <strong class="text-info text-capitalize">{{ translate($deliveryman->application_status) }}</strong>
                            </td>
                            <td class="text-center">
                                <div class="btn--container justify-content-center">
                                    <a class="btn btn-sm btn--primary btn-outline-primary action-btn change-request-status"
                                       data-url="{{ route('admin.delivery-man.application', [$deliveryman['id'], 'approved']) }}"
                                       data-message="{{ translate('you_want_to_approve_this_application') }}"
                                       data-toggle="tooltip" data-placement="top" title="{{translate('Approve')}}"
                                       href="javascript:"><i class="tio-done font-weight-bold"></i>
                                    </a>
                                    @if ($deliveryman->application_status != 'denied')
                                        <a class="btn btn-sm btn--danger btn-outline-danger action-btn change-request-status"
                                           data-toggle="tooltip" data-placement="top" title="{{translate('Deny')}}"
                                           data-url="{{ route('admin.delivery-man.application', [$deliveryman['id'], 'denied']) }}"
                                           data-message="{{ translate('you_want_to_deny_this_application') }}"
                                           href="javascript:"><i class="tio-clear"></i>
                                        </a>
                                    @endif

                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <div class="page-area">
                    <table>
                        <tfoot>
                        {!! $deliverymen->links() !!}
                        </tfoot>
                    </table>
                </div>
                @if(count($deliverymen)==0)
                    <div class="text-center p-4">
                        <img class="w-120px mb-3" src="{{asset('public/assets/admin')}}/svg/illustrations/sorry.svg"  alt="{{ translate('image') }}">
                        <p class="mb-0">{{ translate('No_data_to_show')}}</p>
                    </div>
                @endif

            </div>

            <div class="modal fade bd-example-modal-lg" id="identification_image_view_modal" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-body p-0">
                            <div data-dismiss="modal">
                                <img src="" alt="{{ translate('image') }}"
                                     class="w-100" id="identification_image_element">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script>
        "use strict";

        $('.change-request-status').on('click', function (){
            let url = $(this).data('url');
            let message = $(this).data('message');
            request_alert(url, message)
        });

        function request_alert(url, message) {
            Swal.fire({
                title: '{{ translate('are_you_sure') }}',
                text: message,
                type: 'warning',
                showCancelButton: true,
                cancelButtonColor: 'default',
                confirmButtonColor: '#FC6A57',
                cancelButtonText: '{{ translate('no') }}',
                confirmButtonText: '{{ translate('yes') }}',
                reverseButtons: true
            }).then((result) => {
                if (result.value) {
                    location.href = url;
                }
            })
        }

        $('.show-image-modal').on('click', function(){
            let image_location = $(this).data('image')
            $('#identification_image_view_modal').modal('show');
            if(image_location != null || image_location !== '') {
                $('#identification_image_element').attr("src", image_location);
            }
        })
    </script>
@endpush
