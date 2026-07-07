@extends('layouts.admin.app')

@section('title', translate('Settings'))

@section('content')
    <div class="content container-fluid">
    @php($branchCount=\App\Model\Branch::count())
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">{{translate('location')}} {{translate('coverage')}} {{translate('setup')}}</h1>
                    <span class="badge badge-soft-danger text-left">
                        {{ translate("This location setup is for your Main branch. Carefully set your restaurant location and coverage area. If you want to ignore the coverage area then keep the input box empty.<br>
                        You can ignore this when you have only the default branch and you don't want coverage area.") }}
                    </span>
                </div>
            </div>
        </div>
        <div class="row gx-2 gx-lg-3">
            <div class="col-sm-12 col-lg-12 mb-3 mb-lg-2">
                <form action="{{route('admin.business-settings.store.update-location')}}" method="post"
                      enctype="multipart/form-data">
                    @csrf
                    @php($data=\App\Model\Branch::find(1))
                    <div class="row">

                        <div class="col-md-4 col-12">
                            <div class="form-group">
                                <label class="input-label" for="exampleFormControlInput1">{{translate('latitude')}}</label>
                                <input type="text" value="{{$data['latitude']}}"
                                       name="latitude" class="form-control"
                                       placeholder="{{ translate('Ex : -94.22213') }}" {{$branchCount>1?'required':''}}>
                            </div>
                        </div>

                        <div class="col-md-4 col-12">
                            <div class="form-group">
                                <label class="input-label" for="exampleFormControlInput1">{{translate('longitude')}}</label>
                                <input type="text" value="{{$data['longitude']}}"
                                       name="longitude" class="form-control"
                                       placeholder="{{ translate('Ex : 103.344322') }}" {{$branchCount>1?'required':''}}>
                            </div>
                        </div>

                        <div class="col-md-4 col-12">
                            <div class="form-group">
                                <label class="input-label" for="">
                                    <i class="tio-info-outined"
                                       data-toggle="tooltip"
                                       data-placement="top"
                                       title="This value is the radius from your restaurant location, and customer can order food inside  the circle calculated by this radius."></i>
                                    {{translate('coverage')}} ( {{translate('km')}} )
                                </label>
                                <input type="number" value="{{$data['coverage']}}"
                                       name="coverage" class="form-control" placeholder="{{ translate('Ex : 3') }}" {{$branchCount>1?'required':''}}>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <button type="submit" class="btn btn-primary">{{translate('update')}}</button>
                </form>
            </div>
        </div>
    </div>
@endsection
