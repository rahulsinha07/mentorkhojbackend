@extends('layouts.admin.app')

@section('title', translate('Add new branch'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/add_branch.png')}}" class="w--20" alt="{{ translate('branch') }}">
                </span>
                <span>
                    {{translate('add New Branch')}}
                </span>
            </h1>
        </div>
        <div class="row g-3">
            <div class="col-sm-12">
                <form action="{{route('admin.branch.store')}}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-2">
                        <div class="col-sm-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">
                                        <i class="tio-user"></i>
                                        {{translate('branch information')}}
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-lg-6">
                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <div class="form-group mb-0">
                                                        <label class="input-label">{{translate('branch_name')}}</label>
                                                        <input type="text" name="name" class="form-control" placeholder="{{ translate('Ex: xyz branch') }}" value="{{ old('name') }}" maxlength="255" required>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="form-group mb-0">
                                                        <label class="input-label" for="">{{translate('address')}}</label>
                                                        <textarea type="text" name="address" class="form-control h--90px" placeholder="{{translate('Ex: 666/668 DOHS Mirpur, Dhaka, Bangladesh')}}" value="{{ old('address') }}" required>{{ old('address') }}</textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="d-flex flex-column justify-content-center h-100">
                                                <div class="text-center mb-3 text--title">
                                                    {{translate('Branch Image')}}
                                                    <small class="text-danger">* ( {{translate('ratio')}} 1:1 )</small>
                                                </div>
                                                <label class="upload--squire">
                                                    <input type="file" name="image" id="customFileEg1" class="" accept=".jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*" hidden>
                                                    <img id="viewer" src="{{asset('public/assets/admin/img/upload-vertical.png')}}" alt="{{ translate('branch image') }}"/>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-lg-12 mt-4">
                                            <div class="row g-3">
                                                <div class="col-sm-6 col-md-4">
                                                    <div class="form-group mb-0">
                                                        <label class="input-label">{{translate('phone')}}</label>
                                                        <input type="phone" name="phone" class="form-control" value="{{ old('phone') }}"
                                                               maxlength="255" placeholder="{{ translate('EX : +09853834') }}"
                                                               required>
                                                    </div>
                                                </div>
                                                <div class="col-sm-6 col-md-4">
                                                    <div class="form-group mb-0">
                                                        <label class="input-label">{{translate('email')}}</label>
                                                        <input type="email" name="email" class="form-control" value="{{ old('email') }}"
                                                               maxlength="255" placeholder="{{ translate('EX : example@example.com') }}"
                                                               required>
                                                    </div>
                                                </div>
                                                <div class="col-sm-6 col-md-4">
                                                    <div class="form-group mb-0">
                                                        <label class="input-label">{{translate('password')}}</label>
                                                        <div class="position-relative">
                                                            <input type="password" name="password" class="form-control" placeholder="{{ translate('Ex: 5+ Character') }}" maxlength="255" value="{{ old('password') }}" required>
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
                            </div>
                        </div>
                        <div class="col-sm-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">
                                        <i class="tio-poi"></i>
                                        {{translate('branch location')}}
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <div class="form-group mb-0">
                                                        <label class="form-label text-capitalize" for="latitude">{{ translate('latitude') }}
                                                            <i class="tio-info-outined"
                                                               data-toggle="tooltip"
                                                               data-placement="top"
                                                               title="{{ translate('click_on_the_map_select_your_default_location') }}">
                                                            </i>
                                                        </label>
                                                        <input type="text" id="latitude" name="latitude" class="form-control"
                                                               placeholder="{{ translate('Ex:') }} 23.8118428"
                                                               value="{{ old('latitude') }}" required readonly>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="form-group mb-0">
                                                        <label class="form-label text-capitalize" for="longitude">{{ translate('longitude') }}
                                                            <i class="tio-info-outined"
                                                               data-toggle="tooltip"
                                                               data-placement="top"
                                                               title="{{ translate('click_on_the_map_select_your_default_location') }}">
                                                            </i>
                                                        </label>
                                                        <input type="text" step="0.1" name="longitude" class="form-control"
                                                               placeholder="{{ translate('Ex:') }} 90.356331" id="longitude"
                                                               value="{{ old('longitude') }}" required readonly>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="form-group mb-0">
                                                        <label class="input-label">
                                                            {{translate('coverage (km)')}}
                                                            <i class="tio-info-outined"
                                                               data-toggle="tooltip"
                                                               data-placement="top"
                                                               title="{{ translate('This value is the radius from your branch location, and customer can order inside  the circle calculated by this radius. The coverage area value must be less or equal than 1000.') }}">
                                                            </i>
                                                        </label>
                                                        <input type="number" name="coverage" min="1" max="1000" class="form-control" placeholder="{{ translate('Ex : 3') }}" value="{{ old('coverage') }}" required>
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
                        <button type="submit" class="btn btn--primary">{{translate('submit')}}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script src="https://maps.googleapis.com/maps/api/js?key={{ Helpers::get_business_settings('map_api_client_key') }}&libraries=places&v=3.45.8"></script>
    <script src="{{ asset('public/assets/admin/js/branch.js') }}"></script>
@endpush
