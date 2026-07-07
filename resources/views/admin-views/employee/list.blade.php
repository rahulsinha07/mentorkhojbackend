@extends('layouts.admin.app')

@section('title', translate('Employee List'))

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{asset('public/assets/admin/img/employee.png')}}" class="w--24" alt="{{ translate('employee') }}">
            </span>
            <span>
                {{translate('Employee List')}}<span class="badge badge-soft-primary ml-2">{{$employees->total()}}</span>
            </span>
        </h1>
    </div>

    <div class="card">
        <div class="card-header border-0">
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

                <div class="hs-unfold ml-sm-auto">
                    <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle btn export-btn btn-outline-primary-2 btn--primary font--sm" href="javascript:;"
                       data-hs-unfold-options='{
                            "target": "#usersExportDropdown",
                            "type": "css-animation"
                            }'>
                        <i class="tio-download-to mr-1"></i> {{translate('export')}}
                    </a>

                    <div id="usersExportDropdown" class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                        <span class="dropdown-header">{{translate('download')}} {{translate('options')}}</span>
                        <a id="export-excel" class="dropdown-item" href="{{route('admin.employee.export', ['search'=>Request::get('search')])}}"
                           href="{{route('admin.employee.export', ['search'=>Request::get('search')])}}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{asset('public/assets/admin')}}/svg/components/excel.svg" alt="{{ translate('excel') }}">
                            {{translate('excel')}}
                        </a>
                    </div>
                </div>

                <div>
                    <a href="{{route('admin.employee.add-new')}}" class="btn btn--primary py-2">
                        <i class="tio-add"></i>
                        <span class="text">{{translate('Add New Employee')}}</span>
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body px-0 pt-0">
            <div class="table-responsive">
                <table id="datatable" class="table table-borderless table-hover table-align-middle m-0 text-14px">
                    <thead class="thead-light">
                    <tr>
                        <th>{{translate('SL')}}</th>
                        <th>{{translate('Name')}}</th>
                        <th>{{translate('Contact Info')}}</th>
                        <th>{{translate('Role')}}</th>
                        <th class="text-center">{{translate('Status')}}</th>
                        <th class="text-center">{{translate('action')}}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($employees as $k=>$employee)
                    @if($employee->role)
                        <tr>
                            <td>{{$employees->firstItem()+$k}}</td>
                            <td class="text-capitalize">
                                <div class="table--media">
                                    <img class="rounded-full"
                                         src="{{$employee->imageFullPath}}"
                                         alt="{{ translate('employee') }}">
                                    <div class="table--media-body">
                                        <h5 class="title">
                                            {{$employee['f_name'] . ' '. $employee['l_name']}}
                                        </h5>
                                    </div>
                                </div>
                            </td>
                            <td >
                                <h5 class="m-0">
                                    <a href="mailto:{{$employee['email']}}" class="text-hover">{{$employee['email']}}</a>
                                </h5>
                                <div>
                                    <a href="tel:{{$employee['phone']}}" class="text-hover">{{$employee['phone']}}</a>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-soft-info py-2 px-3 font-medium">
                                    {{$employee->role ? $employee->role['name'] : ''}}
                                </span>
                            </td>
                            <td>
                                <label class="toggle-switch my-0">
                                    <input type="checkbox"
                                           class="toggle-switch-input status-change-alert" id="stocksCheckbox{{ $employee->id }}"
                                           data-route="{{ route('admin.employee.status', [$employee->id, $employee->status ? 0 : 1]) }}"
                                           data-message="{{ $employee->status? translate('you_want_to_disable_this_employee'): translate('you_want_to_active_this_employee') }}"
                                        {{ $employee->status ? 'checked' : '' }}>
                                    <span class="toggle-switch-label mx-auto text">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>
                            </td>
                            <td>
                                <div class="btn--container justify-content-center">
                                    <a href="{{route('admin.employee.update',[$employee['id']])}}"
                                        class="action-btn"
                                        title="{{translate('Edit')}}">
                                        <i class="tio-edit"></i>
                                    </a>
                                    <a class="action-btn btn--danger btn-outline-danger form-alert" href="javascript:"
                                        data-id="employee-{{$employee['id']}}"
                                        data-message="{{translate('Want to delete this employee')}}?">
                                        <i class="tio-delete-outlined"></i>
                                    </a>
                                    <form action="{{route('admin.employee.delete',[$employee['id']])}}" method="post" id="employee-{{$employee['id']}}">
                                        @csrf @method('delete')
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div>
                {{$employees->links()}}
            </div>
            @if(count($employees)==0)
                <div class="text-center p-4">
                    <img class="w-120px mb-3" src="{{asset('public/assets/admin')}}/svg/illustrations/sorry.svg" alt="{{ translate('image') }}">
                    <p class="mb-0">{{ translate('No_data_to_show')}}</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
