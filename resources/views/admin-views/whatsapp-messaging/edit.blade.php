@extends('layouts.admin.app')

@section('title', 'WhatsApp Messaging')

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-messages"></i></span>
                <span>WhatsApp Messaging</span>
            </h1>
            <p class="text-muted mb-0">
                Mentorkhoj Cloud API inbox (+91 91026 95888). Incoming webhooks and outgoing Graph sends appear here.
            </p>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if(!$tableReady)
            <div class="alert alert-warning">Message table is missing. Run <code>php artisan migrate</code>.</div>
        @endif

        <div class="row">
            <div class="col-lg-4 mb-3">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Conversations</h5>
                    </div>
                    <div class="card-body p-0" style="max-height: 520px; overflow: auto;">
                        @forelse($threads as $thread)
                            <a class="d-block px-3 py-2 border-bottom text-dark {{ $activeWaId === $thread->wa_id ? 'bg-light' : '' }}"
                               href="{{ route('admin.whatsapp-messaging.edit', ['wa_id' => $thread->wa_id]) }}">
                                <strong>{{ $thread->contact_name ?: $thread->wa_id }}</strong>
                                <div class="small text-muted">{{ $thread->wa_id }}</div>
                            </a>
                        @empty
                            <p class="text-muted p-3 mb-0">No messages yet. Send below or wait for a customer reply after the webhook is subscribed.</p>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-lg-8 mb-3">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            @if($activeWaId)
                                Thread {{ $activeWaId }}
                            @else
                                New message
                            @endif
                        </h5>
                        <small class="text-muted">Webhook: <code>{{ $webhookUrl }}</code></small>
                    </div>
                    <div class="card-body">
                        <div style="max-height: 360px; overflow: auto; background: #f8fafc; border-radius: 8px; padding: 12px;" id="wa-thread">
                            @forelse($messages as $msg)
                                <div class="mb-2 {{ $msg->direction === 'out' ? 'text-right' : '' }}">
                                    <div class="d-inline-block px-3 py-2 rounded {{ $msg->direction === 'out' ? 'bg-primary text-white' : 'bg-white border' }}"
                                         style="max-width: 85%; text-align: left;">
                                        <div>{{ $msg->body }}</div>
                                        <div class="small {{ $msg->direction === 'out' ? 'text-white-50' : 'text-muted' }} mt-1">
                                            {{ $msg->direction === 'out' ? 'Sent' : 'Received' }}
                                            · {{ $msg->type }}
                                            @if($msg->status) · {{ $msg->status }} @endif
                                            · {{ optional($msg->occurred_at)->format('d M H:i') }}
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted mb-0">Select a conversation or send a new message.</p>
                            @endforelse
                        </div>

                        @if($activeWaId)
                            @if($windowOpen)
                                <div class="alert alert-success py-2 mt-3 mb-2">
                                    Customer window <strong>open</strong> — free-form text works. Templates work anytime.
                                </div>
                            @else
                                <div class="alert alert-warning py-2 mt-3 mb-2">
                                    Customer window <strong>closed</strong> (no inbound in last 24h). Use <strong>Template</strong> to message anytime. All received messages still show here forever.
                                </div>
                            @endif
                        @else
                            <div class="alert alert-info py-2 mt-3 mb-2">
                                Received messages always appear (no time limit). Free-form text only within 24h of a customer reply; use Template to start or continue anytime.
                            </div>
                        @endif

                        <form class="mt-2" method="post" action="{{ route('admin.whatsapp-messaging.send') }}" id="wa-send-form">
                            @csrf
                            <div class="form-row">
                                <div class="col-md-4 form-group">
                                    <label class="input-label">To (WhatsApp number)</label>
                                    <input type="text" name="phone" class="form-control" required
                                           value="{{ $activeWaId }}"
                                           placeholder="91xxxxxxxxxx — not 9102695888">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label class="input-label">Send mode</label>
                                    <select name="send_mode" id="wa-send-mode" class="form-control" required>
                                        <option value="text" {{ ($defaultSendMode ?? 'text') === 'text' ? 'selected' : '' }}>Text (within 24h window)</option>
                                        <option value="template" {{ ($defaultSendMode ?? 'text') === 'template' ? 'selected' : '' }}>Template (anytime)</option>
                                    </select>
                                </div>
                                <div class="col-md-4 form-group" id="wa-template-wrap">
                                    <label class="input-label">Template</label>
                                    <select name="template_key" id="wa-template-key" class="form-control">
                                        <option value="followup">Follow-up (free text → body variable)</option>
                                        <option value="neet">Demo NEET</option>
                                        <option value="jee">Demo JEE</option>
                                        <option value="tech">Demo Tech</option>
                                        <option value="ai">Demo AI/ML</option>
                                        <option value="session_confirmed">Session confirmed</option>
                                        <option value="custom">Custom template name</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group d-none" id="wa-custom-template-wrap">
                                <label class="input-label">Custom Meta template name</label>
                                <input type="text" name="template_name" class="form-control" placeholder="approved_template_name">
                            </div>
                            <div class="form-group">
                                <label class="input-label" id="wa-body-label">Message</label>
                                <textarea name="body" id="wa-body" class="form-control" rows="2" maxlength="4096"
                                          placeholder="Your message"></textarea>
                                <small class="text-muted" id="wa-body-hint">
                                    Text mode: free reply inside 24h. Template follow-up: body becomes the template’s first variable.
                                </small>
                            </div>
                            <div class="form-row d-none" id="wa-params-wrap">
                                <div class="col-md form-group">
                                    <label class="input-label">Param 1 (name)</label>
                                    <input type="text" name="param1" class="form-control" placeholder="Optional">
                                </div>
                                <div class="col-md form-group">
                                    <label class="input-label">Param 2</label>
                                    <input type="text" name="param2" class="form-control" placeholder="Optional">
                                </div>
                                <div class="col-md form-group">
                                    <label class="input-label">Param 3</label>
                                    <input type="text" name="param3" class="form-control" placeholder="Optional">
                                </div>
                                <div class="col-md form-group">
                                    <label class="input-label">Param 4</label>
                                    <input type="text" name="param4" class="form-control" placeholder="Optional">
                                </div>
                                <div class="col-md form-group">
                                    <label class="input-label">Param 5</label>
                                    <input type="text" name="param5" class="form-control" placeholder="Optional">
                                </div>
                            </div>
                            <button class="btn btn-primary" type="submit">Send WhatsApp</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="max-width: 900px;">
            <div class="card-header"><h5 class="card-title mb-0">API settings</h5></div>
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
                                   value="{{ $settings['phone_number_id'] ?? '1247043131821693' }}" placeholder="1247043131821693">
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
                                   value="{{ $settings['display_phone'] ?? '+91 91026 95888' }}" placeholder="+91 91026 95888">
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
                        <div class="col-md-6 form-group">
                            <label class="input-label">Follow-up (anytime free text)</label>
                            <input type="text" name="template_followup" class="form-control"
                                   value="{{ $settings['templates']['followup'] ?? 'mentorkhoj_util_followup' }}"
                                   placeholder="mentorkhoj_util_followup">
                            <small class="text-muted">Approved Meta template with one body variable for your message. Required to send free text outside 24h.</small>
                        </div>
                    </div>

                    <button class="btn btn-primary" type="submit">Save WhatsApp settings</button>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        (function () {
            var modeEl = document.getElementById('wa-send-mode');
            var keyEl = document.getElementById('wa-template-key');
            var templateWrap = document.getElementById('wa-template-wrap');
            var customWrap = document.getElementById('wa-custom-template-wrap');
            var paramsWrap = document.getElementById('wa-params-wrap');
            var bodyEl = document.getElementById('wa-body');
            var hintEl = document.getElementById('wa-body-hint');

            function sync() {
                if (!modeEl) return;
                var isTemplate = modeEl.value === 'template';
                if (templateWrap) templateWrap.style.display = isTemplate ? '' : 'none';
                var key = keyEl ? keyEl.value : 'followup';
                if (customWrap) customWrap.classList.toggle('d-none', !(isTemplate && key === 'custom'));
                if (paramsWrap) {
                    var showParams = isTemplate && ['neet', 'jee', 'tech', 'ai', 'session_confirmed', 'custom'].indexOf(key) !== -1;
                    paramsWrap.classList.toggle('d-none', !showParams);
                }
                if (bodyEl) {
                    if (!isTemplate) {
                        bodyEl.placeholder = 'Free-form text (only within 24h of customer reply)';
                        bodyEl.required = true;
                    } else if (key === 'followup' || key === 'custom') {
                        bodyEl.placeholder = 'Message text for template body variable';
                        bodyEl.required = true;
                    } else {
                        bodyEl.placeholder = 'Optional note (demo templates use Param 1 as name)';
                        bodyEl.required = false;
                    }
                }
                if (hintEl) {
                    hintEl.textContent = isTemplate
                        ? 'Templates can be sent anytime. Follow-up needs an APPROVED Meta template with a body variable.'
                        : 'Received messages always show. Free-form send only works inside the 24-hour customer window.';
                }
            }

            if (modeEl) modeEl.addEventListener('change', sync);
            if (keyEl) keyEl.addEventListener('change', sync);
            sync();

            @if($activeWaId)
            setTimeout(function () { window.location.reload(); }, 20000);
            @endif
        })();
    </script>
@endpush
