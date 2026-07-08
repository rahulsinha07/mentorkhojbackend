@extends('layouts.admin.app')

@section('title', translate('WhatsApp OTP Verification'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            @include('admin-views.business-settings.partial.third-party-api-navmenu')
        </div>
        <div class="row gx-2 gx-lg-3">
            <div class="col-sm-12 col-lg-12 mb-3 mb-lg-2">
                <div class="card">
                    <div class="card-body">
                        <form action="{{route('admin.business-settings.web-app.third-party.whatsapp-otp-verification-update')}}" method="post">
                            @csrf
                            <?php
                            $whatsappOtp = \App\CentralLogics\Helpers::get_business_settings('whatsapp_otp_verification');
                            ?>
                            <div class="row">
                                <div class="col-md-6" style="padding-top: 30px;">
                                    <div class="form-group">
                                        <label class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                            <span class="pr-1 d-flex align-items-center switch--label">
                                                <span class="line--limit-1">
                                                    <strong>{{translate('WhatsApp OTP Verification Status')}}</strong>
                                                </span>
                                            </span>
                                            <input type="checkbox" class="toggle-switch-input" name="status" {{ isset($whatsappOtp) && ($whatsappOtp['status'] ?? 0) == 1 ? 'checked' : '' }}>
                                            <span class="toggle-switch-label text">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6 col-sm-6">
                                    <div class="form-group mb-0">
                                        <label class="input-label text-capitalize">{{translate('Provider')}}</label>
                                        <select name="provider" class="form-control">
                                            <option value="meta" {{ ($whatsappOtp['provider'] ?? 'meta') === 'meta' ? 'selected' : '' }}>Meta Cloud API</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6 col-sm-6">
                                    <div class="form-group mb-0">
                                        <label class="input-label text-capitalize">Phone Number ID</label>
                                        <input type="text" value="{{ $whatsappOtp && env('APP_MODE')!='demo' ? ($whatsappOtp['phone_number_id'] ?? '') : '' }}" name="phone_number_id" class="form-control" placeholder="">
                                    </div>
                                </div>
                                <div class="col-md-6 col-sm-6">
                                    <div class="form-group mb-0">
                                        <label class="input-label text-capitalize">Access Token</label>
                                        <input type="text" value="{{ $whatsappOtp && env('APP_MODE')!='demo' ? ($whatsappOtp['access_token'] ?? '') : '' }}" name="access_token" class="form-control" placeholder="">
                                    </div>
                                </div>
                                <div class="col-md-6 col-sm-6">
                                    <div class="form-group mb-0">
                                        <label class="input-label text-capitalize">Template Name</label>
                                        <input type="text" value="{{ $whatsappOtp['template_name'] ?? 'mentorkhoj_otp' }}" name="template_name" class="form-control" placeholder="mentorkhoj_otp">
                                    </div>
                                </div>
                                <div class="col-md-6 col-sm-6">
                                    <div class="form-group mb-0">
                                        <label class="input-label text-capitalize">Template Language</label>
                                        <input type="text" value="{{ $whatsappOtp['template_language'] ?? 'en' }}" name="template_language" class="form-control" placeholder="en">
                                    </div>
                                </div>
                                <div class="col-md-6 col-sm-6">
                                    <div class="form-group mb-0">
                                        <label class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                            <span class="pr-1 d-flex align-items-center switch--label">
                                                <span class="line--limit-1">
                                                    <strong>Include copy-code button parameter</strong>
                                                </span>
                                            </span>
                                            <input type="checkbox" class="toggle-switch-input" name="include_copy_code_button" {{ !isset($whatsappOtp) || ($whatsappOtp['include_copy_code_button'] ?? 1) == 1 ? 'checked' : '' }}>
                                            <span class="toggle-switch-label text">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <p class="text-muted mt-3 mb-0">
                                Meta webhook (optional): <code>{{ url('/api/v1/whatsapp/webhook') }}</code>.
                                Create an approved authentication template (e.g. body with one OTP variable).
                            </p>
                            <div class="btn--container justify-content-end mt-3">
                                <button type="reset" class="btn btn--reset">{{translate('clear')}}</button>
                                <button type="{{env('APP_MODE')!='demo'?'submit':'button'}}"
                                        class="btn btn--primary call-demo">{{translate('submit')}}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
