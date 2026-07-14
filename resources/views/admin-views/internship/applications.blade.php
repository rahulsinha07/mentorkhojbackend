@extends('layouts.admin.app')

@section('title', 'Internship Applications')

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-group-equal"></i></span>
                <span>Internship applications <span class="badge badge-soft-secondary">{{ $applications->total() }}</span></span>
            </h1>
        </div>

        <div class="card">
            <div class="card-header border-0">
                <form action="{{ url()->current() }}" method="GET" class="row g-2">
                    <div class="col-md-4">
                        <select name="internship_id" class="form-control" onchange="this.form.submit()">
                            <option value="">All internships</option>
                            @foreach($internships as $internship)
                                <option value="{{ $internship->id }}" {{ (string)$internshipId === (string)$internship->id ? 'selected' : '' }}>
                                    {{ $internship->role }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-8">
                        <div class="input-group">
                            <input type="search" name="search" class="form-control" placeholder="Search name, email, role, ID"
                                   value="{{ $search }}" autocomplete="off">
                            <div class="input-group-append">
                                <button type="submit" class="input-group-text">{{ translate('search') }}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="table-responsive datatable-custom">
                <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Application ID</th>
                        <th>Role</th>
                        <th>Applicant</th>
                        <th>College / Company</th>
                        <th>Resume</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($applications as $key => $application)
                        <tr>
                            <td>{{ $applications->firstItem() + $key }}</td>
                            <td><code>{{ $application->application_id }}</code></td>
                            <td>
                                {{ $application->role }}
                                @if($application->internship)
                                    <small class="d-block text-muted">{{ $application->internship->team }}</small>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $application->name }}</strong><br>
                                <small>{{ $application->email }}</small><br>
                                <small class="text-muted">{{ $application->phone }}</small>
                            </td>
                            <td>
                                {{ $application->org }}
                                @if($application->message)
                                    <small class="d-block text-muted">{{ \Illuminate\Support\Str::limit($application->message, 80) }}</small>
                                @endif
                            </td>
                            <td>
                                @if($application->resume_url)
                                    <a href="{{ $application->resume_url }}" target="_blank" rel="noopener">View</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td><span class="badge badge-soft-info">{{ $application->status }}</span></td>
                            <td>{{ $application->created_at?->format('d M Y, h:i A') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-4">No applications yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{!! $applications->links() !!}</div>
        </div>
    </div>
@endsection
