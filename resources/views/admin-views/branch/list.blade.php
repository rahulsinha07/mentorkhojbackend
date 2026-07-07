@extends('layouts.admin.app')

@section('title', translate('branch List'))

@section('content')
    <div class="content container-fluid">

        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/add_branch.png')}}" class="w--20" alt="{{ translate('branch') }}">
                </span>
                <span>
                    {{translate('branch List')}} <span class="badge badge-soft-secondary">{{ $branches->total() }}</span>
                </span>
            </h1>
        </div>

        <div class="row g-3">

            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header border-0">
                        <div class="card--header">
                            <h5 class="card-header-title"></h5>
                            <form action="{{url()->current()}}" method="GET">
                                <div class="input-group">
                                    <input id="datatableSearch_" type="search" name="search"
                                        class="form-control"
                                        placeholder="{{translate('Search by Name')}}" aria-label="Search"
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
                        <table id="columnSearchDatatable"
                               class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                               data-hs-datatables-options='{
                                 "order": [],
                                 "orderCellsTop": true
                               }'>
                            <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{translate('#')}}</th>
                                <th class="border-0">{{translate('branch name')}}</th>
                                <th class="border-0">{{translate('branch type')}}</th>
                                <th class="border-0">{{translate('contact info')}}</th>
                                <th class="border-0">{{translate('status')}}</th>
                                <th class="border-0 text-center">{{translate('action')}}</th>
                            </tr>

                            </thead>

                            <tbody>
                            @foreach($branches as $key=>$branch)
                                <tr>
                                    <td>{{$branches->firstItem()+$key}}</td>
                                    <td>
                                        <div class="short-media">
                                            <img src="{{$branch->imageFullPath}}">
                                            <div class="text-cont">
                                                <span class="d-block font-size-sm text-body text-trim-50">
                                                    {{$branch['name']}}
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if($branch['id']==1)
                                            <span>{{translate('main')}} </span>
                                        @else
                                            <span>{{translate('sub Branch')}} </span>
                                        @endif
                                    </td>
                                    <td>
                                        <h5 class="m-0">
                                            <a href="mailto:{{$branch['email']}}">{{$branch['email']}}</a>
                                        </h5>
                                        <div>
                                            <a href="Tel:{{$branch['phone']}}">{{$branch['phone']}}</a>
                                        </div>
                                    </td>
                                    <td>
                                        @if($branch['id']!=1)
                                        <label class="toggle-switch">
                                            <input type="checkbox"
                                                   class="toggle-switch-input status-change-alert" id="stocksCheckbox{{ $branch->id }}"
                                                   data-route="{{ route('admin.branch.status', [$branch->id, $branch->status ? 0 : 1]) }}"
                                                   data-message="{{ $branch->status? translate('you_want_to_disable_this_branch'): translate('you_want_to_active_this_branch') }}"
                                                {{ $branch->status ? 'checked' : '' }}>
                                            <span class="toggle-switch-label text">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                        </label>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="action-btn"
                                                href="{{route('admin.branch.edit',[$branch['id']])}}"><i class="tio-edit"></i>
                                            </a>
                                            @if($branch['id']!=1)
                                                <a class="action-btn btn--danger btn-outline-danger form-alert" href="javascript:"
                                                   data-id="branch-{{$branch['id']}}"
                                                   data-message="{{ translate("Want to delete this") }}">
                                                    <i class="tio-delete-outlined"></i>
                                                </a>
                                            @endif
                                        </div>
                                        <form action="{{route('admin.branch.delete',[$branch['id']])}}"
                                                method="post" id="branch-{{$branch['id']}}">
                                            @csrf @method('delete')
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <table>
                            <tfoot>
                            {!! $branches->links() !!}
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
