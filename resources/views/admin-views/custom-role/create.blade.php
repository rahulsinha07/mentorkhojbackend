@extends('layouts.admin.app')

@section('title', translate('employee role'))

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{asset('public/assets/admin/img/employee.png')}}" class="w--24" alt="{{ translate('employee') }}">
            </span>
            <span>
                {{translate('Employee Role Setup')}}
            </span>
        </h1>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form id="submit-create-role" method="post" action="{{route('admin.custom-role.store')}}">
                @csrf
                <div class="max-w-500px">
                    <div class="form-group">
                        <label class="form-label">{{translate('role_name')}}</label>
                        <input type="text" name="name" class="form-control" id="name" aria-describedby="emailHelp" placeholder="{{translate('Ex')}} : {{translate('Store')}}" required>
                    </div>
                </div>

                <div class="d-flex">
                    <h5 class="input-label m-0 text-capitalize">{{translate('module_permission')}} : </h5>
                    <div class="check-item pb-0 w-auto">
                        <input type="checkbox" id="select_all">
                        <label class="title-color mb-0 pl-2" for="select_all">{{ translate('select_all')}}</label>
                    </div>
                </div>

                <div class="check--item-wrapper">
                    @foreach(MANAGEMENT_SECTION as $section)
                        <div class="check-item">
                            <div class="form-group form-check form--check">
                                <input type="checkbox" name="modules[]" value="{{$section}}" class="form-check-input module-permission" id="{{$section}}">
                                <label class="form-check-label" for="{{$section}}">{{translate($section)}}</label>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="btn--container justify-content-end mt-4">
                    <button type="reset" class="btn btn--reset">{{translate('reset')}}</button>
                    <button type="submit" class="btn btn--primary">{{translate('Submit')}}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header border-0">
            <div class="card--header">
                <h5 class="card-title">{{translate('employee_roles_table')}} <span class="badge badge-soft-primary">{{count($adminRoles)}}</span></h5>
                <form action="{{url()->current()}}" method="GET">
                    <div class="input-group">
                        <input id="datatableSearch_" type="search" name="search"
                            class="form-control"
                            placeholder="{{translate('Search by Role Name')}}" aria-label="Search" required autocomplete="off">
                        <div class="input-group-append">
                            <button type="submit" class="input-group-text">
                                {{translate('Search')}}
                            </button>
                        </div>
                    </div>
                </form>

                <div class="hs-unfold ml-sm-3">
                    <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle btn export-btn btn-outline-primary-2 btn--primary font--sm" href="javascript:;"
                        data-hs-unfold-options='{
                            "target": "#usersExportDropdown",
                            "type": "css-animation"
                        }'>
                        <i class="tio-download-to mr-1"></i> {{translate('export')}}
                    </a>

                    <div id="usersExportDropdown" class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                        <span class="dropdown-header">{{translate('download')}} {{translate('options')}}</span>
                        <a id="export-excel" class="dropdown-item" href="{{route('admin.custom-role.export')}}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{asset('public/assets/admin')}}/svg/components/excel.svg" alt="{{ translate('excel') }}">
                            {{translate('excel')}}
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-borderless mb-0" id="dataTable" cellspacing="0">
                    <thead class="thead-light">
                    <tr>
                        <th>{{translate('SL')}}</th>
                        <th>{{translate('role_name')}}</th>
                        <th>{{translate('modules')}}</th>
                        <th class="text-center">{{translate('status')}}</th>
                        <th class="text-center">{{translate('action')}}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($adminRoles as $k=>$role)
                        <tr>
                            <td>{{$k+1}}</td>
                            <td>{{$role['name']}}</td>
                            <td class="text-capitalize">
                                <div class="max-w-300px">
                                    @if($role['module_access']!=null)
                                        @php($comma = '')
                                        @foreach((array)json_decode($role['module_access']) as $module)
                                            {{$comma}}{{ translate(str_replace('_',' ',$module)) }}
                                            @php($comma = ', ')
                                        @endforeach
                                    @endif
                                </div>
                            </td>
                            <td>
                                <label class="toggle-switch my-0">
                                    <input type="checkbox"
                                           data-route="{{ route('admin.custom-role.status', [$role->id, $role->status ? 0 : 1]) }}"
                                           data-message="{{ $role->status? translate('you_want_to_disable_this_role'): translate('you_want_to_active_this_role') }}"
                                           class="toggle-switch-input status-change-alert" id="stocksCheckbox{{ $role->id }}"
                                        {{ $role->status ? 'checked' : '' }}>
                                    <span class="toggle-switch-label mx-auto text">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>
                            </td>
                            <td>
                                <div class="btn--container justify-content-center">
                                    <a href="{{route('admin.custom-role.update',[$role['id']])}}"
                                        class="action-btn"
                                        title="{{translate('Edit') }}">
                                        <i class="tio-edit"></i>
                                    </a>
                                    <a class="action-btn btn--danger btn-outline-danger form-alert" href="javascript:"
                                       data-id="role-{{$role['id']}}"
                                       data-message="{{translate('Want to delete this role')}}?">
                                        <i class="tio-delete-outlined"></i>
                                    </a>
                                    <form action="{{route('admin.custom-role.delete',[$role['id']])}}"
                                          method="post" id="role-{{$role['id']}}">
                                        @csrf @method('delete')
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if(count($adminRoles) === 0)
                    <div class="text-center p-4">
                        <img class="mb-3 width-7rem" src="{{asset('public/assets/admin/svg/illustrations/sorry.svg')}}" alt="{{ translate('image') }}">
                        <p class="mb-0">{{ translate('No data to show') }}</p>
                    </div>
                @endif
            </div>
        </div>
        <div>
            {{$adminRoles->links()}}
        </div>
    </div>
</div>
@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin/js/custom-role.js') }}"></script>
@endpush
