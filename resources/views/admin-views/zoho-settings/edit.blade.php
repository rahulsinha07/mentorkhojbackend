@extends('layouts.admin.app')

@section('title', translate('Zoho Meeting Settings'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/order.png') }}" class="w--24" alt="">
                </span>
                <span>{{ translate('Zoho Meeting Settings') }}</span>
            </h1>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <small class="text-muted d-block">{{ translate('Connection status') }}</small>
                        @if($setting->status === 'connected')
                            <span class="badge badge-soft-success">{{ translate('Connected') }}</span>
                            @if($setting->connected_at)
                                <small class="text-muted ml-1">{{ translate('since') }} {{ $setting->connected_at->format('d M Y, h:i A') }}</small>
                            @endif
                        @elseif($setting->status === 'error')
                            <span class="badge badge-soft-danger">{{ translate('Error') }}</span>
                        @else
                            <span class="badge badge-soft-secondary">{{ translate('Disconnected') }}</span>
                        @endif
                    </div>
                    <div>
                        @if($setting->isConnected())
                            <form method="post" action="{{ route('admin.zoho-settings.test') }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary">{{ translate('Test Connection') }}</button>
                            </form>
                            <form method="get" action="{{ route('admin.zoho-settings.disconnect') }}" class="d-inline"
                                  onsubmit="return confirm('{{ translate('Disconnect from Zoho? Auto-generated meeting links will stop working until reconnected.') }}');">
                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ translate('Disconnect') }}</button>
                            </form>
                        @else
                            <a href="{{ route('admin.zoho-settings.connect') }}" class="btn btn-sm btn-success">{{ translate('Connect Zoho') }}</a>
                        @endif
                    </div>
                </div>

                @if($setting->last_error)
                    <div class="alert alert-warning">
                        <strong>{{ translate('Last error') }}:</strong> {{ $setting->last_error }}
                    </div>
                @endif

                <hr>

                <form action="{{ route('admin.zoho-settings.update') }}" method="post">
                    @csrf
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label class="form-label">{{ translate('Client ID') }}</label>
                            <input type="text" name="client_id" class="form-control" value="{{ $setting->client_id }}" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label class="form-label">{{ translate('Client Secret') }}</label>
                            <input type="password" name="client_secret" class="form-control"
                                   placeholder="{{ $setting->client_secret ? '••••••••••••••••' : translate('Enter client secret') }}"
                                   autocomplete="new-password">
                            <small class="text-muted">{{ translate('Leave blank to keep the current saved secret.') }}</small>
                        </div>
                    </div>
                    <div class="btn--container justify-content-end">
                        <button type="submit" class="btn btn-primary">{{ translate('Save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h6>{{ translate('How this works') }}</h6>
                <ol class="mb-0">
                    <li>{{ translate('Paste the Client ID and Client Secret from your Zoho API Console (Server-based Application) above and Save.') }}</li>
                    <li>{{ translate('Click Connect Zoho and approve access on the Zoho consent screen.') }}</li>
                    <li>{{ translate('A test meeting is created and deleted automatically to confirm the connection works. You can re-run this anytime with Test Connection.') }}</li>
                    <li>{{ translate('Once connected, mentor sessions automatically get a Zoho Meeting link the moment they\'re scheduled. If Zoho is unreachable for a specific session (expired token, Zoho-side rate limit, etc.), the mentor sees the exact reason on that booking and can retry or add a link manually — scheduling itself is never blocked.') }}</li>
                    <li>{{ translate('After a session ends, the recording, transcript and AI summary are fetched from Zoho automatically (checked hourly) and shown to both the mentor and student on the booking, with an option to translate the transcript into another language.') }}</li>
                </ol>
                @if($setting->isConnected())
                    <div class="alert alert-info mt-3 mb-0 p-2" style="font-size: 13px;">
                        {{ translate('Recording access was added on 9 Nov 2026. If this connection was made before that date, click Disconnect then Connect again so Zoho grants the new recording permission — otherwise recordings/transcripts will silently stay unavailable.') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
