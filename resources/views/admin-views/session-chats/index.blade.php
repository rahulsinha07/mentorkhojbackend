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
            <p class="text-muted mb-0">Mentor–student chat and ADMIN support messages. First names only in student app — no student phone or email shown there.</p>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 pl-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Send as ADMIN</h5>
                <small class="text-muted">Unlimited support thread · WhatsApp +91 73669 39888 · student sees “ADMIN” in Messages</small>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('admin.mentor.session-messages') }}" class="mb-3">
                    <label class="input-label">Find student (name, email, or phone)</label>
                    <div class="input-group">
                        <input type="search" name="student_q" class="form-control"
                               placeholder="Search students…"
                               value="{{ $studentQuery ?? '' }}">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-secondary">Search students</button>
                        </div>
                    </div>
                </form>

                @if(($students ?? collect())->isNotEmpty())
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-thead-bordered">
                            <thead class="thead-light">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($students as $student)
                                <tr>
                                    <td>{{ $student->id }}</td>
                                    <td>{{ trim(($student->f_name ?? '').' '.($student->l_name ?? '')) }}</td>
                                    <td>{{ $student->email }}</td>
                                    <td>{{ $student->phone }}</td>
                                    <td>
                                        <a class="btn btn-xs btn-primary"
                                           href="{{ route('admin.mentor.session-messages', ['mentee_user_id' => $student->id, 'student_q' => $studentQuery]) }}">
                                            Select
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.mentor.session-messages.store') }}">
                    @csrf
                    <div class="form-group">
                        <label class="input-label" for="mentee_user_id">Student user ID</label>
                        <input type="number" min="1" required name="mentee_user_id" id="mentee_user_id"
                               class="form-control"
                               value="{{ old('mentee_user_id', optional($prefillStudent)->id) }}"
                               placeholder="User id">
                        @if($prefillStudent)
                            <small class="text-muted">
                                Selected: {{ trim(($prefillStudent->f_name ?? '').' '.($prefillStudent->l_name ?? '')) }}
                                · {{ $prefillStudent->email }}
                            </small>
                        @endif
                    </div>
                    <div class="form-group">
                        <label class="input-label" for="admin_body">Message</label>
                        <textarea name="body" id="admin_body" rows="4" required maxlength="2000"
                                  class="form-control"
                                  placeholder="Write the ADMIN message students will see in /account/messages…">{{ old('body') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="tio-send"></i> Send as ADMIN
                    </button>
                </form>
            </div>
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
                        <th></th>
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
                            <td>
                                @if(!empty($row['mentee_user_id']))
                                    <a class="btn btn-xs btn-outline-primary"
                                       href="{{ route('admin.mentor.session-messages', ['mentee_user_id' => $row['mentee_user_id']]) }}">
                                        Reply ADMIN
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No messages yet.</td>
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
