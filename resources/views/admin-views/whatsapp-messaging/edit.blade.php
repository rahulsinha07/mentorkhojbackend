@extends('layouts.admin.app')

@section('title', 'WhatsApp Messaging (Demo)')

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-messages"></i></span>
                <span>WhatsApp Messaging API</span>
            </h1>
            <p class="text-muted mb-0">
                Demo confirmation messages (NEET / JEE / Tech / AI). Separate from WhatsApp OTP login settings.
            </p>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card" style="max-width: 900px;">
            <div class="card-body">
                <form method="post" action="{{ route('admin.whatsapp-messaging.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="form-group">
                        <label class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                            <span class="pr-1 d-flex align-items-center switch--label">
                                <strong>Enabled</strong> — send demo WhatsApp from form
                            </span>
                            <input type="checkbox" class="toggle-switch-input" name="status" value="1"
                                {{ ($settings['status'] ?? 0) == 1 ? 'checked' : '' }}>
                            <span class="toggle-switch-label text">
                                <span class="toggle-switch-indicator"></span>
                            </span>
                        </label>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="input-label">Phone Number ID *</label>
                            <input type="text" name="phone_number_id" class="form-control"
                                   value="{{ $settings['phone_number_id'] ?? '' }}" placeholder="1247043131821693">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="input-label">Access Token *</label>
                            <input type="password" name="access_token" class="form-control" value=""
                                   placeholder="{{ !empty($settings['access_token']) ? 'Saved — leave blank to keep' : 'EAA… permanent system user token' }}"
                                   autocomplete="new-password">
                            @if(!empty($settings['access_token']))
                                <small class="text-muted">Token on file (hidden). Submit a new value only to rotate.</small>
                            @endif
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="input-label">WABA ID (optional)</label>
                            <input type="text" name="waba_id" class="form-control" value="{{ $settings['waba_id'] ?? '' }}">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="input-label">Display phone</label>
                            <input type="text" name="display_phone" class="form-control"
                                   value="{{ $settings['display_phone'] ?? '' }}" placeholder="+91 …">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="input-label">API version</label>
                            <input type="text" name="api_version" class="form-control"
                                   value="{{ $settings['api_version'] ?? 'v21.0' }}">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="input-label">Template language</label>
                            <input type="text" name="template_language" class="form-control"
                                   value="{{ $settings['template_language'] ?? 'en' }}">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="input-label">Business name</label>
                            <input type="text" name="business_name" class="form-control"
                                   value="{{ $settings['business_name'] ?? 'MentorKhoj' }}">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="input-label">Header image URL</label>
                            <input type="text" name="header_image_url" class="form-control"
                                   value="{{ $settings['header_image_url'] ?? 'https://www.mentorkhoj.com/icon.png' }}">
                        </div>
                    </div>

                    <h5 class="mt-2">Demo UTILITY template names</h5>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="input-label">NEET</label>
                            <input type="text" name="template_neet" class="form-control"
                                   value="{{ $settings['templates']['neet'] ?? 'mentorkhoj_util_demo_neet' }}">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="input-label">JEE</label>
                            <input type="text" name="template_jee" class="form-control"
                                   value="{{ $settings['templates']['jee'] ?? 'mentorkhoj_util_demo_jee' }}">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="input-label">Tech</label>
                            <input type="text" name="template_tech" class="form-control"
                                   value="{{ $settings['templates']['tech'] ?? 'mentorkhoj_util_demo_tech' }}">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="input-label">AI/ML</label>
                            <input type="text" name="template_ai" class="form-control"
                                   value="{{ $settings['templates']['ai'] ?? 'mentorkhoj_util_demo_ai' }}">
                        </div>
                    </div>

                    <button class="btn btn-primary" type="submit">Save WhatsApp settings</button>
                </form>
            </div>
        </div>
    </div>
@endsection
