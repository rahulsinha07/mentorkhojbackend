@extends('layouts.admin.app')

@section('title', 'Internship List')

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">
                        <span class="page-header-icon"><i class="tio-briefcase"></i></span>
                        <span>Internships <span class="badge badge-soft-secondary">{{ $internships->total() }}</span></span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a class="btn btn--primary" href="{{ route('admin.internship.add') }}">
                        <i class="tio-add"></i> Add internship
                    </a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-0">
                <form action="{{ url()->current() }}" method="GET" class="w-100">
                    <div class="input-group">
                        <input type="search" name="search" class="form-control" placeholder="Search by role or team"
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
                        <th>Role</th>
                        <th>Details</th>
                        <th class="text-center">Applications</th>
                        <th class="text-center">Published</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($internships as $key => $internship)
                        <tr>
                            <td>{{ $internships->firstItem() + $key }}</td>
                            <td>
                                <h6 class="mb-0">{{ $internship->role }}</h6>
                                <small class="text-muted">{{ $internship->team }}</small>
                                <small class="d-block text-muted">/{{ $internship->slug }}</small>
                            </td>
                            <td>
                                <small class="d-block">{{ $internship->location }} · {{ $internship->type }}</small>
                                <small class="text-muted">{{ $internship->duration }} · {{ $internship->stipend }}</small>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.internship.applications', ['internship_id' => $internship->id]) }}">
                                    {{ $internship->applications_count }}
                                </a>
                            </td>
                            <td class="text-center">
                                <label class="toggle-switch my-0">
                                    <input type="checkbox"
                                           onclick="status_change_alert('{{ route('admin.internship.publish', [$internship->id, $internship->is_published ? 0 : 1]) }}', 'Change publish status?', event)"
                                           class="toggle-switch-input" {{ $internship->is_published ? 'checked' : '' }}>
                                    <span class="toggle-switch-label mx-auto text"><span class="toggle-switch-indicator"></span></span>
                                </label>
                            </td>
                            <td class="text-center">
                                <label class="toggle-switch my-0">
                                    <input type="checkbox"
                                           onclick="status_change_alert('{{ route('admin.internship.status', [$internship->id, $internship->status === 'active' ? 'paused' : 'active']) }}', 'Change internship status?', event)"
                                           class="toggle-switch-input" {{ $internship->status === 'active' ? 'checked' : '' }}>
                                    <span class="toggle-switch-label mx-auto text"><span class="toggle-switch-indicator"></span></span>
                                </label>
                                <small class="d-block text-muted">{{ $internship->status === 'active' ? 'Active' : 'Paused' }}</small>
                            </td>
                            <td>
                                <div class="btn--container justify-content-center">
                                    <a class="btn btn-sm btn--primary btn-outline-primary action-btn" href="{{ route('admin.internship.edit', $internship->id) }}">
                                        <i class="tio-edit"></i>
                                    </a>
                                    <a class="btn btn-sm btn--warning btn-outline-warning action-btn" href="{{ route('admin.internship.applications', ['internship_id' => $internship->id]) }}">
                                        <i class="tio-group-equal"></i>
                                    </a>
                                    <a class="btn btn-sm btn--danger btn-outline-danger action-btn form-alert" href="javascript:"
                                       data-id="internship-{{ $internship->id }}"
                                       data-message="Delete this internship?">
                                        <i class="tio-delete-outlined"></i>
                                    </a>
                                    <form action="{{ route('admin.internship.delete', $internship->id) }}" method="post" id="internship-{{ $internship->id }}">
                                        @csrf @method('delete')
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{!! $internships->links() !!}</div>
        </div>
    </div>
@endsection
