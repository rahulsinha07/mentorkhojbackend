<div class="card-header border-0 order-header-shadow">
    <h5 class="card-title d-flex justify-content-between flex-grow-1">
        <span>{{ translate('Top Mentees') }}</span>
        <a href="{{ route('admin.customer.list') }}" class="fz-12px font-medium text-006AE5">{{ translate('view_all') }}</a>
    </h5>
</div>

<div class="card-body">
    <div class="top--selling">
        @forelse($top_mentees as $item)
            @if($item->mentee)
                <a class="grid--card" href="{{ route('admin.customer.view', [$item->mentee_user_id]) }}">
                    <div class="cont pt-2">
                        <h6>{{ trim(($item->mentee->f_name ?? '') . ' ' . ($item->mentee->l_name ?? '')) ?: translate('Not exist') }}</h6>
                        <span>{{ $item->mentee->phone }}</span>
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
