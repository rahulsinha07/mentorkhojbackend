@extends('layouts.admin.app')

@section('title', 'Students by Section')

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-group-equal"></i></span>
                <span>Students by Section <span class="badge badge-soft-secondary">{{ $total_all ?? 0 }}</span></span>
            </h1>
            <p class="text-muted mb-0">
                Live list from demo bookings + mentor bookings. Use section tabs to see NEET, IIT-JEE, Tech, AI/ML, or Other together.
            </p>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <ul class="nav nav-tabs nav-tabs-custom mb-3 flex-nowrap overflow-auto">
            <li class="nav-item">
                <a class="nav-link {{ empty($filterSection) ? 'active' : '' }}"
                   href="{{ route('admin.student-sections.index', array_filter(['source' => $filterSource ?: null, 'q' => $q ?: null])) }}">
                    All <span class="badge badge-soft-secondary">{{ $total_all ?? 0 }}</span>
                </a>
            </li>
            @foreach(($sections ?? []) as $key => $label)
                <li class="nav-item">
                    <a class="nav-link {{ ($filterSection ?? '') === $key ? 'active' : '' }}"
                       href="{{ route('admin.student-sections.index', array_filter(['section' => $key, 'source' => $filterSource ?: null, 'q' => $q ?: null])) }}">
                        {{ $label }} <span class="badge badge-soft-secondary">{{ $counts[$key] ?? 0 }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Default WhatsApp messages by section</h5>
                <p class="text-muted small mb-0">Placeholders: <code>{name}</code>, <code>{section}</code>. Used when a row has no saved draft.</p>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('admin.student-sections.templates') }}">
                    @csrf
                    @method('PUT')
                    <div class="row">
                        @foreach(($sections ?? []) as $key => $label)
                            <div class="col-md-6 mb-3">
                                <label class="input-label">{{ $label }}</label>
                                <textarea name="templates[{{ $key }}]" rows="4" class="form-control">{{ old('templates.'.$key, $templates[$key] ?? '') }}</textarea>
                            </div>
                        @endforeach
                    </div>
                    <button type="submit" class="btn btn-primary">Save templates</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-0">
                <form method="get" class="row g-2 align-items-end">
                    @if(!empty($filterSection))
                        <input type="hidden" name="section" value="{{ $filterSection }}">
                    @endif
                    <div class="col-md-4">
                        <label class="input-label">Search</label>
                        <input type="text" name="q" value="{{ $q }}" placeholder="Name, phone, email" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="input-label">Source</label>
                        <select name="source" class="form-control">
                            <option value="">All sources</option>
                            <option value="demo" {{ ($filterSource ?? '') === 'demo' ? 'selected' : '' }}>Demo only</option>
                            <option value="mentor" {{ ($filterSource ?? '') === 'mentor' ? 'selected' : '' }}>Mentor only</option>
                            <option value="both" {{ ($filterSource ?? '') === 'both' ? 'selected' : '' }}>Both</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <button class="btn btn-primary" type="submit">Filter</button>
                        <a class="btn btn-outline-secondary" href="{{ route('admin.student-sections.index', array_filter(['section' => $filterSection ?: null])) }}">Reset</a>
                    </div>
                </form>
                @if(!empty($filterSection) && isset($sections[$filterSection]))
                    <h5 class="mt-3 mb-0">Showing: {{ $sections[$filterSection] }} only</h5>
                @endif
            </div>

            <div class="table-responsive">
                <table class="table table-borderless table-thead-bordered table-align-middle card-table">
                    <thead class="thead-light">
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Source</th>
                        <th>Latest</th>
                        <th style="min-width: 280px;">WhatsApp message</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @php $lastSection = null; @endphp
                    @forelse($students as $s)
                        @php
                            $rowId = 'ss-'.md5(($s['phone_digits'] ?? '').'|'.($s['email'] ?? '').'|'.($s['user_id'] ?? ''));
                            $sec = $s['section'] ?? 'other';
                        @endphp
                        @if(empty($filterSection) && $sec !== $lastSection)
                            @php $lastSection = $sec; @endphp
                            <tr class="table-active">
                                <td colspan="7" class="font-weight-bold py-2">
                                    {{ $sections[$sec] ?? strtoupper($sec) }}
                                    <span class="badge badge-soft-secondary">{{ $counts[$sec] ?? 0 }}</span>
                                </td>
                            </tr>
                        @endif
                        <tr data-row="{{ $rowId }}">
                            <td>
                                <strong>{{ $s['name'] ?: '—' }}</strong>
                                @if(!empty($s['demo_ref']))
                                    <div class="small text-muted">{{ $s['demo_ref'] }}</div>
                                @endif
                                @if(!empty($filterSection))
                                    <div><span class="badge badge-soft-info">{{ $s['section_label'] ?? '' }}</span></div>
                                @endif
                            </td>
                            <td>
                                @if(!empty($s['phone']))
                                    <a href="tel:{{ $s['phone'] }}">{{ $s['phone'] }}</a>
                                    @if(!empty($s['window_open']))
                                        <div><span class="badge badge-soft-success">24h open</span></div>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="small">{{ $s['email'] ?: '—' }}</td>
                            <td>
                                @if(($s['source_label'] ?? '') === 'Both')
                                    <span class="badge badge-soft-primary">Both</span>
                                @elseif(($s['source_label'] ?? '') === 'Demo')
                                    <span class="badge badge-soft-info">Demo</span>
                                @elseif(($s['source_label'] ?? '') === 'Mentor')
                                    <span class="badge badge-soft-warning">Mentor</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="small">
                                @if(!empty($s['latest_at']))
                                    {{ $s['latest_at']->format('d M Y') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                <textarea class="form-control form-control-sm ss-message"
                                          rows="4"
                                          data-row="{{ $rowId }}"
                                          @if(empty($s['phone'])) disabled @endif>{{ $s['message'] ?? '' }}</textarea>
                            </td>
                            <td>
                                @if(!empty($s['phone']))
                                    <div class="d-flex flex-column" style="gap: 6px; min-width: 140px;">
                                        <button type="button"
                                                class="btn btn-sm btn-success ss-open-wa"
                                                data-row="{{ $rowId }}"
                                                data-phone="{{ $s['phone'] }}">
                                            <i class="tio-whatsapp"></i> Open WhatsApp
                                        </button>
                                        <form method="post" action="{{ route('admin.student-sections.send') }}">
                                            @csrf
                                            <input type="hidden" name="phone" value="{{ $s['phone'] }}">
                                            <input type="hidden" name="email" value="{{ $s['email'] ?? '' }}">
                                            <input type="hidden" name="user_id" value="{{ $s['user_id'] ?? '' }}">
                                            <input type="hidden" name="section" value="{{ $s['section'] ?? 'other' }}">
                                            <input type="hidden" name="send_mode" value="auto">
                                            <input type="hidden" name="save_draft" value="1">
                                            <input type="hidden" name="body" class="ss-body-input" data-row="{{ $rowId }}" value="{{ $s['message'] ?? '' }}">
                                            <button type="submit" class="btn btn-sm btn-primary ss-send-api" data-row="{{ $rowId }}">
                                                Send via API
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-muted small">No phone</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No students found for this filter.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if(method_exists($students, 'links'))
                <div class="card-footer">
                    {{ $students->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection

@push('script_2')
<script>
(function () {
    var linkUrl = @json(route('admin.student-sections.whatsapp-link'));
    var csrf = document.querySelector('meta[name="csrf-token"]')
        ? document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        : '';

    function messageFor(rowId) {
        var ta = document.querySelector('textarea.ss-message[data-row="' + rowId + '"]');
        return ta ? ta.value : '';
    }

    document.querySelectorAll('textarea.ss-message').forEach(function (ta) {
        ta.addEventListener('input', function () {
            var rowId = ta.getAttribute('data-row');
            var hidden = document.querySelector('input.ss-body-input[data-row="' + rowId + '"]');
            if (hidden) {
                hidden.value = ta.value;
            }
        });
    });

    document.querySelectorAll('.ss-open-wa').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var rowId = btn.getAttribute('data-row');
            var phone = btn.getAttribute('data-phone');
            var body = messageFor(rowId);
            btn.disabled = true;
            fetch(linkUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify({ phone: phone, body: body })
            }).then(function (r) { return r.json(); }).then(function (data) {
                btn.disabled = false;
                if (data && data.ok && data.url) {
                    window.open(data.url, '_blank', 'noopener');
                } else {
                    alert((data && data.message) ? data.message : 'Could not build WhatsApp link.');
                }
            }).catch(function () {
                btn.disabled = false;
                alert('Network error building WhatsApp link.');
            });
        });
    });

    document.querySelectorAll('form[action*="student-sections/send"]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var hidden = form.querySelector('input.ss-body-input');
            if (!hidden) return;
            var rowId = hidden.getAttribute('data-row');
            hidden.value = messageFor(rowId);
        });
    });
})();
</script>
@endpush
