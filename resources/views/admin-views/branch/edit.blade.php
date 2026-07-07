@extends('layouts.admin.app')

@section('title', translate('Update Branch'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/add_branch.png')}}" class="w--24" alt="{{ translate('branch') }}">
                </span>
                <span>
                    {{translate('branch')}} {{translate('update')}}
                </span>
            </h1>
        </div>

        @php($branchCount=\App\Model\Branch::count())
        <form action="{{route('admin.branch.update',[$branch['id']])}}" method="post" enctype="multipart/form-data">
            @csrf
            <div class="row g-2">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="tio-user"></i>
                                {{translate('store_information')}}
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="form-group mb-0">
                                                <label class="input-label" for="exampleFormControlInput1">{{translate('name')}}</label>
                                                <input type="text" name="name" value="{{$branch['name']}}" class="form-control" placeholder="{{ translate('New branch') }}" required>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group mb-0">
                                                <label class="input-label" for="">{{translate('address')}}</label>
                                                <textarea type="text" name="address" class="form-control h--90px" placeholder="{{translate('Ex: 666/668 DOHS Mirpur, Dhaka, Bangladesh')}}" value="{{$branch['address']}}" required>{{$branch['address']}}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                        <div class="d-flex flex-column justify-content-center h-100">
                                            <div class="text-center mb-3 text--title">
                                                {{translate('Branch Image')}}
                                                <small class="text-danger">* ( {{translate('ratio')}} 1:1 )</small>
                                            </div>
                                            <label class="upload--squire">
                                                <input type="file" name="image" id="customFileEg1" value="{{$branch['image']}}" accept=".jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*" hidden>
                                                <img id="viewer" src="{{$branch->imageFullPath}}"
                                                    alt="branch image"/>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row g-3 mt-3">
                                    <div class="col-sm-6 col-md-4">
                                        <div class="form-group mb-0">
                                            <label class="input-label" for="exampleFormControlInput1">{{translate('phone')}}</label>
                                            <input type="phone" name="phone" value="{{$branch['phone']}}" class="form-control"
                                                    placeholder="{{ translate('EX : +09853834') }}" required>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-md-4">
                                        <div class="form-group mb-0">
                                            <label class="input-label" for="exampleFormControlInput1">{{translate('email')}}</label>
                                            <input type="email" name="email" value="{{$branch['email']}}" class="form-control"
                                                    placeholder="{{ translate('EX : example@example.com') }}" required>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-md-4">
                                        <div class="form-group mb-0">
                                            <label class="input-label" for="exampleFormControlInput1">{{translate('password')}} <span class="text-danger font-size-sm">* ( {{ translate('input if you want to reset.') }} )</span></label>
                                            <div class="position-relative">
                                                <input type="password" name="password" class="form-control" placeholder="">
                                                <div class="__right-eye">
                                                    <i class="tio-hidden-outlined"></i>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="tio-poi"></i>
                                {{translate('store_location')}}
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="row  g-3">
                                        <div class="col-12">
                                            <div class="form-group mb-0">
                                                <label class="form-label text-capitalize"
                                                       for="latitude">{{ translate('latitude') }}<span
                                                        class="form-label-secondary pl-1" data-toggle="tooltip" data-placement="right"
                                                        data-original-title="{{ translate('click_on_the_map_select_your_default_location') }}"><img
                                                            src="{{ asset('/public/assets/admin/img/info-circle.svg') }}"
                                                            alt="{{ translate('click_on_the_map_select_your_default_location') }}"></span></label>
                                                <input type="text" id="latitude" name="latitude" class="form-control"
                                                       placeholder="{{ translate('Ex:') }} 23.8118428"
                                                       value="{{$branch['latitude']}}" required readonly>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group mb-0">
                                                <label class="form-label text-capitalize"
                                                for="longitude">{{ translate('longitude') }}<span
                                                    class="form-label-secondary pl-1" data-toggle="tooltip" data-placement="right"
                                                    data-original-title="{{ translate('messages.click_on_the_map_select_your_default_location') }}"><img
                                                        src="{{ asset('/public/assets/admin/img/info-circle.svg') }}"
                                                        alt="{{ translate('click_on_the_map_select_your_default_location') }}"></span></label>
                                                <input type="text" name="longitude" class="form-control"
                                                       placeholder="{{ translate('Ex:') }} 90.356331" id="longitude"
                                                       value="{{$branch['longitude']}}" required readonly>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group mb-0">
                                                <label class="input-label" for="">
                                                    <i class="tio-info-outined"
                                                        data-toggle="tooltip"
                                                        data-placement="top"
                                                        title="This value is the radius from your branch location, and customer can order food inside  the circle calculated by this radius."></i>
                                                    {{translate('coverage')}} ( {{translate('km')}} )
                                                </label>
                                                <input type="number" name="coverage" min="1" value="{{$branch['coverage']}}" max="1000" class="form-control" placeholder="{{ translate('Ex : 3') }}"
                                                    {{$branchCount>1?'required':''}} >
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6" id="location_map_div">
                                    <input id="pac-input" class="controls rounded" data-toggle="tooltip"
                                           data-placement="right"
                                           data-original-title="{{ translate('search_your_location_here') }}"
                                           type="text" placeholder="{{ translate('search_here') }}" />
                                    <div id="location_map_canvas" class="overflow-hidden rounded h-100"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="btn--container justify-content-end mt-3">
                <button type="reset" class="btn btn--reset">{{translate('reset')}}</button>
                <button type="submit" class="btn btn--primary">{{translate('update')}}</button>
            </div>
        </form>
    </div>

@endsection

@push('script_2')

<script src="https://maps.googleapis.com/maps/api/js?key={{ Helpers::get_business_settings('map_api_client_key') }}&libraries=places&v=3.45.8"></script>
<script src="{{ asset('public/assets/admin/js/branch.js') }}"></script>

@endpush
