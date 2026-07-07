@extends('layouts.admin.app')

@section('title', translate('flash_deal'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/flash_sale.png')}}" class="w--20" alt="">
                </span>
                <span>
                    {{translate('flash deal update')}}
                </span>
            </h1>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form action="{{route('admin.offer.flash.update', [$flashDeal['id']])}}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="form-group mb-0">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('title')}}</label>
                                        <input type="text" name="title" value="{{$flashDeal['title']}}" class="form-control" placeholder="{{ translate('enter title') }}" maxlength="255" required>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group mb-0">
                                        <label for="name" class="title-color font-weight-medium text-capitalize">{{ translate('start_date')}}</label>
                                        <input type="date" name="start_date" value="{{date('Y-m-d',strtotime($flashDeal['start_date']))}}" required id="start_date"
                                               class="js-flatpickr form-control flatpickr-custom" placeholder="{{ translate('dd/mm/yy') }}" data-hs-flatpickr-options='{ "dateFormat": "Y/m/d", "minDate": "today" }'>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group mb-0">
                                        <label for="name" class="title-color font-weight-medium text-capitalize">{{ translate('end_date')}}</label>
                                        <input type="date" name="end_date" value="{{date('Y-m-d', strtotime($flashDeal['end_date']))}}" required id="end_date"
                                               class="js-flatpickr form-control flatpickr-custom" placeholder="{{ translate('dd/mm/yy') }}" data-hs-flatpickr-options='{ "dateFormat": "Y/m/d", "minDate": "today" }'>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex flex-column justify-content-center h-100">
                                <h5 class="text-center mb-3 text--title text-capitalize">
                                    {{translate('image')}}
                                    <small class="text-danger">* ( {{translate('ratio')}} 3:1 )</small>
                                </h5>
                                <label class="upload--vertical">
                                    <input type="file" name="image" id="customFileEg1" class="" accept=".jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*" hidden>
                                    <img id="viewer"
                                         src="{{$flashDeal->imageFullPath}}" alt="banner image" alt="{{ translate('image')}}"/>
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="btn--container justify-content-end">
                                <button type="reset" class="btn btn--reset">{{translate('reset')}}</button>
                                <button type="submit" class="btn btn--primary">{{translate('submit')}}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin/js/flatpicker.js') }}"></script>
    <script src="{{ asset('public/assets/admin/js/upload-single-image.js') }}"></script>
@endpush
