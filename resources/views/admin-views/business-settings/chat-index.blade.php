@extends('layouts.admin.app')

@section('title', translate('Chat Settings'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            @include('admin-views.business-settings.partial.third-party-api-navmenu')
        </div>
        <div class="row gx-2 gx-lg-3">

            <div class="col-sm-12 col-lg-12 mb-3 mb-lg-2">

                <div class="card">

                    <div class="card-body">
                        <form action="{{route('admin.business-settings.web-app.third-party.update-chat')}}" method="post" enctype="multipart/form-data">
                            @csrf

                            <div class="row">
                                @php($whatsapp=\App\Model\BusinessSetting::where('key','whatsapp')->first()->value)
                                @php($whatsapp_data=json_decode($whatsapp,true))
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label class="toggle-switch d-flex align-items-center mb-3" for="whatsapp_status">
                                            <input type="checkbox" name="whatsapp_status" class="toggle-switch-input"
                                                   value="1" id="whatsapp_status" {{$whatsapp_data['status']==1?'checked':''}}>
                                            <span class="toggle-switch-label">
                                                <span class="toggle-switch-indicator"></span>
                                              </span>
                                            <span class="toggle-switch-content">
                                                <span class="d-block">{{translate('whatsapp')}} {{translate('status')}}</span>
                                            </span>
                                        </label>
                                        <label class="text-capitalize" class="form-label">{{translate('Whatsapp Number')}}<span class="text-danger"> ({{ translate('without country code') }})</span></label>
                                        <input type="text" name="whatsapp_number"  class="form-control" placeholder="{{ translate('number') }}" value="{{$whatsapp_data['number']}}">
                                    </div>
                                </div>

                                @php($telegram=\App\Model\BusinessSetting::where('key','telegram')->first()->value)
                                @php($telegram_data=json_decode($telegram,true))
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label class="toggle-switch d-flex align-items-center mb-3" for="telegram_status">
                                            <input type="checkbox" name="telegram_status" class="toggle-switch-input"
                                                   value="1" id="telegram_status" {{$telegram_data['status']==1?'checked':''}}>
                                            <span class="toggle-switch-label">
                                                <span class="toggle-switch-indicator"></span>
                                              </span>
                                            <span class="toggle-switch-content">
                                            <span class="d-block">{{translate('telegram')}} {{translate('status')}}</span>
                                          </span>
                                        </label>
                                        <label class="text-capitalize" class="form-label">{{translate('Telegram User Name')}}<span class="text-danger"> ({{ translate('without @') }})</span></label>
                                        <input type="text" name="telegram_user_name"  class="form-control" placeholder="{{ translate('user name') }}" value="{{$telegram_data['user_name']}}">
                                    </div>
                                </div>

                                @php($messenger=\App\Model\BusinessSetting::where('key','messenger')->first()->value)
                                @php($messenger_data=json_decode($messenger,true))
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label class="toggle-switch d-flex align-items-center mb-3" for="messenger_status">
                                            <input type="checkbox" name="messenger_status" class="toggle-switch-input"
                                                   value="1" id="messenger_status" {{$messenger_data['status']==1?'checked':''}}>
                                            <span class="toggle-switch-label">
                                                <span class="toggle-switch-indicator"></span>
                                              </span>
                                            <span class="toggle-switch-content">
                                            <span class="d-block">{{translate('messenger')}} {{translate('status')}}</span>
                                          </span>
                                        </label>
                                        <label class="text-capitalize" class="form-label">{{translate('Messenger User Name')}}</label>
                                        <input type="text" name="messenger_user_name"  class="form-control" placeholder="{{ translate('user name') }}" value="{{$messenger_data['user_name']}}">
                                    </div>
                                </div>

                            </div>
                            <div class="btn--container justify-content-end">
                                <button type="reset" class="btn btn--reset">{{translate('clear')}}</button>
                                <button type="submit" class="btn btn--primary">{{translate('submit')}}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

