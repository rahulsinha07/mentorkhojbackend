@extends('layouts.admin.app')

@section('title', translate('Session analytics'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h1 class="page-header-title mb-0">{{ translate('Session analytics') }}</h1>
            <a href="{{ route('admin.mentor.bookings.list') }}" class="btn btn-outline-primary">{{ translate('All bookings') }}</a>
        </div>

        @php $reminder1h = (int)(\App\CentralLogics\Helpers::get_business_settings('session_reminder_1h_enabled') ?? 0); @endphp
        <form method="POST" action="{{ route('admin.mentor.bookings.reminder-1h-toggle') }}" class="card mb-3">
            @csrf
            <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <strong>{{ translate('1-hour session reminders') }}</strong>
                    <p class="mb-0 text-muted small">{{ translate('Send email reminders one hour before confirmed sessions') }}</p>
                </div>
                <input type="hidden" name="enabled" value="{{ $reminder1h ? 0 : 1 }}">
                <button type="submit" class="btn btn-{{ $reminder1h ? 'success' : 'secondary' }}">
                    {{ $reminder1h ? translate('Enabled') : translate('Disabled') }} — {{ translate('Toggle') }}
                </button>
            </div>
        </form>

        <div class="row g-3 mb-4">
            @foreach([
                ['label' => 'Scheduled', 'value' => $stats['scheduled'], 'class' => 'primary'],
                ['label' => 'Pending schedule', 'value' => $stats['pending_schedule'], 'class' => 'info'],
                ['label' => 'Completed', 'value' => $stats['completed'], 'class' => 'success'],
                ['label' => 'Cancelled', 'value' => $stats['cancelled'], 'class' => 'danger'],
                ['label' => 'No show', 'value' => $stats['no_show'], 'class' => 'warning'],
                ['label' => 'Reminders sent', 'value' => $stats['reminders_sent'], 'class' => 'dark'],
                ['label' => 'Reminders failed', 'value' => $stats['reminders_failed'], 'class' => 'secondary'],
            ] as $card)
                <div class="col-6 col-md-4 col-xl">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <div class="h3 mb-1 text-{{ $card['class'] }}">{{ $card['value'] }}</div>
                            <div class="text-muted small">{{ translate($card['label']) }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-header">
                <form method="GET" class="row g-2">
                    <div class="col-md-2"><input type="date" name="from" class="form-control" value="{{ $from }}"></div>
                    <div class="col-md-2"><input type="date" name="to" class="form-control" value="{{ $to }}"></div>
                    <div class="col-md-2"><input type="number" name="mentor_id" class="form-control" placeholder="Mentor ID" value="{{ $mentorId }}"></div>
                    <div class="col-md-2"><input type="number" name="mentee_id" class="form-control" placeholder="Mentee ID" value="{{ $menteeId }}"></div>
                    <div class="col-md-2">
                        <select name="status" class="custom-select">
                            <option value="">{{ translate('All statuses') }}</option>
                            @foreach(['assigned','requested','confirmed','reschedule_requested','completed','cancelled','no_show'] as $s)
                                <option value="{{ $s }}" @selected($status===$s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2"><button class="btn btn-primary w-100">{{ translate('Filter') }}</button></div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-borderless table-thead-bordered table-nowrap card-table">
                    <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>{{ translate('Mentor') }}</th>
                        <th>{{ translate('Mentee') }}</th>
                        <th>{{ translate('Session') }}</th>
                        <th>{{ translate('Date') }}</th>
                        <th>{{ translate('Status') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($bookings as $booking)
                        <tr>
                            <td>{{ $booking->id }}</td>
                            <td>{{ $booking->mentor?->display_name ?? '—' }}</td>
                            <td>{{ trim(($booking->mentee?->f_name ?? '').' '.($booking->mentee?->l_name ?? '')) ?: '—' }}</td>
                            <td>
                                @if($booking->session_number)
                                    {{ $booking->session_number }}/{{ $booking->total_sessions }}
                                @else
                                    {{ $booking->service?->title ?? '—' }}
                                @endif
                            </td>
                            <td>{{ $booking->preferred_date?->format('Y-m-d') ?? '—' }} {{ $booking->preferred_time ? substr($booking->preferred_time,0,5) : '' }}</td>
                            <td><span class="badge badge-soft-secondary">{{ ucfirst(str_replace('_',' ',$booking->status)) }}</span></td>
                            <td><a href="{{ route('admin.mentor.bookings.show', $booking->id) }}" class="btn btn-sm btn-outline-primary">{{ translate('View') }}</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{!! $bookings->links() !!}</div>
        </div>
    </div>
@endsection
