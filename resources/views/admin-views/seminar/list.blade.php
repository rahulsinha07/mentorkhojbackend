@extends('layouts.admin.app')

@section('title', 'Seminar List')

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">
                        <span class="page-header-icon"><i class="tio-calendar"></i></span>
                        <span>Seminars <span class="badge badge-soft-secondary">{{ $seminars->total() }}</span></span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a class="btn btn--primary" href="{{ route('admin.seminar.add') }}">
                        <i class="tio-add"></i> Add seminar
                    </a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-0">
                <form action="{{ url()->current() }}" method="GET" class="w-100">
                    <div class="input-group">
                        <input type="search" name="search" class="form-control" placeholder="Search by title or slug"
                               value="{{ $search }}" autocomplete="off">
                        <div class="input-group-append">
                            <button type="submit" class="input-group-text">{{ translate('search') }}</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="table-responsive datatable-custom">
                <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Seminar</th>
                        <th>Schedule</th>
                        <th class="text-center">Registrations</th>
                        <th class="text-center">Published</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($seminars as $key => $seminar)
                        <tr>
                            <td>{{ $seminars->firstItem() + $key }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span style="font-size: 1.5rem;">{{ $seminar->emoji ?: '📅' }}</span>
                                    <div>
                                        <h6 class="mb-0">{{ $seminar->title }}</h6>
                                        <small class="text-muted">{{ $seminar->tagline }}</small>
                                        <small class="d-block text-muted">/{{ $seminar->slug }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <small class="d-block">{{ $seminar->date ?: '—' }}</small>
                                <small class="text-muted">{{ $seminar->duration }} · {{ $seminar->mode }}</small>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.seminar.registrations', ['seminar_id' => $seminar->id]) }}">
                                    {{ $seminar->registrations_count }}
                                </a>
                            </td>
                            <td class="text-center">
                                <label class="toggle-switch my-0">
                                    <input type="checkbox"
                                           onclick="status_change_alert('{{ route('admin.seminar.publish', [$seminar->id, $seminar->is_published ? 0 : 1]) }}', 'Change publish status?', event)"
                                           class="toggle-switch-input" {{ $seminar->is_published ? 'checked' : '' }}>
                                    <span class="toggle-switch-label mx-auto text"><span class="toggle-switch-indicator"></span></span>
                                </label>
                            </td>
                            <td class="text-center">
                                <label class="toggle-switch my-0">
                                    <input type="checkbox"
                                           onclick="status_change_alert('{{ route('admin.seminar.status', [$seminar->id, $seminar->status === 'active' ? 'paused' : 'active']) }}', 'Change seminar status?', event)"
                                           class="toggle-switch-input" {{ $seminar->status === 'active' ? 'checked' : '' }}>
                                    <span class="toggle-switch-label mx-auto text"><span class="toggle-switch-indicator"></span></span>
                                </label>
                                <small class="d-block text-muted">{{ $seminar->status === 'active' ? 'Active' : 'Paused' }}</small>
                            </td>
                            <td>
                                <div class="btn--container justify-content-center">
                                    <a class="btn btn-sm btn--primary btn-outline-primary action-btn" href="{{ route('admin.seminar.edit', $seminar->id) }}">
                                        <i class="tio-edit"></i>
                                    </a>
                                    <a class="btn btn-sm btn--warning btn-outline-warning action-btn" href="{{ route('admin.seminar.registrations', ['seminar_id' => $seminar->id]) }}">
                                        <i class="tio-group-equal"></i>
                                    </a>
                                    <a class="btn btn-sm btn--danger btn-outline-danger action-btn form-alert" href="javascript:"
                                       data-id="seminar-{{ $seminar->id }}"
                                       data-message="Delete this seminar and all registrations?">
                                        <i class="tio-delete-outlined"></i>
                                    </a>
                                    <form action="{{ route('admin.seminar.delete', $seminar->id) }}" method="post" id="seminar-{{ $seminar->id }}">
                                        @csrf @method('delete')
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{!! $seminars->links() !!}</div>
        </div>
    </div>
@endsection
