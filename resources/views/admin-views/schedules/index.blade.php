@extends('layouts.admin.app')

@section('title', translate('Schedules'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h1 class="page-header-title mb-0">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/order.png') }}" class="w--24" alt="">
                </span>
                <span>{{ translate('Schedules') }}</span>
            </h1>
            <div>
                <span class="badge badge-soft-info" id="auto-refresh-badge">{{ translate('Auto-refreshing every 60s') }}</span>
                <a href="{{ route('admin.schedules.index') }}" class="btn btn-sm btn-outline-secondary ml-1">
                    <i class="tio-refresh"></i> {{ translate('Refresh now') }}
                </a>
            </div>
        </div>

        <ul class="nav nav-tabs mb-3" id="scheduleTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="upcoming-tab" data-toggle="tab" href="#upcoming-pane" role="tab">
                    {{ translate('Upcoming Sessions') }}
                    <span class="badge badge-soft-primary ml-1">{{ $upcoming->count() }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="past-tab" data-toggle="tab" href="#past-pane" role="tab">
                    {{ translate('Past Sessions') }}
                    <span class="badge badge-soft-secondary ml-1">{{ $past->count() }}</span>
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="upcoming-pane" role="tabpanel">
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>{{ translate('When') }}</th>
                                        <th>{{ translate('Mentor') }}</th>
                                        <th>{{ translate('Mentee') }}</th>
                                        <th>{{ translate('Status') }}</th>
                                        <th>{{ translate('Meeting link') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($upcoming as $row)
                                        @php $booking = $row['booking']; $when = $row['when']; @endphp
                                        <tr class="{{ $row['is_live'] ? 'table-success' : '' }}">
                                            <td>
                                                @if($when)
                                                    <strong>{{ $when->copy()->timezone(config('app.timezone'))->format('d M, h:i A') }}</strong>
                                                    <br>
                                                    <small class="text-muted">{{ $when->diffForHumans() }}</small>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td><a href="{{ route('admin.mentor.bookings.show', $booking->id) }}">{{ $row['mentor_name'] }}</a></td>
                                            <td>{{ $row['mentee_name'] }}</td>
                                            <td>
                                                @if($row['is_live'])
                                                    <span class="badge badge-soft-success">{{ translate('Live now') }}</span>
                                                @else
                                                    <span class="badge badge-soft-secondary">{{ translate('Upcoming') }}</span>
                                                @endif
                                                @if($row['can_join'])
                                                    <span class="badge badge-soft-primary">{{ translate('Joinable') }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if(!$row['meeting_url'])
                                                    <span class="text-muted">{{ translate('Not generated yet') }}</span>
                                                @elseif($row['can_join'])
                                                    <a href="{{ $row['meeting_url'] }}" target="_blank" rel="noopener" class="btn btn-sm btn--primary">
                                                        <i class="tio-video-camera"></i> {{ translate('Open link') }}
                                                    </a>
                                                    @if($row['meeting_link_source'] === 'zoho')
                                                        <span class="badge badge-soft-primary ml-1">{{ translate('Zoho') }}</span>
                                                    @elseif($row['meeting_link_source'] === 'manual')
                                                        <span class="badge badge-soft-secondary ml-1">{{ translate('Manual') }}</span>
                                                    @endif
                                                @else
                                                    <span class="text-muted" title="{{ translate('The link only becomes active 15 minutes before the session starts.') }}">
                                                        {{ translate('Link not active yet — opens 15 min before start') }}
                                                    </span>
                                                @endif
                                                @if($row['zoho_last_error'])
                                                    <div class="text-danger" style="font-size: 11px;">{{ $row['zoho_last_error'] }}</div>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted py-4">{{ translate('No upcoming scheduled sessions in the next 14 days.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="past-pane" role="tabpanel">
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>{{ translate('When') }}</th>
                                        <th>{{ translate('Mentor') }}</th>
                                        <th>{{ translate('Mentee') }}</th>
                                        <th>{{ translate('Booking status') }}</th>
                                        <th>{{ translate('Meeting link used') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($past as $row)
                                        @php $booking = $row['booking']; $when = $row['when']; @endphp
                                        <tr>
                                            <td>
                                                @if($when)
                                                    <strong>{{ $when->copy()->timezone(config('app.timezone'))->format('d M, h:i A') }}</strong>
                                                    <br>
                                                    <small class="text-muted">{{ $when->diffForHumans() }}</small>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td><a href="{{ route('admin.mentor.bookings.show', $booking->id) }}">{{ $row['mentor_name'] }}</a></td>
                                            <td>{{ $row['mentee_name'] }}</td>
                                            <td>
                                                @php
                                                    $statusBadge = match($row['status']) {
                                                        'completed' => 'success',
                                                        'no_show' => 'danger',
                                                        default => 'secondary',
                                                    };
                                                @endphp
                                                <span class="badge badge-soft-{{ $statusBadge }}">{{ ucfirst(str_replace('_', ' ', $row['status'])) }}</span>
                                            </td>
                                            <td>
                                                @if($row['meeting_url'])
                                                    <span class="text-muted" style="font-size: 12px;">{{ $row['meeting_url'] }}</span>
                                                    @if($row['meeting_link_source'] === 'zoho')
                                                        <span class="badge badge-soft-primary ml-1">{{ translate('Zoho') }}</span>
                                                    @elseif($row['meeting_link_source'] === 'manual')
                                                        <span class="badge badge-soft-secondary ml-1">{{ translate('Manual') }}</span>
                                                    @endif
                                                @else
                                                    <span class="text-muted">{{ translate('No link was generated') }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted py-4">{{ translate('No past sessions in the last 30 days.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        setTimeout(function () {
            window.location.reload();
        }, 60000);
    </script>
@endpush
