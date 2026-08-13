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
                                <a href="tel:{{ $booking->phone }}">{{ $booking->phone }}</a>
                                ·
                                <a href="https://wa.me/{{ preg_replace('/\D/', '', $booking->phone) }}" target="_blank" rel="noopener">WhatsApp</a>
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
    </div>
@endsection
