@extends('layouts.admin.app')

@section('title', 'Demo '.$booking->booking_ref)

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h1 class="page-header-title mb-0">
                    <span class="page-header-icon"><i class="tio-user"></i></span>
                    <span>{{ $booking->booking_ref }}</span>
                    @if(!empty($verticalKey) && $verticalKey !== 'other')
                        <span class="badge badge-soft-primary text-uppercase">{{ $verticalKey }}</span>
                    @endif
                </h1>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.demo-bookings.index') }}" class="btn btn-sm btn-outline-secondary">&larr; All demos</a>
                    <a href="{{ route('admin.invoices.create', ['demo_ref' => $booking->booking_ref]) }}" class="btn btn-sm btn--primary">{{ translate('Generate Tax Invoice') }}</a>
                    <a class="btn btn-sm btn-outline-danger form-alert" href="javascript:"
                       data-id="demo-booking-delete-{{ $booking->id }}"
                       data-message="Remove this demo booking? This cannot be undone.">
                        Remove booking
                    </a>
                    <form action="{{ route('admin.demo-bookings.destroy', $booking->id) }}"
                          method="post"
                          id="demo-booking-delete-{{ $booking->id }}">
                        @csrf
                        @method('delete')
                    </form>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="row">
            <div class="col-lg-6 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Lead details</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Name</dt>
                            <dd class="col-sm-8">{{ $booking->name }}</dd>
                            <dt class="col-sm-4">Phone</dt>
                            <dd class="col-sm-8">
                                @if($booking->phone)
                                    <a href="tel:{{ $booking->phone }}">{{ $booking->phone }}</a>
                                @else
                                    —
                                @endif
                                @if($booking->whatsappWebUrl())
                                    @include('admin-views.partials._whatsapp-web-btn', [
                                        'url' => $booking->whatsappWebUrl(),
                                        'title' => 'Student welcome on WhatsApp',
                                        'label' => 'WhatsApp',
                                    ])
                                @endif
                            </dd>
                            <dt class="col-sm-4">Email</dt>
                            <dd class="col-sm-8">
                                @if($booking->email)
                                    <a href="mailto:{{ $booking->email }}">{{ $booking->email }}</a>
                                @else
                                    —
                                @endif
                            </dd>
                            <dt class="col-sm-4">Category</dt>
                            <dd class="col-sm-8"><strong>{{ $booking->category_label ?: $booking->category }}</strong> ({{ $booking->category }})</dd>
                            <dt class="col-sm-4">Stage</dt>
                            <dd class="col-sm-8">{{ $booking->stage }}</dd>
                            <dt class="col-sm-4">Subjects</dt>
                            <dd class="col-sm-8">{{ is_array($booking->subjects) ? implode(', ', $booking->subjects) : '—' }}</dd>
                            <dt class="col-sm-4">Source / UTM</dt>
                            <dd class="col-sm-8">{{ $booking->source }} · {{ $booking->utm_source }} / {{ $booking->utm_medium }} / {{ $booking->utm_campaign }}</dd>
                            <dt class="col-sm-4">Email sent</dt>
                            <dd class="col-sm-8">{{ $booking->email_sent_at ?: '—' }}</dd>
                            <dt class="col-sm-4">Created</dt>
                            <dd class="col-sm-8">{{ $booking->created_at }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Status &amp; last communication</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="{{ route('admin.demo-bookings.update', $booking->id) }}">
                            @csrf
                            @method('PUT')
                            <div class="form-group">
                                <label class="input-label">Status</label>
                                <select name="status" class="form-control">
                                    @foreach($statuses as $s)
                                        <option value="{{ $s }}" {{ $booking->status === $s ? 'selected' : '' }}>{{ $s }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="input-label">Last communication / comments</label>
                                <textarea name="admin_notes" class="form-control" rows="6"
                                          placeholder="e.g. Called on WhatsApp — interested in NEET bio demo next week. Follow up Friday.">{{ old('admin_notes', $booking->admin_notes) }}</textarea>
                                <small class="text-muted">
                                    Save what you last said or heard from this lead (call, WhatsApp, email).
                                    @if($booking->last_communication_at)
                                        · Last saved: {{ $booking->last_communication_at->format('d M Y, h:i A') }}
                                    @endif
                                </small>
                            </div>
                            <button class="btn btn-primary" type="submit">Save</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Assigned mentors</h5>
                    </div>
                    <div class="card-body">
                        @if($booking->assignedMentors->isEmpty())
                            <p class="text-muted mb-3">No mentors assigned yet. Add one or more published mentors below.</p>
                        @else
                            <form method="post" action="{{ route('admin.demo-bookings.paid.update', $booking->id) }}" class="mb-4">
                                @csrf
                                @method('PUT')
                                <div class="table-responsive">
                                    <table class="table table-sm table-borderless table-thead-bordered">
                                        <thead>
                                        <tr>
                                            <th>Mentor</th>
                                            <th>Paid session done</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($booking->assignedMentors as $mentor)
                                            <tr>
                                                <td>
                                                    <strong>{{ $mentor->display_name }}</strong>
                                                    @if($mentor->username)
                                                        <div class="small">
                                                            <a href="https://www.mentorkhoj.com/mentor/{{ $mentor->username }}" target="_blank" rel="noopener">
                                                                {{ '@'.$mentor->username }}
                                                            </a>
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <input type="hidden" name="paid_done[{{ $mentor->id }}]" value="0">
                                                    <label class="mb-0">
                                                        <input type="checkbox" name="paid_done[{{ $mentor->id }}]" value="1"
                                                               {{ !empty($mentor->pivot->paid_session_done) ? 'checked' : '' }}>
                                                        Done
                                                    </label>
                                                </td>
                                                <td class="text-right">
                                                    <button form="remove-mentor-{{ $mentor->id }}" class="btn btn-sm btn-outline-danger" type="submit">
                                                        Remove
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <button class="btn btn-primary btn-sm" type="submit">Save paid session status</button>
                            </form>
                            @foreach($booking->assignedMentors as $mentor)
                                <form id="remove-mentor-{{ $mentor->id }}"
                                      action="{{ route('admin.demo-bookings.mentors.destroy', [$booking->id, $mentor->id]) }}"
                                      method="post" class="d-none">
                                    @csrf
                                    @method('delete')
                                </form>
                            @endforeach
                        @endif

                        <form method="post" action="{{ route('admin.demo-bookings.mentors.store', $booking->id) }}">
                            @csrf
                            <div class="form-group mb-2">
                                <label class="input-label">Add {{ $mentorFilterLabel ?? 'category' }} mentor(s)</label>
                                @if(($publishedMentors ?? collect())->isEmpty())
                                    <p class="text-muted mb-0">No published mentors in this category yet.</p>
                                @else
                                <select name="mentor_ids[]" class="form-control" multiple size="8" required>
                                    @foreach($publishedMentors as $m)
                                        <option value="{{ $m->id }}" {{ in_array($m->id, $assignedIds ?? [], false) ? 'disabled' : '' }}>
                                            {{ $m->display_name }} @if($m->username)({{ $m->username }})@endif
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Only mentors in this demo category are listed. Hold Cmd/Ctrl to select more than one. Each assign sends the student email (Mentorkhoj copied).</small>
                            </div>
                            <button class="btn btn-primary" type="submit">Assign selected mentors</button>
                                @endif
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @include('admin-views.partials._session-chat-thread')
    </div>
@endsection
