@extends('layouts.blank')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 mt-3">
                <div class="card mt-3">
                    <div class="card-body text-center">
                        @php($logo=\App\Model\BusinessSetting::where(['key'=>'logo'])->first()->value)
                        <img class="width-200px"
                             src="{{ App\CentralLogics\Helpers::onErrorImage($logo, asset('storage/app/public/restaurant') . '/' . $logo, asset('public/assets/admin/img/160x160/img2.jpg'), 'restaurant/')}}"
                             alt="{{ translate('logo') }}">
                        <br><hr>

                        <a class="btn btn-primary" href="{{route('admin.dashboard')}}">{{ translate('Dashboard') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
