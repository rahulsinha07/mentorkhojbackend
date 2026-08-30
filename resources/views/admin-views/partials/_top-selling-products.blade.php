<div class="card-header border-0 order-header-shadow">
    <h5 class="card-title d-flex justify-content-between flex-grow-1">
        <span>{{ translate('Top Mentors') }}</span>
        <a href="{{ route('admin.mentor.list') }}" class="fz-12px font-medium text-006AE5">{{ translate('view_all') }}</a>
    </h5>
</div>

<div class="card-body">
    <div class="top--selling">
        @forelse($top_mentors as $item)
            @if($item->mentor)
                <a class="grid--card" href="{{ route('admin.mentor.edit', $item->mentor_id) }}">
                    <div class="cont pt-2">
                        <h6>{{ $item->mentor->display_name }}</h6>
                        <span>{{ $item->mentor->username ?? '' }}</span>
                    </div>
                    <div class="ml-auto">
                        <span class="badge badge-soft">{{ translate('Bookings') }} : {{ $item->count }}</span>
                    </div>
                </a>
            @endif
        @empty
            <p class="text-muted mb-0">{{ translate('No bookings yet') }}</p>
        @endforelse
    </div>
</div>
