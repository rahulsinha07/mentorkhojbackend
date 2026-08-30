@extends('layouts.admin.app')

@section('title', 'Session messages')

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-chat"></i></span>
                <span>
                    Session messages
                    <span class="badge badge-soft-secondary">{{ $messages->total() }}</span>
                </span>
            </h1>
            <p class="text-muted mb-0">Mentor–student chat. First names only — no student phone or email.</p>
        </div>

        <div class="card">
            <div class="card-header border-0">
                <form action="{{ url()->current() }}" method="GET" class="w-100">
                    <div class="input-group">
                        <input type="search" name="search" class="form-control"
                               placeholder="Search by student first name, mentor, or message"
                               value="{{ $search ?? '' }}">
                        <div class="input-group-append">
                            <button type="submit" class="input-group-text">Search</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-borderless table-thead-bordered table-nowrap card-table">
                    <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>When</th>
                        <th>From</th>
                        <th>Student</th>
                        <th>Mentor</th>
                        <th>Message</th>
                        <th>Booking</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($messages as $row)
                        <tr>
                            <td>{{ $row['id'] }}</td>
                            <td>{{ optional($row['created_at'])->format('d M Y H:i') }}</td>
                            <td class="text-capitalize">{{ $row['sender_role'] }}</td>
                            <td>{{ $row['student_first_name'] }}</td>
                            <td>{{ $row['mentor_name'] }}</td>
                            <td style="max-width:360px;white-space:normal;">{{ \Illuminate\Support\Str::limit($row['body'], 240) }}</td>
                            <td>
                                @if(!empty($row['mentor_booking_id']))
                                    <a href="{{ route('admin.mentor.bookings.show', $row['mentor_booking_id']) }}">#{{ $row['mentor_booking_id'] }}</a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No messages yet.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                {{ $messages->links() }}
            </div>
        </div>
    </div>
@endsection
