@extends('layouts.admin.app')

@section('title', translate('Edit mentor'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">
                        <span class="page-header-icon"><i class="tio-edit"></i></span>
                        <span>{{ translate('Edit mentor') }} — {{ $mentor->display_name }}</span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a class="btn btn-outline-secondary" href="{{ route('admin.mentor.list') }}">
                        <i class="tio-chevron-left"></i> {{ translate('Back to list') }}
                    </a>
                    @php
                        $profileSite = rtrim(config('app.mentorkhoj_site_url', 'https://www.mentorkhoj.com'), '/');
                    @endphp
                    <a class="btn btn--primary" href="{{ $profileSite . '/mentor/' . $mentor->username }}" target="_blank">
                        <i class="tio-visible-outlined"></i> {{ translate('View public profile') }}
                    </a>
                </div>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('admin.mentor.update', $mentor->id) }}" method="post" enctype="multipart/form-data">
            @csrf
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header"><h5 class="card-title mb-0">{{ translate('Profile') }}</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="input-label">{{ translate('Display name') }} *</label>
                                    <input type="text" name="display_name" class="form-control" required
                                           value="{{ old('display_name', $mentor->display_name) }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="input-label">{{ translate('Username') }} *</label>
                                    <input type="text" name="username" class="form-control" required pattern="[a-z0-9-]+"
                                           value="{{ old('username', $mentor->username) }}">
                                    <small class="text-muted">Lowercase letters, numbers, hyphens only</small>
                                </div>
                                <div class="col-12">
                                    <label class="input-label">{{ translate('Headline') }}</label>
                                    <input type="text" name="headline" class="form-control" maxlength="500"
                                           value="{{ old('headline', $mentor->headline) }}">
                                </div>
                                <div class="col-12">
                                    <label class="input-label">{{ translate('Bio / description') }}</label>
                                    <textarea name="bio_html" rows="8" class="form-control">{{ old('bio_html', $mentor->bio_html) }}</textarea>
                                </div>
                                <div class="col-12">
                                    <label class="input-label">{{ translate('Share caption') }}</label>
                                    <textarea name="share_caption" rows="3" class="form-control">{{ old('share_caption', $mentor->share_caption) }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header"><h5 class="card-title mb-0">{{ translate('Session offerings') }}</h5></div>
                        <div class="card-body">
                            @forelse($mentor->services as $service)
                                <div class="border rounded p-3 mb-3">
                                    <input type="hidden" name="services[{{ $service->id }}][id]" value="{{ $service->id }}">
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="input-label">{{ translate('Title') }}</label>
                                            <input type="text" name="services[{{ $service->id }}][title]" class="form-control"
                                                   value="{{ old('services.'.$service->id.'.title', $service->title) }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="input-label">{{ translate('Duration (min)') }}</label>
                                            <input type="number" name="services[{{ $service->id }}][duration_minutes]" class="form-control" min="5" max="480"
                                                   value="{{ old('services.'.$service->id.'.duration_minutes', $service->duration_minutes) }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="input-label">{{ translate('Price') }} (₹)</label>
                                            <input type="number" name="services[{{ $service->id }}][price]" class="form-control" min="0" step="0.01"
                                                   value="{{ old('services.'.$service->id.'.price', $service->price) }}">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="input-label">{{ translate('Compare at price') }}</label>
                                            <input type="number" name="services[{{ $service->id }}][compare_at_price]" class="form-control" min="0" step="0.01"
                                                   value="{{ old('services.'.$service->id.'.compare_at_price', $service->compare_at_price) }}">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="input-label">{{ translate('Meeting type') }}</label>
                                            <input type="text" name="services[{{ $service->id }}][meeting_type]" class="form-control"
                                                   value="{{ old('services.'.$service->id.'.meeting_type', $service->meeting_type ?? 'video') }}">
                                        </div>
                                        <div class="col-md-4 d-flex align-items-end gap-3 pb-2">
                                            <label class="toggle-switch d-flex align-items-center mb-0">
                                                <input type="checkbox" name="services[{{ $service->id }}][is_enabled]" value="1" class="toggle-switch-input"
                                                    {{ old('services.'.$service->id.'.is_enabled', $service->is_enabled) ? 'checked' : '' }}>
                                                <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                                <span class="ml-2">{{ translate('Enabled') }}</span>
                                            </label>
                                            <label class="toggle-switch d-flex align-items-center mb-0">
                                                <input type="checkbox" name="services[{{ $service->id }}][is_popular]" value="1" class="toggle-switch-input"
                                                    {{ old('services.'.$service->id.'.is_popular', $service->is_popular) ? 'checked' : '' }}>
                                                <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                                <span class="ml-2">{{ translate('Popular') }}</span>
                                            </label>
                                        </div>
                                        <div class="col-12">
                                            <label class="input-label">{{ translate('Description') }}</label>
                                            <textarea name="services[{{ $service->id }}][description]" rows="2" class="form-control">{{ old('services.'.$service->id.'.description', $service->description) }}</textarea>
                                        </div>
                                        <div class="col-12">
                                            <label class="custom-control custom-checkbox">
                                                <input type="checkbox" name="delete_service_ids[]" value="{{ $service->id }}" class="custom-control-input">
                                                <span class="custom-control-label text-danger">{{ translate('Delete this service') }}</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted mb-0">{{ translate('No services yet') }}</p>
                            @endforelse

                            <hr>
                            <h6 class="mb-3">{{ translate('Add new service') }}</h6>
                            <div class="border rounded p-3 bg-light">
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="input-label">{{ translate('Title') }}</label>
                                        <input type="text" name="new_services[0][title]" class="form-control" placeholder="1-on-1 Mentorship">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="input-label">{{ translate('Duration (min)') }}</label>
                                        <input type="number" name="new_services[0][duration_minutes]" class="form-control" min="5" value="30">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="input-label">{{ translate('Price') }} (₹)</label>
                                        <input type="number" name="new_services[0][price]" class="form-control" min="0" step="0.01" value="0">
                                    </div>
                                    <div class="col-12">
                                        <label class="input-label">{{ translate('Description') }}</label>
                                        <textarea name="new_services[0][description]" rows="2" class="form-control"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header"><h5 class="card-title mb-0">{{ translate('Social links') }}</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                @foreach(['linkedin', 'instagram', 'facebook', 'youtube', 'whatsapp', 'linktree', 'website'] as $key)
                                    <div class="col-md-6">
                                        <label class="input-label text-capitalize">{{ $key }}</label>
                                        <input type="url" name="social_links[{{ $key }}]" class="form-control"
                                               value="{{ old('social_links.'.$key, $social[$key] ?? '') }}" placeholder="https://">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header"><h5 class="card-title mb-0">{{ translate('Status') }}</h5></div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Status') }}</label>
                                <select name="status" class="form-control">
                                    <option value="active" {{ old('status', $mentor->status) === 'active' ? 'selected' : '' }}>{{ translate('Active') }}</option>
                                    <option value="draft" {{ old('status', $mentor->status) === 'draft' ? 'selected' : '' }}>{{ translate('Draft') }}</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="toggle-switch d-flex align-items-center">
                                    <input type="checkbox" name="is_published" value="1" class="toggle-switch-input"
                                        {{ old('is_published', $mentor->is_published) ? 'checked' : '' }}>
                                    <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                    <span class="ml-2">{{ translate('Published on site') }}</span>
                                </label>
                            </div>
                            <div class="form-group">
                                <label class="input-label">{{ translate('Profile discount') }}</label>
                                <input type="number" name="profile_discount" class="form-control" min="0" step="0.01"
                                       value="{{ old('profile_discount', $mentor->profile_discount ?? 0) }}">
                            </div>
                            <div class="form-group">
                                <label class="input-label">{{ translate('Discount type') }}</label>
                                <select name="discount_type" class="form-control">
                                    <option value="percent" {{ old('discount_type', $mentor->discount_type ?? 'percent') === 'percent' ? 'selected' : '' }}>{{ translate('Percent') }}</option>
                                    <option value="amount" {{ old('discount_type', $mentor->discount_type) === 'amount' ? 'selected' : '' }}>{{ translate('Amount') }}</option>
                                </select>
                            </div>
                            <p class="text-muted small mb-0">
                                ID: {{ $mentor->id }} ·
                                {{ translate('Bookings') }}: {{ $mentor->bookings()->count() }} ·
                                {{ translate('Views') }}: {{ $mentor->view_count ?? 0 }}
                            </p>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header"><h5 class="card-title mb-0">{{ translate('Category') }}</h5></div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="input-label">{{ translate('Category') }} *</label>
                                <select name="category_id" id="category-id" class="form-control" required
                                        onchange="loadSubCategories(this.value)">
                                    <option value="" disabled>{{ translate('Select category') }}</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}"
                                            {{ (string) old('category_id', $categoryId) === (string) $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group mb-0">
                                <label class="input-label">{{ translate('Sub category') }}</label>
                                <select name="sub_category_id" id="sub-categories" class="form-control">
                                    <option value="">{{ translate('None') }}</option>
                                    @foreach($subCategories as $sub)
                                        <option value="{{ $sub->id }}"
                                            {{ (string) old('sub_category_id', $subCategoryId) === (string) $sub->id ? 'selected' : '' }}>
                                            {{ $sub->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header"><h5 class="card-title mb-0">{{ translate('Profile photos') }}</h5></div>
                        <div class="card-body">
                            @php $images = $mentor->images_array; @endphp
                            @if(count($images))
                                <div class="row g-2 mb-3">
                                    @foreach($images as $img)
                                        @php
                                            $fileExists = \App\CentralLogics\MentorImageService::fileExists($img);
                                            $imageUrl = filter_var($img, FILTER_VALIDATE_URL)
                                                ? $img
                                                : \App\CentralLogics\MentorImageService::publicAssetUrl($img);
                                        @endphp
                                        <div class="col-6">
                                            @if($fileExists && $imageUrl)
                                                <img src="{{ $imageUrl }}" class="img-fluid rounded border" alt="">
                                            @else
                                                <div class="rounded border border-danger bg-light p-4 text-center">
                                                    <p class="text-danger small mb-2">{{ translate('Photo file missing on server') }}</p>
                                                    <p class="text-muted small mb-0">{{ $img }}</p>
                                                </div>
                                            @endif
                                            @if($img !== 'default.png')
                                                <label class="custom-control custom-checkbox mt-1">
                                                    <input type="checkbox" name="remove_images[]" value="{{ $img }}" class="custom-control-input">
                                                    <span class="custom-control-label text-danger small">{{ translate('Remove') }}</span>
                                                </label>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            <div class="form-group mb-0">
                                <label class="input-label">{{ translate('Upload new photos') }}</label>
                                <input type="file" name="images[]" class="form-control" accept="image/*" multiple>
                                <small class="text-muted">{{ translate('Max 4 photos, 2MB each') }}</small>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-body">
                            <button type="submit" class="btn btn--primary btn-block">
                                <i class="tio-save"></i> {{ translate('Save changes') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('script_2')
    <script>
        function loadSubCategories(parentId, selectedId) {
            const subSelect = document.getElementById('sub-categories');
            if (!parentId) {
                subSelect.innerHTML = '<option value="">None</option>';
                return;
            }
            const selected = selectedId || '';
            fetch('{{ url('/admin/product/get-categories') }}?parent_id=' + parentId + '&sub_category=' + selected)
                .then(res => res.json())
                .then(data => {
                    let html = '<option value="">None</option>';
                    if (data.options) {
                        html += data.options.replace(/<option value="0"[^>]*>[\s\S]*?<\/option>/i, '');
                    }
                    subSelect.innerHTML = html;
                })
                .catch(() => {});
        }

        document.getElementById('category-id')?.addEventListener('change', function () {
            loadSubCategories(this.value, '');
        });
    </script>
@endpush
