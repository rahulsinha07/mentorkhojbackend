{{-- Admin: seminar registrations / payments --}}
@extends('layouts.admin.app')

@section('title', 'Seminar bookings — ' . $seminar->title)

@section('content')
  <div class="content container-fluid">
    <div class="page-header">
      <h1 class="page-header-title">{{ $seminar->title }} — bookings</h1>
      <p class="text-muted mb-0">
        Fee: {{ (float) ($seminar->fee_amount ?? 0) <= 0 ? 'FREE' : '₹' . number_format($seminar->fee_amount, 2) }}
      </p>
    </div>

    <div class="card">
      <div class="card-header d-flex gap-2">
        <a href="?payment_status=" class="btn btn-sm {{ empty($filter) ? 'btn-primary' : 'btn-outline-primary' }}">All</a>
        <a href="?payment_status=paid" class="btn btn-sm {{ $filter === 'paid' ? 'btn-primary' : 'btn-outline-primary' }}">Paid</a>
        <a href="?payment_status=pending" class="btn btn-sm {{ $filter === 'pending' ? 'btn-primary' : 'btn-outline-primary' }}">Pending</a>
        <a href="?payment_status=failed" class="btn btn-sm {{ $filter === 'failed' ? 'btn-primary' : 'btn-outline-primary' }}">Failed</a>
        <a href="?payment_status=free" class="btn btn-sm {{ $filter === 'free' ? 'btn-primary' : 'btn-outline-primary' }}">Free</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Ref</th>
              <th>Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Payment</th>
              <th>Booked</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($bookings as $booking)
              <tr>
                <td>{{ $booking->booking_ref }}</td>
                <td>{{ $booking->name }}</td>
                <td>{{ $booking->email }}</td>
                <td>{{ $booking->phone }}</td>
                <td>
                  @if ((float) $booking->amount <= 0)
                    FREE
                  @else
                    ₹{{ number_format($booking->amount, 2) }}
                  @endif
                </td>
                <td>{{ $booking->status }}</td>
                <td>{{ $booking->payment_status }}</td>
                <td>{{ $booking->created_at?->format('d M Y H:i') }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="8" class="text-center text-muted py-4">No bookings yet.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if ($bookings->hasPages())
        <div class="card-footer">{{ $bookings->links() }}</div>
      @endif
    </div>
  </div>
@endsection
