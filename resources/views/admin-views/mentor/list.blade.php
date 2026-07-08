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
                        <div class="card--header justify-content-end max--sm-grow">
                            <form action="{{ url()->current() }}" method="GET" class="mr-sm-auto">
                                <div class="input-group">
                                    <input type="search" name="search" class="form-control"
                                        placeholder="{{ translate('Search_by_ID_or_name') }}"
                                        value="{{ $search }}" required autocomplete="off">
                                    <div class="input-group-append">
                                        <button type="submit" class="input-group-text">{{ translate('search') }}</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="table-responsive datatable-custom">
                        <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                            <thead class="thead-light">
                            <tr>
                                <th>{{ translate('#') }}</th>
                                <th>{{ translate('mentor_name') }}</th>
                                <th>{{ translate('headline') }}</th>
                                <th class="text-center">{{ translate('services') }}</th>
                                <th class="text-center">{{ translate('bookings') }}</th>
                                <th class="text-center">{{ translate('published') }}</th>
                                <th class="text-center">{{ translate('status') }}</th>
                                <th class="text-center">{{ translate('action') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($mentors as $key => $mentor)
                                @php
                                    $images = $mentor->images_array;
                                    $image = count($images) ? $images[0] : 'def.png';
                                    $imageUrl = $image && filter_var($image, FILTER_VALIDATE_URL)
                                        ? $image
                                        : asset('storage/app/public/product/' . $image);
                                @endphp
                                <tr>
                                    <td>{{ $mentors->firstItem() + $key }}</td>
                                    <td>
                                        <div class="product-list-media">
                                            <img src="{{ $imageUrl }}"
                                                 onerror="this.src='{{ asset('public/assets/admin/img/400x400/img2.jpg') }}'"
                                                 alt="">
                                            <h6 class="name line--limit-2">
                                                {{ \Illuminate\Support\Str::limit($mentor->display_name ?? $mentor->username, 28, '...') }}
                                                <small class="d-block text-muted">{{ '@' . $mentor->username }}</small>
                                            </h6>
                                        </div>
                                    </td>
                                    <td>{{ \Illuminate\Support\Str::limit($mentor->headline ?? '-', 45, '...') }}</td>
                                    <td class="text-center">{{ $mentor->services_count }}</td>
                                    <td class="text-center">{{ $mentor->bookings_count }}</td>
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
                                            <a class="action-btn" href="{{ \App\CentralLogics\MentorLogic::profileUrl($mentor) }}" target="_blank">
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
