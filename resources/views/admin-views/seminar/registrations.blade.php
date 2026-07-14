@extends('layouts.admin.app')

@section('title', 'Seminar Registrations')

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-group-equal"></i></span>
                <span>Seminar registrations <span class="badge badge-soft-secondary">{{ $registrations->total() }}</span></span>
            </h1>
        </div>

        <div class="card">
            <div class="card-header border-0">
                <form action="{{ url()->current() }}" method="GET" class="row g-2">
                    <div class="col-md-4">
                        <select name="seminar_id" class="form-control" onchange="this.form.submit()">
                            <option value="">All seminars</option>
                            @foreach($seminars as $seminar)
                                <option value="{{ $seminar->id }}" {{ (string)$seminarId === (string)$seminar->id ? 'selected' : '' }}>
                                    {{ $seminar->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-8">
                        <div class="input-group">
                            <input type="search" name="search" class="form-control" placeholder="Search name, email, phone, ID"
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
                        <th>Registration ID</th>
                        <th>Seminar</th>
                        <th>Student</th>
                        <th>College</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($registrations as $key => $registration)
                        <tr>
                            <td>{{ $registrations->firstItem() + $key }}</td>
                            <td><code>{{ $registration->registration_id }}</code></td>
                            <td>{{ $registration->seminar?->title ?? '—' }}</td>
                            <td>
                                <strong>{{ $registration->name }}</strong><br>
                                <small>{{ $registration->email }}</small><br>
                                <small class="text-muted">{{ $registration->phone }}</small>
                            </td>
                            <td>
                                {{ $registration->college }}
                                @if($registration->details)
                                    <small class="d-block text-muted">{{ \Illuminate\Support\Str::limit($registration->details, 80) }}</small>
                                @endif
                            </td>
                            <td><span class="badge badge-soft-info">{{ $registration->status }}</span></td>
                            <td>{{ $registration->created_at?->format('d M Y, h:i A') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-4">No registrations yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{!! $registrations->links() !!}</div>
        </div>
    </div>
@endsection
