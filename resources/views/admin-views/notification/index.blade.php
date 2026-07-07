@extends('layouts.admin.app')

@section('title', translate('Add new notification'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/notification.png')}}" class="w--20" alt="{{ translate('notification') }}">
                </span>
                <span>
                    {{translate('Send Push Notification')}}
                </span>
            </h1>
        </div>
        <div class="row">
            <div class="col-sm-12 mb-5">
                <div class="card">
                    <div class="card-body">
                        <form action="{{route('admin.notification.store')}}" method="post" enctype="multipart/form-data">
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label mb-3" for="exampleFormControlInput1">{{translate('title')}}</label>
                                        <input type="text" name="title" class="form-control" value="{{old('title')}}" placeholder="{{ translate('Ex : New Notification') }}" required>
                                    </div>
                                    <div class="form-group mb-0">
                                        <label class="form-label mb-3" for="exampleFormControlInput1">{{translate('description')}}
                                            <i class="tio-info-outined"
                                               data-toggle="tooltip"
                                               data-placement="top"
                                               title="{{ translate('Description maximum character length must be 255') }}">
                                            </i>
                                        </label>
                                        <textarea name="description" class="form-control h--92px" placeholder="{{ translate('Ex : Max 250 Words') }}" required>{{ old('description') }}</textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex flex-column justify-content-center h-100">
                                        <h5 class="text-center mb-3 mt-auto text--title text-capitalize">
                                            {{translate('notification banner')}}
                                            <small class="text-danger">* ( {{translate('ratio')}} 3:1 )</small>
                                        </h5>
                                        <label class="upload--vertical mt-auto">
                                            <input type="file" name="image" id="customFileEg1" accept=".jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*" hidden>
                                            <img id="viewer" src="{{asset('public/assets/admin/img/upload-vertical.png')}}" alt="notification image"/>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="btn--container justify-content-end mt-2">
                                        <button type="reset" class="btn btn--reset">{{translate('reset')}}</button>
                                        <button type="submit" class="btn btn--primary">{{translate('submit')}}</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header border-0 flex-between">
                        <div class="card--header">
                            <h5 class="card-title">{{translate('Notifications Table')}} <span class="ml-2 badge badge-pill badge-soft-secondary">{{ $notifications->total() }}</span> </h5>
                            <form action="{{url()->current()}}" method="GET">
                                <div class="input-group">
                                    <input id="datatableSearch_" type="search" name="search"
                                            class="form-control"
                                            placeholder="{{translate('Search')}}" aria-label="Search"
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
                                    <th>{{translate('image')}}</th>
                                    <th>{{translate('title')}}</th>
                                    <th>{{translate('description')}}</th>
                                    <th class="text-center">{{translate('status')}}</th>
                                    <th class="text-center">{{translate('action')}}</th>
                                </tr>
                            </thead>

                            <tbody>
                            @foreach($notifications as $key=>$notification)
                                <tr>
                                    <td>{{$notifications->firstItem()+$key}}</td>
                                    <td>
                                        @if($notification['image']!=null)
                                            <img class="img-vertical-150"
                                                 src="{{$notification->imageFullPath}}"
                                            alt="{{ translate('notification') }}">
                                        @else
                                            <label class="badge badge-soft-warning">No {{translate('image')}}</label>
                                        @endif
                                    </td>
                                    <td>
                                    <span class="d-block font-size-sm text-body">
                                        {{substr($notification['title'],0,25)}} {{strlen($notification['title'])>25?'...':''}}
                                    </span>
                                    </td>
                                    <td>
                                        <div class="line--limit-2 max-200px ">
                                        {{substr($notification['description'],0,50)}} {{strlen($notification['description'])>25?'...':''}}
                                        </div>
                                    </td>
                                    <td>
                                        <label class="toggle-switch my-0">
                                            <input type="checkbox"
                                                   data-route="{{ route('admin.notification.status', [$notification->id, $notification->status ? 0 : 1]) }}"
                                                   data-message="{{ $notification->status? translate('you_want_to_disable_this_notification'): translate('you_want_to_active_this_notification') }}?"
                                                    class="toggle-switch-input status-change-alert" id="stocksCheckbox{{ $notification->id }}"
                                                    {{ $notification->status ? 'checked' : '' }}>
                                            <span class="toggle-switch-label mx-auto text">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </td>
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="action-btn"
                                                href="{{route('admin.notification.edit',[$notification['id']])}}">
                                                <i class="tio-edit"></i>
                                            </a>
                                            <a class="action-btn btn--danger btn-outline-danger form-alert" href="javascript:"
                                               data-id="notification-{{$notification['id']}}"
                                               data-message="{{ translate("Want to delete this") }}?">
                                                <i class="tio-delete-outlined"></i>
                                            </a>
                                            <form
                                                action="{{route('admin.notification.delete',[$notification['id']])}}"
                                                method="post" id="notification-{{$notification['id']}}">
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
                            {!! $notifications->links() !!}
                            </tfoot>
                        </table>
                        @if(count($notifications)==0)
                            <div class="text-center p-4">
                                <img class="mb-3 width-7rem" src="{{asset('public/assets/admin')}}/svg/illustrations/sorry.svg" alt="{{ translate('image') }}">
                                <p class="mb-0">{{ translate('No_data_to_show')}}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
<script src="{{ asset('public/assets/admin/js/upload-single-image.js') }}"></script>
@endpush
