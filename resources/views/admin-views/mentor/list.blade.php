@extends('layouts.admin.app')

@section('title', translate('Mentor List'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/products.png') }}" class="w--24" alt="">
                </span>
                <span>
                    {{ translate('mentor list') }}
                    <span class="badge badge-soft-secondary">{{ $mentors->total() }}</span>
                </span>
            </h1>
        </div>

        <div class="row gx-2 gx-lg-3">
            <div class="col-sm-12 col-lg-12 mb-3 mb-lg-2">
                <div class="card">
                    <div class="card-header border-0">
                        <ul class="nav nav-tabs border-0 flex-wrap gap-1 mb-3">
                            <li class="nav-item">
                                <a class="nav-link {{ empty($activeCategoryId) ? 'active' : '' }}"
                                   href="{{ route('admin.mentor.list', array_filter(['search' => $search])) }}">
                                    {{ translate('All') }}
                                </a>
                            </li>
                            @foreach($featuredCategories as $cat)
                                <li class="nav-item">
                                    <a class="nav-link {{ (int) $activeCategoryId === (int) $cat->id ? 'active' : '' }}"
                                       href="{{ route('admin.mentor.list', array_filter(['category' => $cat->id, 'search' => $search])) }}">
                                        {{ $cat->name }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>

                        <div class="card--header justify-content-between max--sm-grow flex-wrap gap-2">
                            <form action="{{ url()->current() }}" method="GET" class="mr-sm-auto">
                                @if(!empty($activeCategoryId))
                                    <input type="hidden" name="category" value="{{ $activeCategoryId }}">
                                @endif
                                <div class="input-group">
                                    <input type="search" name="search" class="form-control"
                                        placeholder="{{ translate('Search_by_ID_or_name') }}"
                                        value="{{ $search }}" autocomplete="off">
                                    <div class="input-group-append">
                                        <button type="submit" class="input-group-text">{{ translate('search') }}</button>
                                    </div>
                                </div>
                            </form>

                            @if(!empty($activeCategoryId))
                                <form action="{{ route('admin.mentor.refresh-category-defaults') }}" method="POST" class="mb-0">
                                    @csrf
                                    <input type="hidden" name="category" value="{{ $activeCategoryId }}">
                                    @if($search)
                                        <input type="hidden" name="search" value="{{ $search }}">
                                    @endif
                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                        {{ translate('Refresh default ranks') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                        @if(!empty($activeCategoryId))
                            <p class="text-muted small mb-0 mt-2">
                                {{ translate('Default rank') }} = {{ translate('most bookings first') }}.
                                {{ translate('Rank') }} = {{ translate('browse order (override anytime)') }}.
                            </p>
                        @endif
                    </div>

                    <div class="table-responsive datatable-custom">
                        <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                            <thead class="thead-light">
                            <tr>
                                <th>{{ translate('#') }}</th>
                                <th>{{ translate('mentor_name') }}</th>
                                <th>{{ translate('phone') }}</th>
                                <th>{{ translate('headline') }}</th>
                                <th class="text-center">{{ translate('services') }}</th>
                                <th class="text-center">{{ translate('bookings') }}</th>
                                @if(!empty($activeCategoryId))
                                    <th class="text-center">{{ translate('Default rank') }}</th>
                                    <th class="text-center">{{ translate('Rank') }}</th>
                                @endif
                                <th class="text-center">{{ translate('Real reviews only') }}</th>
                                <th class="text-center">{{ translate('published') }}</th>
                                <th class="text-center">{{ translate('status') }}</th>
                                <th class="text-center">{{ translate('action') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($mentors as $key => $mentor)
                                @php
                                    $thumb = \App\CentralLogics\MentorImageService::adminListThumbnail($mentor);
                                    $defaultRank = !empty($activeCategoryId)
                                        ? \App\CentralLogics\MentorCategoryRankLogic::defaultRankForCategory($mentor, (int) $activeCategoryId)
                                        : null;
                                    $finalRank = !empty($activeCategoryId)
                                        ? \App\CentralLogics\MentorCategoryRankLogic::rankForCategory($mentor, (int) $activeCategoryId)
                                        : null;
                                @endphp
                                <tr>
                                    <td>{{ $mentors->firstItem() + $key }}</td>
                                    <td>
                                        <div class="product-list-media">
                                            <img src="{{ $thumb['url'] }}"
                                                 class="{{ $thumb['missing'] ? 'border border-danger' : '' }}"
                                                 alt="">
                                            <h6 class="name line--limit-2">
                                                {{ \Illuminate\Support\Str::limit($mentor->display_name ?? $mentor->username, 28, '...') }}
                                                <small class="d-block text-muted">{{ '@' . $mentor->username }}</small>
                                            </h6>
                                        </div>
                                    </td>
                                    <td>
                                        @php
                                            $phone = trim((string) ($mentor->user?->phone ?? ''));
                                        @endphp
                                        @if($phone === '' || $phone === '0')
                                            <span class="text-muted">—</span>
                                        @elseif($mentor->user)
                                            <a href="tel:{{ $phone }}">{{ $phone }}</a>
                                            @include('admin-views.partials._whatsapp-web-btn', [
                                                'url' => $mentor->user->whatsappWebUrl('mentor'),
                                                'title' => 'Contact mentor on WhatsApp',
                                            ])
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ \Illuminate\Support\Str::limit($mentor->headline ?? '-', 45, '...') }}</td>
                                    <td class="text-center">{{ $mentor->services_count }}</td>
                                    <td class="text-center">{{ $mentor->bookings_count }}</td>
                                    @if(!empty($activeCategoryId))
                                        <td class="text-center">
                                            <span class="badge badge-soft-info">{{ $defaultRank ?? '—' }}</span>
                                        </td>
                                        <td class="text-center" style="min-width: 7rem;">
                                            <form action="{{ route('admin.mentor.category-rank', $mentor->id) }}" method="POST" class="d-inline-flex align-items-center gap-1">
                                                @csrf
                                                <input type="hidden" name="category_id" value="{{ $activeCategoryId }}">
                                                @if($search)
                                                    <input type="hidden" name="search" value="{{ $search }}">
                                                @endif
                                                @if(request('page'))
                                                    <input type="hidden" name="page" value="{{ request('page') }}">
                                                @endif
                                                <input type="number" name="rank" min="1" class="form-control form-control-sm"
                                                       style="width: 4.5rem;"
                                                       value="{{ $finalRank }}"
                                                       title="{{ translate('Final browse rank') }}">
                                                <button type="submit" class="btn btn-sm btn-primary px-2" title="{{ translate('Save') }}">
                                                    <i class="tio-done"></i>
                                                </button>
                                            </form>
                                        </td>
                                    @endif
                                    <td class="text-center">
                                        @php $hidesDefaults = (bool) ($mentor->settings->hide_default_reviews ?? false); @endphp
                                        <label class="toggle-switch my-0" title="{{ translate('When on, this mentor never shows placeholder reviews or a default rating — only real ones, even if that means none yet.') }}">
                                            <input type="checkbox"
                                                onclick="status_change_alert('{{ route('admin.mentor.toggle-default-reviews', [$mentor->id, $hidesDefaults ? 0 : 1]) }}', '{{ $hidesDefaults ? translate('you want to re-enable default placeholder reviews for this mentor') : translate('you want to hide default placeholder reviews for this mentor — only real reviews will show') }}', event)"
                                                class="toggle-switch-input"
                                                {{ $hidesDefaults ? 'checked' : '' }}>
                                            <span class="toggle-switch-label mx-auto text">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </td>
                                    <td class="text-center">
                                        <label class="toggle-switch my-0">
                                            <input type="checkbox"
                                                onclick="status_change_alert('{{ route('admin.mentor.publish', [$mentor->id, $mentor->is_published ? 0 : 1]) }}', '{{ $mentor->is_published ? translate('you want to unpublish this mentor') : translate('you want to publish this mentor') }}', event)"
                                                class="toggle-switch-input"
                                                {{ $mentor->is_published ? 'checked' : '' }}>
                                            <span class="toggle-switch-label mx-auto text">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </td>
                                    <td class="text-center">
                                        <label class="toggle-switch my-0">
                                            <input type="checkbox"
                                                onclick="status_change_alert('{{ route('admin.mentor.status', [$mentor->id, $mentor->status === 'active' ? 0 : 1]) }}', '{{ $mentor->status === 'active' ? translate('you want to disable this mentor') : translate('you want to active this mentor') }}', event)"
                                                class="toggle-switch-input"
                                                {{ $mentor->status === 'active' ? 'checked' : '' }}>
                                            <span class="toggle-switch-label mx-auto text">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </td>
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="action-btn btn--primary btn-outline-primary" href="{{ route('admin.mentor.edit', $mentor->id) }}" title="{{ translate('Edit') }}">
                                                <i class="tio-edit"></i>
                                            </a>
                                            <a class="action-btn" href="{{ rtrim(config('app.mentorkhoj_site_url', 'https://www.mentorkhoj.com'), '/') . '/mentor/' . $mentor->username }}" target="_blank" title="{{ translate('View') }}">
                                                <i class="tio-visible-outlined"></i>
                                            </a>
                                            <a class="action-btn btn--danger btn-outline-danger" href="javascript:"
                                               onclick="form_alert('mentor-{{ $mentor->id }}', '{{ translate('Want to delete this') }}')">
                                                <i class="tio-delete-outlined"></i>
                                            </a>
                                        </div>
                                        <form action="{{ route('admin.mentor.delete', [$mentor->id]) }}" method="post" id="mentor-{{ $mentor->id }}">
                                            @csrf
                                            @method('delete')
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>

                        <div class="page-area">
                            <table>
                                <tfoot class="border-top">
                                {!! $mentors->links() !!}
                                </tfoot>
                            </table>
                        </div>

                        @if(count($mentors) == 0)
                            <div class="text-center p-4">
                                <img class="w-120px mb-3" src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
                                <p class="mb-0">{{ translate('No_data_to_show') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        function status_change_alert(url, message, e) {
            e.preventDefault();
            Swal.fire({
                title: '{{ translate("Are you sure?") }}',
                text: message,
                type: 'warning',
                showCancelButton: true,
                cancelButtonColor: 'default',
                confirmButtonColor: '#107980',
                cancelButtonText: '{{ translate("No") }}',
                confirmButtonText: '{{ translate("Yes") }}',
                reverseButtons: true
            }).then((result) => {
                if (result.value) {
                    location.href = url;
                }
            })
        }
    </script>
@endpush
