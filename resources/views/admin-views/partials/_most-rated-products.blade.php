<div class="card-header border-0 order-header-shadow">
    <h5 class="card-title d-flex justify-content-between flex-grow-1">
        <span>{{ translate('Recent Demo Bookings') }}</span>
        <a href="{{ route('admin.demo-bookings.index') }}" class="fz-12px font-medium text-006AE5">{{ translate('view_all') }}</a>
    </h5>
</div>

<div class="card-body">
    <div class="rated--products">
        @forelse($recent_demos as $demo)
            <a href="{{ route('admin.demo-bookings.show', $demo->id) }}">
                <div class="rated-media d-flex align-items-center">
                    <span class="line--limit-1 w-0 flex-grow-1">
                        {{ $demo->name }}
                        @if($demo->vertical)
                            <small class="text-muted">({{ $demo->vertical }})</small>
                        @endif
                    </span>
                </div>
                <div>
                    <span class="badge badge-soft-info">{{ $demo->status }}</span>
                    <small class="text-muted">{{ $demo->created_at?->format('m-d-Y h:i A') }}</small>
                </div>
            </a>
        @empty
            <p class="text-muted mb-0">{{ translate('No demo bookings yet') }}</p>
        @endforelse
    </div>
</div>
