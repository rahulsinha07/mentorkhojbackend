@extends('layouts.admin.app')

@section('title', 'Demo Bookings')

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-calendar-note"></i></span>
                <span>Demo bookings <span class="badge badge-soft-secondary">{{ $total_all ?? 0 }}</span></span>
            </h1>
            <p class="text-muted mb-0">Free demo form fills by LP: NEET · JEE · Tech · AI/ML.</p>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="row mb-3">
            <div class="col-md-2 col-6 mb-2">
                <a href="{{ route('admin.demo-bookings.index') }}"
                   class="card text-decoration-none h-100 {{ empty($filterVertical) ? 'border-primary' : '' }}">
                    <div class="card-body py-3">
                        <div class="text-muted small">All demos</div>
                        <div class="h4 mb-0">{{ $total_all ?? 0 }}</div>
                    </div>
                </a>
            </div>
            @foreach(($verticals ?? []) as $key => $label)
                <div class="col-md-2 col-6 mb-2">
                    <a href="{{ route('admin.demo-bookings.index', ['vertical' => $key]) }}"
                       class="card text-decoration-none h-100 {{ ($filterVertical ?? '') === $key ? 'border-primary' : '' }}">
                        <div class="card-body py-3">
                            <div class="text-muted small">{{ $label }}</div>
                            <div class="h4 mb-0">{{ $counts[$key] ?? 0 }}</div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-header border-0">
                <form method="get" class="row g-2 align-items-end">
                    @if(!empty($filterVertical))
                        <input type="hidden" name="vertical" value="{{ $filterVertical }}">
                    @endif
                    <div class="col-md-4">
                        <label class="input-label">Search</label>
                        <input type="text" name="q" value="{{ $q }}" placeholder="Name, phone, email, ref" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="input-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="">All statuses</option>
                            @foreach($statuses as $s)
                                <option value="{{ $s }}" {{ ($filterStatus ?? '') === $s ? 'selected' : '' }}>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <button class="btn btn-primary" type="submit">Filter</button>
                        <a class="btn btn-outline-secondary"
                           href="{{ route('admin.demo-bookings.index', array_filter(['vertical' => $filterVertical ?? null, 'status' => $filterStatus ?? null, 'q' => $q ?? null, 'export' => 'csv'])) }}">
                            Export CSV
                        </a>
                    </div>
                </form>
                @if(!empty($filterVertical) && isset($verticals[$filterVertical]))
                    <h5 class="mt-3 mb-0">Section: {{ $verticals[$filterVertical] }}</h5>
                @endif
            </div>

            <div class="table-responsive datatable-custom admin-desktop-booking-table">
                <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                    <tr>
                        <th>Ref</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Category</th>
                        <th>Stage</th>
                        <th>Status</th>
                        <th>Mentors</th>
                        <th>Paid</th>
                        <th>Last communication</th>
                        <th>Created</th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($bookings as $b)
                        <tr>
                            <td><a href="{{ route('admin.demo-bookings.show', $b->id) }}">{{ $b->booking_ref }}</a></td>
                            <td>{{ $b->name }}</td>
                            <td>
                                <span class="d-inline-flex align-items-center">
                                    @if($b->phone)
                                        <a href="tel:{{ $b->phone }}">{{ $b->phone }}</a>
                                    @else
                                        —
                                    @endif
                                    @if($b->whatsappWebUrl())
                                        @include('admin-views.partials._whatsapp-web-btn', ['url' => $b->whatsappWebUrl(), 'title' => 'Student welcome on WhatsApp'])
                                    @endif
                                </span>
                            </td>
                            <td>{{ $b->email ?: '—' }}</td>
                            <td><strong>{{ $b->category_label ?: $b->category }}</strong></td>
                            <td>{{ $b->stage }}</td>
                            <td><span class="badge badge-soft-info">{{ $b->status }}</span></td>
                            <td class="small" style="max-width: 180px; white-space: normal;">
                                @if($b->assignedMentors && $b->assignedMentors->count())
                                    {{ $b->assignedMentors->pluck('display_name')->implode(', ') }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($b->assignedMentors && $b->assignedMentors->contains(function ($m) { return !empty($m->pivot->paid_session_done); }))
                                    <span class="badge badge-soft-success">Paid done</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="small" style="max-width: 220px; white-space: normal;">
                                @if($b->admin_notes)
                                    <div class="text-dark">{{ \Illuminate\Support\Str::limit($b->admin_notes, 80) }}</div>
                                    @if($b->last_communication_at)
                                        <div class="text-muted">{{ $b->last_communication_at->format('d M Y, h:i A') }}</div>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $b->created_at }}</td>
                            <td>
                                <div class="btn--container justify-content-center">
                                    <a class="btn btn-sm btn--primary btn-outline-primary action-btn"
                                       href="{{ route('admin.demo-bookings.show', $b->id) }}"
                                       title="View / add comment">
                                        <i class="tio-visible-outlined"></i>
                                    </a>
                                    <a class="btn btn-sm btn--danger btn-outline-danger action-btn form-alert"
                                       href="javascript:"
                                       data-id="demo-booking-{{ $b->id }}"
                                       data-message="Remove this demo booking? This cannot be undone."
                                       title="Remove">
                                        <i class="tio-delete-outlined"></i>
                                    </a>
                                    <form action="{{ route('admin.demo-bookings.destroy', $b->id) }}"
                                          method="post"
                                          id="demo-booking-{{ $b->id }}">
                                        @csrf
                                        @method('delete')
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="text-muted">No form fills in this section yet.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="admin-mobile-booking-list">
                @forelse($bookings as $b)
                    <div class="admin-mobile-booking-card">
                        <div class="admin-mobile-booking-card__top">
                            <p class="admin-mobile-booking-card__name">{{ $b->name }}</p>
                            <span class="badge badge-soft-info">{{ $b->status }}</span>
                        </div>
                        <div class="admin-mobile-booking-card__meta">
                            <div><a href="{{ route('admin.demo-bookings.show', $b->id) }}">{{ $b->booking_ref }}</a></div>
                            <div><strong>{{ $b->category_label ?: $b->category }}</strong> · {{ $b->stage }}</div>
                            @if($b->phone)
                                <div><a href="tel:{{ $b->phone }}">{{ $b->phone }}</a></div>
                            @endif
                            @if($b->admin_notes)
                                <div class="text-dark mt-1">{{ \Illuminate\Support\Str::limit($b->admin_notes, 90) }}</div>
                            @endif
                        </div>
                        <div class="admin-mobile-booking-card__actions">
                            @if($b->whatsappWebUrl())
                                @include('admin-views.partials._whatsapp-web-btn', [
                                    'url' => $b->whatsappWebUrl(),
                                    'title' => 'Student welcome on WhatsApp',
                                    'label' => 'WhatsApp',
                                ])
                            @endif
                            <a class="btn btn-outline-primary btn-sm"
                               href="{{ route('admin.demo-bookings.show', $b->id) }}">
                                View
                            </a>
                            <a class="btn btn-outline-danger btn-sm form-alert"
                               href="javascript:"
                               data-id="demo-booking-m-{{ $b->id }}"
                               data-message="Remove this demo booking? This cannot be undone."
                               title="Remove">
                                Delete
                            </a>
                            <form action="{{ route('admin.demo-bookings.destroy', $b->id) }}"
                                  method="post"
                                  id="demo-booking-m-{{ $b->id }}">
                                @csrf
                                @method('delete')
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">No form fills in this section yet.</p>
                @endforelse
            </div>

            @if($bookings->hasPages())
                <div class="card-footer">
                    {{ $bookings->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
