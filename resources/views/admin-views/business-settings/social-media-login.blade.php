@extends('layouts.admin.app')

@section('title', translate('social media login'))

@section('content')
    <div class="content container-fluid">


        <div class="page-header">
            @include('admin-views.business-settings.partial.third-party-api-navmenu')
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <?php
                    $google=\App\Model\BusinessSetting::where('key','google_social_login')->first()->value;
                    $status = $google == 1 ? 0 : 1;
                ?>
                <div class="card __social-media-login __shadow">
                    <div class="card-body">
                        <div class="__social-media-login-top">
                            <div class="__social-media-login-icon">
                                <img src="{{asset('/public/assets/admin/img/google.png')}}" alt="{{ translate('google') }}">
                            </div>
                            <div class="text-center sub-txt">{{translate('Google Login')}}</div>
                            <div class="custom--switch switch--right change-social-login-status" data-route="{{route('admin.business-settings.web-app.third-party.google-social-login',[$status])}}">
                                <input type="checkbox" id="google_social_login" name="google" switch="primary" class="toggle-switch-input" {{ $google == 1 ? 'checked' : '' }}>
                                <label for="google_social_login" data-on-label="on" data-off-label="off">
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <?php
                    $facebook =\App\Model\BusinessSetting::where('key','facebook_social_login')->first()->value;
                    $status = $facebook == 1 ? 0 : 1;
                ?>
                <div class="card __social-media-login __shadow">
                    <div class="card-body">
                        <div class="__social-media-login-top">
                            <div class="__social-media-login-icon">
                                <img src="{{asset('/public/assets/admin/img/facebook.png')}}" alt="{{ translate('facebook') }}">
                            </div>
                            <div class="text-center sub-txt">{{translate('Facebook Login')}}</div>
                            <div class="custom--switch switch--right change-social-login-status" data-route="{{route('admin.business-settings.web-app.third-party.facebook-social-login',[$status])}}">
                                <input type="checkbox" id="facebook" name="facebook_social_login" switch="primary" class="toggle-switch-input" {{ $facebook == 1 ? 'checked' : '' }}>
                                <label for="facebook" data-on-label="on" data-off-label="off"></label>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <?php
                    $linkedin = optional(\App\Model\BusinessSetting::where('key','linkedin_social_login')->first())->value ?? 0;
                    $status = $linkedin == 1 ? 0 : 1;
                ?>
                <div class="card __social-media-login __shadow">
                    <div class="card-body">
                        <div class="__social-media-login-top">
                            <div class="__social-media-login-icon">
                                <img src="{{asset('/public/assets/admin/img/linkedin.png')}}" alt="{{ translate('linkedin') }}">
                            </div>
                            <div class="text-center sub-txt">{{translate('LinkedIn Login')}}</div>
                            <div class="custom--switch switch--right change-social-login-status" data-route="{{route('admin.business-settings.web-app.third-party.linkedin-social-login',[$status])}}">
                                <input type="checkbox" id="linkedin_social_login" name="linkedin_social_login" switch="primary" class="toggle-switch-input" {{ $linkedin == 1 ? 'checked' : '' }}>
                                <label for="linkedin_social_login" data-on-label="on" data-off-label="off"></label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if (isset($appleLoginService))
                <div class="col-md-6">

                    <div class="card">
                        <form action="{{ route('admin.business-settings.web-app.third-party.update-apple-login') }}" method="post" enctype="multipart/form-data">
                            @csrf
                            <div class="card-header card-header-shadow">
                                <div class="__social-media-login-top flex-grow-1">
                                    <h5 class="card-title align-items-center ">
                                        <img src="{{asset('/public/assets/admin/img/modal/apple.png')}}" class="mr-1 w--20" alt="{{ translate('apple') }}">
                                        {{translate('Apple Login')}}
                                    </h5>
                                    <div class="custom--switch switch--right">
                                        <input type="checkbox" id="apple" name="status" switch="primary" class="toggle-switch-input"
                                            {{$appleLoginService['status'] == 1 ? 'checked' : ''}}>
                                        <label for="apple" data-on-label="on" data-off-label="off"></label>
                                    </div>
                                </div>
                            </div>

                            <div class="card-body text-left">
                                <div class="d-flex justify-content-end">
                                    <div class="text--primary-2 d-flex flex-wrap align-items-center" type="button" data-toggle="modal" data-target="#{{$appleLoginService['login_medium']}}-modal">
                                        <strong class="mr-2 text--underline">{{translate('Credential Setup')}}</strong>
                                        <div class="blinkings">
                                            <i class="tio-info-outined"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">{{translate('client_id')}}</label>
                                    <input type="text" class="form-control" name="client_id" value="{{ $appleLoginService['client_id'] }}">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">{{translate('team_id')}}</label>
                                    <input type="text" class="form-control" name="team_id" value="{{ $appleLoginService['team_id'] }}">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">{{translate('key_id')}}</label>
                                    <input type="text" class="form-control" name="key_id" value="{{ $appleLoginService['key_id'] }}">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">{{translate('service_file')}} {{ $appleLoginService['service_file']?translate('(Already Exists)'):'' }}</label>
                                    <input type="file" accept=".p8" class="form-control" name="service_file"
                                           value="{{ 'storage/app/public/apple-login/'.$appleLoginService['service_file'] }}">
                                </div>
                                <div class="btn--container justify-content-end">
                                    <button type="reset" class="btn btn--reset mb-2">{{translate('Reset')}}</button>
                                    <button type="{{env('APP_MODE')!='demo'?'submit':'button'}}"
                                            class="btn btn--primary mb-2 call-demo">{{translate('save')}}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>

    </div>

    <div class="modal fade" id="apple-modal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog status-warning-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pb-0"><b></b>
                    <div class="text-center mb-20">
                        <img src="{{asset('/public/assets/admin/img/modal/apple.png')}}" alt="" class="mb-3">
                        <h5 class="modal-title mb-2">{{translate('apple_api_set_instruction')}}</h5>
                    </div>
                    <ol>
                        <li>{{translate('Go to Apple Developer page')}} (<a href="https://developer.apple.com/account/resources/identifiers/list" target="_blank">{{translate('click_here')}}</a>)</li>
                        <li>{{translate('Here in top left corner you can see the')}} <b>{{ translate('Team ID') }}</b> {{ translate('[Apple_Deveveloper_Account_Name - Team_ID]')}}</li>
                        <li>{{translate('Click Plus icon -> select App IDs -> click on Continue')}}</li>
                        <li>{{translate('Put a description and also identifier (identifier that used for app) and this is the')}} <b>{{ translate('Client ID') }}</b> </li>
                        <li>{{translate('Click Continue and Download the file in device named AuthKey_ID.p8 (Store it safely and it is used for push notification)')}} </li>
                        <li>{{translate('Again click Plus icon -> select Service IDs -> click on Continue')}} </li>
                        <li>{{translate('Push a description and also identifier and Continue')}} </li>
                        <li>{{translate('Download the file in device named')}} <b>{{ translate('AuthKey_KeyID.p8') }}</b> {{translate('[This is the Service Key ID file and also after AuthKey_ that is the Key ID]')}}</li>
                    </ol>
                </div>
                <div class="modal-footer justify-content-center border-0">
                    <button type="button" class="btn btn--primary w-100 mw-300px" data-dismiss="modal">{{translate('Got It')}}</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script>
        "use strict";

        $('.change-social-login-status').on('click', function(){
            let route = $(this).data('route');
            console.log(route);

            $.get({
                url: route,
                contentType: false,
                processData: false,
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function (data) {
                    setTimeout(function () {
                        location.reload(true);
                    }, 1000);
                    toastr.success(data.message);
                },
                complete: function () {
                    $('#loading').hide();
                },
            });
        })
    </script>
@endpush


