@extends('layouts.admin.app')

@section('title', translate('Review List'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/star.png')}}" class="w--20" alt="{{ translate('image') }}">
                </span>
                <span>
                    {{translate('Review List')}}
                </span>
            </h1>
        </div>

        <div class="card">
            <div class="card-header flex-between">
                <div class="card--header">
                    <h5 class="card-title">{{translate('Review list Table')}} <span class="badge badge-soft-secondary badge-pill">{{ $reviews->total() }}</span> </h5>
                    <form action="{{url()->current()}}" method="GET">
                        <div class="input-group">
                            <input id="datatableSearch_" type="search" name="search"
                                    class="form-control"
                                    placeholder="{{translate('Search')}}" aria-label="Search"
                                    value="{{$search}}" required autocomplete="off">
                            <div class="input-group-append">
                                <button type="submit" class="input-group-text">
                                    {{__('Search')}}
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
                        <th>{{translate('deliveryman')}}</th>
                        <th>{{translate('customer')}}</th>
                        <th>{{translate('review')}}</th>
                        <th>{{translate('rating')}}</th>
                    </tr>
                    </thead>

                    <tbody>
                    @foreach($reviews as $key=>$review)
                        <tr>
                            <td>{{$reviews->firstItem()+$key}}</td>
                            <td>
                                <span class="d-block font-size-sm text-body">
                                    @if($review->delivery_man)
                                        <a href="{{route('admin.delivery-man.preview',[$review['delivery_man_id']])}}">
                                            {{$review->delivery_man->f_name.' '.$review->delivery_man->l_name}}
                                        </a>
                                    @else
                                        <span class="badge-pill badge-soft-dark text-muted text-sm small">
                                            {{translate('DeliveryMan unavailable')}}
                                        </span>
                                    @endif
                                </span>
                            </td>
                            <td>
                                @if(isset($review->customer))
                                    <a href="{{route('admin.customer.view',[$review->user_id])}}">
                                        {{$review->customer->f_name." ".$review->customer->l_name}}
                                    </a>
                                @else
                                    <span class="badge-pill badge-soft-dark text-muted text-sm small">
                                        {{translate('Customer unavailable')}}
                                    </span>
                                @endif
                            </td>
                            <td>
                                <div class="max-200px line--limit-3">
                                    {{$review->comment}}
                                </div>
                            </td>
                            <td>
                                <label class="badge rating">
                                    {{$review->rating}} <i class="tio-star"></i>
                                </label>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <table>
                    <tfoot>
                    {!! $reviews->links() !!}
                    </tfoot>
                </table>
                @if(count($reviews)==0)
                    <div class="text-center p-4">
                        <img class="w-120px mb-3" src="{{asset('public/assets/admin')}}/svg/illustrations/sorry.svg" alt="{{ translate('image') }}">
                        <p class="mb-0">{{ translate('No_data_to_show')}}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

