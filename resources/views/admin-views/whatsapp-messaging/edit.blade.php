@extends('layouts.admin.app')

@section('title', 'WhatsApp Messaging')

@push('css_or_js')
    <style>
        .wa-inbox { display: grid; grid-template-columns: 320px 1fr; gap: 16px; min-height: 640px; }
        @media (max-width: 991px) { .wa-inbox { grid-template-columns: 1fr; } }
        .wa-pane { background: #fff; border: 1px solid #e7eaf3; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; min-height: 640px; }
        .wa-pane-head { padding: 14px 16px; border-bottom: 1px solid #eef1f6; background: #f8fafc; }
        .wa-thread-list { overflow: auto; flex: 1; }
        .wa-thread-item { display: block; padding: 12px 14px; border-bottom: 1px solid #f0f2f7; color: inherit; text-decoration: none; }
        .wa-thread-item:hover { background: #f5f8ff; text-decoration: none; color: inherit; }
        .wa-thread-item.active { background: #e8f0fe; border-left: 3px solid #2563eb; }
        .wa-thread-item .wa-preview { color: #64748b; font-size: 12px; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .wa-thread-item .wa-meta { color: #94a3b8; font-size: 11px; }
        .wa-chat { background: linear-gradient(180deg, #eef6f0 0%, #e8f0ea 100%); flex: 1; overflow: auto; padding: 16px; }
        .wa-bubble-row { display: flex; margin-bottom: 10px; }
        .wa-bubble-row.out { justify-content: flex-end; }
        .wa-bubble { max-width: min(78%, 520px); padding: 10px 12px; border-radius: 12px; box-shadow: 0 1px 1px rgba(0,0,0,.06); word-break: break-word; white-space: pre-wrap; font-size: 14px; line-height: 1.45; }
        .wa-bubble.in { background: #fff; border-top-left-radius: 4px; }
        .wa-bubble.out { background: #dcf8c6; border-top-right-radius: 4px; }
        .wa-bubble .wa-foot { margin-top: 6px; font-size: 11px; color: #64748b; display: flex; gap: 6px; flex-wrap: wrap; }
        .wa-stats span { display: inline-block; margin-right: 12px; color: #475569; font-size: 13px; }
        .wa-search { border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 10px; width: 100%; }
        .wa-empty { color: #64748b; text-align: center; padding: 48px 16px; }
        .wa-live { font-size: 12px; color: #16a34a; }
        .wa-live.idle { color: #94a3b8; }
    </style>
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header d-flex flex-wrap justify-content-between align-items-start">
            <div>
                <h1 class="page-header-title">
                    <span class="page-header-icon"><i class="tio-messages"></i></span>
                    <span>WhatsApp Messaging</span>
                </h1>
                <p class="text-muted mb-0">
                    Live inbox for +91 91026 95888 — webhook messages and admin sends.
                </p>
                <div class="wa-stats mt-2">
                    <span><strong id="wa-stat-conversations">{{ $stats['conversations'] ?? 0 }}</strong> conversations</span>
                    <span><strong id="wa-stat-inbound">{{ $stats['inbound'] ?? 0 }}</strong> received</span>
                    <span><strong id="wa-stat-outbound">{{ $stats['outbound'] ?? 0 }}</strong> sent</span>
                    <span><strong id="wa-stat-total">{{ $stats['total'] ?? 0 }}</strong> total</span>
                </div>
            </div>
            <div class="text-right mt-2">
                <div class="wa-live idle" id="wa-live-dot">Polling…</div>
                <small class="text-muted d-block">Webhook: <code>{{ $webhookUrl }}</code></small>
            </div>
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

        <div class="wa-inbox mb-4">
            <div class="wa-pane">
                <div class="wa-pane-head">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>Conversations</strong>
                        <a class="small" href="{{ route('admin.whatsapp-messaging.edit') }}">Refresh</a>
                    </div>
                    <input type="search" class="wa-search" id="wa-thread-filter" placeholder="Search name or number…">
                </div>
                <div class="wa-thread-list" id="wa-thread-list">
                    @forelse($threads as $thread)
                        <a class="wa-thread-item {{ $activeWaId === $thread->wa_id ? 'active' : '' }}"
                           data-wa-id="{{ $thread->wa_id }}"
                           data-search="{{ strtolower(($thread->contact_name ?: '') . ' ' . $thread->wa_id) }}"
                           href="{{ route('admin.whatsapp-messaging.edit', ['wa_id' => $thread->wa_id]) }}">
                            <div class="d-flex justify-content-between">
                                <strong>{{ $thread->contact_name ?: $thread->wa_id }}</strong>
                                <span class="wa-meta">{{ optional($thread->occurred_at)->format('d M H:i') }}</span>
                            </div>
                            @if($thread->contact_name)
                                <div class="wa-meta">{{ $thread->wa_id }}</div>
                            @endif
                            <div class="wa-preview">
                                {{ $thread->direction === 'out' ? 'You: ' : '' }}{{ \Illuminate\Support\Str::limit((string) $thread->body, 70) }}
                            </div>
                        </a>
                    @empty
                        <p class="wa-empty mb-0">No messages yet. When a customer texts +91 91026 95888, the conversation appears here.</p>
                    @endforelse
                </div>
            </div>

            <div class="wa-pane">
                <div class="wa-pane-head d-flex justify-content-between align-items-center">
                    <div>
                        <strong>
                            @if($activeWaId)
                                {{ optional($threads->firstWhere('wa_id', $activeWaId))->contact_name ?: $activeWaId }}
                            @else
                                New message
                            @endif
                        </strong>
                        @if($activeWaId)
                            <div class="small text-muted">{{ $activeWaId }}</div>
                        @endif
                    </div>
                    @if($activeWaId)
                        @if($windowOpen)
                            <span class="badge badge-soft-success">24h window open</span>
                        @else
                            <span class="badge badge-soft-warning">Use template (window closed)</span>
                        @endif
                    @endif
                </div>

                <div class="wa-chat" id="wa-thread">
                    @forelse($messages as $msg)
                        <div class="wa-bubble-row {{ $msg->direction === 'out' ? 'out' : 'in' }}" data-msg-id="{{ $msg->id }}">
                            <div class="wa-bubble {{ $msg->direction === 'out' ? 'out' : 'in' }}">
                                <div>{{ $msg->body !== '' && $msg->body !== null ? $msg->body : '[' . $msg->type . ']' }}</div>
                                <div class="wa-foot">
                                    <span>{{ $msg->direction === 'out' ? 'Sent' : 'Received' }}</span>
                                    <span>{{ $msg->type }}</span>
                                    @if($msg->status)<span>{{ $msg->status }}</span>@endif
                                    <span>{{ optional($msg->occurred_at)->format('d M Y H:i') }}</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="wa-empty" id="wa-empty-thread">
                            @if($activeWaId)
                                No messages in this thread yet.
                            @else
                                Select a conversation or send a new message.
                            @endif
                        </div>
                    @endforelse
                </div>

                <div class="p-3 border-top">
                    @if($activeWaId)
                        @if($windowOpen)
                            <div class="alert alert-success py-2 mb-2">
                                Customer window <strong>open</strong> — free-form text works. Templates work anytime.
                            </div>
                        @else
                            <div class="alert alert-warning py-2 mb-2">
                                Customer window <strong>closed</strong>. Use <strong>Template</strong> to message anytime. Received messages still show forever.
                            </div>
                        @endif
                    @else
                        <div class="alert alert-info py-2 mb-2">
                            Received messages always appear. Free-form text only within 24h of a customer reply; use Template anytime.
                        </div>
                    @endif

                    <form method="post" action="{{ route('admin.whatsapp-messaging.send') }}" id="wa-send-form">
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
                                    <option value="neet" {{ ($defaultTemplateKey ?? 'neet') === 'neet' ? 'selected' : '' }}>Demo NEET (approved — anytime)</option>
                                    <option value="jee" {{ ($defaultTemplateKey ?? '') === 'jee' ? 'selected' : '' }}>Demo JEE (approved — anytime)</option>
                                    <option value="tech" {{ ($defaultTemplateKey ?? '') === 'tech' ? 'selected' : '' }}>Demo Tech (approved — anytime)</option>
                                    <option value="ai" {{ ($defaultTemplateKey ?? '') === 'ai' ? 'selected' : '' }}>Demo AI/ML (approved — anytime)</option>
                                    <option value="session_confirmed" {{ ($defaultTemplateKey ?? '') === 'session_confirmed' ? 'selected' : '' }}>Session confirmed</option>
                                    <option value="session_scheduled" {{ ($defaultTemplateKey ?? '') === 'session_scheduled' ? 'selected' : '' }}>Session scheduled (mentor + mentee, with join link)</option>
                                    <option value="followup" {{ ($defaultTemplateKey ?? '') === 'followup' ? 'selected' : '' }}>Follow-up free text (needs Meta approval)</option>
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
                            <div class="col-md form-group">
                                <label class="input-label">Param 6</label>
                                <input type="text" name="param6" class="form-control" placeholder="Optional">
                            </div>
                        </div>
                        <button class="btn btn-primary" type="submit">Send WhatsApp</button>
                    </form>
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
            var chatEl = document.getElementById('wa-thread');
            var listEl = document.getElementById('wa-thread-list');
            var filterEl = document.getElementById('wa-thread-filter');
            var liveEl = document.getElementById('wa-live-dot');
            var pollUrl = @json($pollUrl ?? '');
            var activeWaId = @json($activeWaId ?? '');
            var afterId = {{ (int) ($messages->max('id') ?: 0) }};

            function sync() {
                if (!modeEl) return;
                var isTemplate = modeEl.value === 'template';
                if (templateWrap) templateWrap.style.display = isTemplate ? '' : 'none';
                var key = keyEl ? keyEl.value : 'followup';
                if (customWrap) customWrap.classList.toggle('d-none', !(isTemplate && key === 'custom'));
                if (paramsWrap) {
                    var showParams = isTemplate && ['neet', 'jee', 'tech', 'ai', 'session_confirmed', 'session_scheduled', 'custom'].indexOf(key) !== -1;
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

            function scrollChat() {
                if (chatEl) chatEl.scrollTop = chatEl.scrollHeight;
            }

            function escapeHtml(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function appendMessage(msg) {
                if (!chatEl || !msg || !msg.id) return;
                if (chatEl.querySelector('[data-msg-id="' + msg.id + '"]')) return;
                var empty = document.getElementById('wa-empty-thread');
                if (empty) empty.remove();
                var row = document.createElement('div');
                row.className = 'wa-bubble-row ' + (msg.direction === 'out' ? 'out' : 'in');
                row.setAttribute('data-msg-id', msg.id);
                var body = msg.body && String(msg.body).length ? msg.body : ('[' + (msg.type || 'msg') + ']');
                row.innerHTML =
                    '<div class="wa-bubble ' + (msg.direction === 'out' ? 'out' : 'in') + '">' +
                    '<div>' + escapeHtml(body) + '</div>' +
                    '<div class="wa-foot">' +
                    '<span>' + (msg.direction === 'out' ? 'Sent' : 'Received') + '</span>' +
                    '<span>' + escapeHtml(msg.type || '') + '</span>' +
                    (msg.status ? '<span>' + escapeHtml(msg.status) + '</span>' : '') +
                    '<span>' + escapeHtml(msg.occurred_label || '') + '</span>' +
                    '</div></div>';
                chatEl.appendChild(row);
                afterId = Math.max(afterId, parseInt(msg.id, 10) || 0);
                scrollChat();
            }

            function renderThreads(threads) {
                if (!listEl || !Array.isArray(threads)) return;
                var html = '';
                if (!threads.length) {
                    listEl.innerHTML = '<p class="wa-empty mb-0">No messages yet. When a customer texts +91 91026 95888, the conversation appears here.</p>';
                    return;
                }
                threads.forEach(function (t) {
                    var active = t.wa_id === activeWaId ? ' active' : '';
                    var title = t.contact_name || t.wa_id;
                    var preview = (t.direction === 'out' ? 'You: ' : '') + (t.body || '');
                    html +=
                        '<a class="wa-thread-item' + active + '" data-wa-id="' + escapeHtml(t.wa_id) + '"' +
                        ' data-search="' + escapeHtml(String(title + ' ' + t.wa_id).toLowerCase()) + '"' +
                        ' href="?wa_id=' + encodeURIComponent(t.wa_id) + '">' +
                        '<div class="d-flex justify-content-between"><strong>' + escapeHtml(title) + '</strong>' +
                        '<span class="wa-meta">' + escapeHtml(t.occurred_label || '') + '</span></div>' +
                        (t.contact_name ? '<div class="wa-meta">' + escapeHtml(t.wa_id) + '</div>' : '') +
                        '<div class="wa-preview">' + escapeHtml(preview).slice(0, 90) + '</div></a>';
                });
                listEl.innerHTML = html;
                applyFilter();
            }

            function applyFilter() {
                if (!filterEl || !listEl) return;
                var q = (filterEl.value || '').toLowerCase().trim();
                listEl.querySelectorAll('.wa-thread-item').forEach(function (el) {
                    var hay = el.getAttribute('data-search') || '';
                    el.style.display = !q || hay.indexOf(q) !== -1 ? '' : 'none';
                });
            }

            function updateStats(stats) {
                if (!stats) return;
                ['conversations', 'inbound', 'outbound', 'total'].forEach(function (k) {
                    var el = document.getElementById('wa-stat-' + k);
                    if (el && stats[k] != null) el.textContent = stats[k];
                });
            }

            function poll() {
                if (!pollUrl) return;
                var url = pollUrl + '?wa_id=' + encodeURIComponent(activeWaId || '') + '&after_id=' + encodeURIComponent(afterId || 0);
                fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data || !data.ok) return;
                        if (liveEl) {
                            liveEl.textContent = 'Live · updated ' + new Date().toLocaleTimeString();
                            liveEl.classList.remove('idle');
                        }
                        if (Array.isArray(data.messages) && afterId > 0) {
                            data.messages.forEach(appendMessage);
                        }
                        if (data.max_id) afterId = Math.max(afterId, parseInt(data.max_id, 10) || 0);
                        renderThreads(data.threads || []);
                        updateStats(data.stats);
                    })
                    .catch(function () {
                        if (liveEl) {
                            liveEl.textContent = 'Poll paused — retrying';
                            liveEl.classList.add('idle');
                        }
                    });
            }

            if (modeEl) modeEl.addEventListener('change', sync);
            if (keyEl) keyEl.addEventListener('change', sync);
            if (filterEl) filterEl.addEventListener('input', applyFilter);
            sync();
            scrollChat();

            if (pollUrl) {
                setInterval(poll, 5000);
                setTimeout(poll, 1500);
            }
        })();
    </script>
@endpush
