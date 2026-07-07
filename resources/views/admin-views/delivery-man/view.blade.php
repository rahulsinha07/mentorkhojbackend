@extends('layouts.admin.app')

@section('title', translate('Delivery Man Preview'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/employee.png')}}" class="w--20" alt="{{ translate('deliveryman') }}">
                </span>
                <span>
                    {{$deliveryman['f_name'].' '.$deliveryman['f_name']}}
                </span>
            </h1>
        </div>

        <div class="card my-3">
            <div class="card-body">
                <div class="row align-items-md-center">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center justify-content-center">
                            <img class="avatar avatar-xxl avatar-4by3 mr-4 mw-120px initial-22"
                                 src="{{$deliveryman->imageFullPath}}"
                                 alt="{{ translate('deliveryman') }}">
                            <div class="d-block">
                                <div class="rating--review">
                                    <h1 class="title">{{count($deliveryman->rating)>0?number_format($deliveryman->rating[0]->average, 1):0}}<span class="out-of">/5</span></h1>
                                    @if (count($deliveryman->rating)>0)
                                    @if ($deliveryman->rating[0]->average == 5)
                                    <div class="rating">
                                        <span><i class="tio-star"></i></span>
                                        <span><i class="tio-star"></i></span>
                                        <span><i class="tio-star"></i></span>
                                        <span><i class="tio-star"></i></span>
                                        <span><i class="tio-star"></i></span>
                                    </div>
                                    @elseif ($deliveryman->rating[0]->average < 5 && $deliveryman->rating[0]->average > 4.5)
                                    <div class="rating">
                                        <span><i class="tio-star"></i></span>
                                        <span><i class="tio-star"></i></span>
                                        <span><i class="tio-star"></i></span>
                                        <span><i class="tio-star"></i></span>
                                        <span><i class="tio-star-half"></i></span>
                                    </div>
                                    @elseif ($deliveryman->rating[0]->average < 4.5 && $deliveryman->rating[0]->average > 4)
                                    <div class="rating">
                                        <span><i class="tio-star"></i></span>
                                        <span><i class="tio-star"></i></span>
                                        <span><i class="tio-star"></i></span>
                                        <span><i class="tio-star"></i></span>
                                        <span><i class="tio-star-outlined"></i></span>
                                    </div>
                                    @elseif ($deliveryman->rating[0]->average < 4 && $deliveryman->rating[0]->average > 3)
                                    <div class="rating">
                                        <span><i class="tio-star"></i></span>
                                        <span><i class="tio-star"></i></span>
                                        <span><i class="tio-star"></i></span>
                                        <span><i class="tio-star-outlined"></i></span>
                                        <span><i class="tio-star-outlined"></i></span>
                                    </div>
                                    @elseif ($deliveryman->rating[0]->average < 3 && $deliveryman->rating[0]->average > 2)
                                    <div class="rating">
                                        <span><i class="tio-star"></i></span>
                                        <span><i class="tio-star"></i></span>
                                        <span><i class="tio-star-outlined"></i></span>
                                        <span><i class="tio-star-outlined"></i></span>
                                        <span><i class="tio-star-outlined"></i></span>
                                    </div>
                                    @elseif ($deliveryman->rating[0]->average < 2 && $deliveryman->rating[0]->average > 1)
                                    <div class="rating">
                                        <span><i class="tio-star"></i></span>
                                        <span><i class="tio-star-outlined"></i></span>
                                        <span><i class="tio-star-outlined"></i></span>
                                        <span><i class="tio-star-outlined"></i></span>
                                        <span><i class="tio-star-outlined"></i></span>
                                    </div>
                                    @elseif ($deliveryman->rating[0]->average < 1 && $deliveryman->rating[0]->average > 0)
                                    <div class="rating">
                                        <span><i class="tio-star-outlined"></i></span>
                                        <span><i class="tio-star-outlined"></i></span>
                                        <span><i class="tio-star-outlined"></i></span>
                                        <span><i class="tio-star-outlined"></i></span>
                                        <span><i class="tio-star-outlined"></i></span>
                                    </div>
                                    @elseif ($deliveryman->rating[0]->average == 1)
                                    <div class="rating">
                                        <span><i class="tio-star"></i></span>
                                        <span><i class="tio-star-outlined"></i></span>
                                        <span><i class="tio-star-outlined"></i></span>
                                        <span><i class="tio-star-outlined"></i></span>
                                        <span><i class="tio-star-outlined"></i></span>
                                    </div>
                                    @elseif ($deliveryman->rating[0]->average == 0)
                                    <div class="rating">
                                        <span><i class="tio-star-outlined"></i></span>
                                        <span><i class="tio-star-outlined"></i></span>
                                        <span><i class="tio-star-outlined"></i></span>
                                        <span><i class="tio-star-outlined"></i></span>
                                        <span><i class="tio-star-outlined"></i></span>
                                    </div>
                                    @endif
                                    @endif
                                    <div class="info">
                                        <span>{{$deliveryman->reviews->count()}} {{translate('reviews')}}</span>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <ul class="list-unstyled list-unstyled-py-2 mb-0 rating--review-right py-3">
                        @php($total=$deliveryman->reviews->count())
                            <li class="d-flex align-items-center font-size-sm">
                                @php($five=Helpers::dm_rating_count($deliveryman['id'],5))
                                <span class="progress-name mr-3">{{ translate('excellent') }}</span>
                                <div class="progress flex-grow-1">
                                    <div class="progress-bar" role="progressbar"
                                         style="width: {{$total==0?0:($five/$total)*100}}%;"
                                         aria-valuenow="{{$total==0?0:($five/$total)*100}}"
                                         aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <span class="ml-3">{{$five}}</span>
                            </li>

                            <li class="d-flex align-items-center font-size-sm">
                                @php($four=Helpers::dm_rating_count($deliveryman['id'],4))
                                <span class="progress-name mr-3">{{ translate('good') }}</span>
                                <div class="progress flex-grow-1">
                                    <div class="progress-bar" role="progressbar"
                                         style="width: {{$total==0?0:($four/$total)*100}}%;"
                                         aria-valuenow="{{$total==0?0:($four/$total)*100}}"
                                         aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <span class="ml-3">{{$four}}</span>
                            </li>

                            <li class="d-flex align-items-center font-size-sm">
                                @php($three=Helpers::dm_rating_count($deliveryman['id'],3))
                                <span class="progress-name mr-3">{{ translate('average') }}</span>
                                <div class="progress flex-grow-1">
                                    <div class="progress-bar" role="progressbar"
                                         style="width: {{$total==0?0:($three/$total)*100}}%;"
                                         aria-valuenow="{{$total==0?0:($three/$total)*100}}"
                                         aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <span class="ml-3">{{$three}}</span>
                            </li>

                            <li class="d-flex align-items-center font-size-sm">
                                @php($two=Helpers::dm_rating_count($deliveryman['id'],2))
                                <span class="progress-name mr-3">{{ translate('below_average') }}</span>
                                <div class="progress flex-grow-1">
                                    <div class="progress-bar" role="progressbar"
                                         style="width: {{$total==0?0:($two/$total)*100}}%;"
                                         aria-valuenow="{{$total==0?0:($two/$total)*100}}"
                                         aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <span class="ml-3">{{$two}}</span>
                            </li>

                            <li class="d-flex align-items-center font-size-sm">
                                @php($one=Helpers::dm_rating_count($deliveryman['id'],1))
                                <span class="progress-name mr-3">{{ translate('poor') }}</span>
                                <div class="progress flex-grow-1">
                                    <div class="progress-bar" role="progressbar"
                                         style="width: {{$total==0?0:($one/$total)*100}}%;"
                                         aria-valuenow="{{$total==0?0:($one/$total)*100}}"
                                         aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <span class="ml-3">{{$one}}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-top-0">
            <div class="table-responsive datatable-custom">
                <table id="datatable" class="table table-borderless table-thead-bordered table-nowrap card-table mb-0">
                    <thead class="thead-light">
                    <tr>
                        <th>{{translate('reviewer')}}</th>
                        <th>{{translate('review')}}</th>
                        <th>{{translate('date')}}</th>
                    </tr>
                    </thead>

                    <tbody>

                    @foreach($reviews as $review)
                        <tr>
                            <td>
                                @if(isset($review->customer))
                                    <a class="d-flex align-items-center"
                                       href="{{route('admin.customer.view',[$review['user_id']])}}">
                                        <div class="avatar avatar-circle">
                                            <img class="avatar-img" width="75" height="75"
                                                 src="{{$review->customer->imageFullPath}}"
                                                 alt="{{ translate('customer') }}">
                                        </div>
                                        <div class="ml-3">
                                        <span class="d-block h5 text-hover-primary mb-0">{{$review->customer['f_name']." ".$review->customer['l_name']}} <i
                                                class="tio-verified text-primary" data-toggle="tooltip" data-placement="top"
                                                title="Verified Customer"></i></span>
                                            <span class="d-block font-size-sm text-body">{{$review->customer->email}}</span>
                                        </div>
                                    </a>
                                @else
                                    <span class="badge-pill badge-soft-dark text-muted text-sm small">
                                        {{translate('Customer unavailable')}}
                                    </span>
                                @endif
                            </td>
                            <td>
                                <div class="text-wrap w-18rem">
                                    <div class="d-flex">
                                        <span class="rating">
                                            {{$review->rating}} <i class="tio-star"></i>
                                        </span>
                                    </div>
                                    <p>
                                        {{$review['comment']}}
                                    </p>
                                </div>
                            </td>
                            <td>
                                {{date('d M Y H:i:s',strtotime($review['created_at']))}}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer border-0">
                    {!! $reviews->links() !!}
            </div>
            @if(count($reviews)==0)
                <div class="text-center p-4">
                    <img class="w-120px mb-3" src="{{asset('public/assets/admin')}}/svg/illustrations/sorry.svg" alt="{{ translate('image') }}">
                    <p class="mb-0">{{ translate('No_data_to_show')}}</p>
                </div>
            @endif
        </div>
    </div>
@endsection

