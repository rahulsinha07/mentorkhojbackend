@extends('layouts.admin.app')

@section('title', $seminar ? 'Edit Seminar' : 'Add Seminar')

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-calendar"></i></span>
                <span>{{ $seminar ? 'Edit seminar' : 'Add seminar' }}</span>
            </h1>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ $seminar ? route('admin.seminar.update', $seminar?->id) : route('admin.seminar.store') }}" method="post">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label class="input-label">Title *</label>
                                <input type="text" name="title" class="form-control" value="{{ old('title', $seminar?->title ?? '') }}" required>
                            </div>
                            <div class="form-group">
                                <label class="input-label">Slug</label>
                                <input type="text" name="slug" class="form-control" value="{{ old('slug', $seminar?->slug ?? '') }}" placeholder="auto-generated if empty">
                            </div>
                            <div class="form-group">
                                <label class="input-label">Tagline</label>
                                <input type="text" name="tagline" class="form-control" value="{{ old('tagline', $seminar?->tagline ?? '') }}">
                            </div>
                            <div class="form-group">
                                <label class="input-label">Description</label>
                                <textarea name="blurb" rows="4" class="form-control">{{ old('blurb', $seminar?->blurb ?? '') }}</textarea>
                            </div>
                            <div class="form-group">
                                <label class="input-label">Highlights (one per line)</label>
                                <textarea name="highlights" rows="5" class="form-control" placeholder="Resume & portfolio review">{{ old('highlights', isset($seminar) && $seminar?->highlights ? implode("\n", $seminar?->highlights) : '') }}</textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">Emoji</label>
                                <input type="text" name="emoji" class="form-control" value="{{ old('emoji', $seminar?->emoji ?? '💻') }}">
                            </div>
                            <div class="form-group">
                                <label class="input-label">Date / schedule</label>
                                <input type="text" name="date" class="form-control" value="{{ old('date', $seminar?->date ?? '') }}" placeholder="Every Saturday, 11:00 AM">
                            </div>
                            <div class="form-group">
                                <label class="input-label">Duration</label>
                                <input type="text" name="duration" class="form-control" value="{{ old('duration', $seminar?->duration ?? '') }}" placeholder="90 minutes">
                            </div>
                            <div class="form-group">
                                <label class="input-label">Mode</label>
                                <select name="mode" class="form-control">
                                    @foreach(['Online', 'On-site', 'Hybrid'] as $mode)
                                        <option value="{{ $mode }}" {{ old('mode', $seminar?->mode ?? 'Online') === $mode ? 'selected' : '' }}>{{ $mode }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="input-label">Audience</label>
                                <input type="text" name="audience" class="form-control" value="{{ old('audience', $seminar?->audience ?? '') }}">
                            </div>
                            <div class="form-group">
                                <label class="input-label">Sort order</label>
                                <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $seminar?->sort_order ?? 0) }}" min="0">
                            </div>
                            <div class="form-group">
                                <label class="input-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="active" {{ old('status', $seminar?->status ?? 'active') === 'active' ? 'selected' : '' }}>Active (accepting registrations)</option>
                                    <option value="paused" {{ old('status', $seminar?->status ?? '') === 'paused' ? 'selected' : '' }}>Paused</option>
                                    <option value="draft" {{ old('status', $seminar?->status ?? '') === 'draft' ? 'selected' : '' }}>Draft</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="toggle-switch d-flex align-items-center">
                                    <input type="checkbox" name="is_published" value="1" class="toggle-switch-input" {{ old('is_published', $seminar?->is_published ?? true) ? 'checked' : '' }}>
                                    <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                    <span class="ml-2">Published on MentorKhoj website</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            @include('admin-views.seminar.fee-fields', ['seminar' => $seminar])
                        </div>
                        <div class="col-12">
                            <div class="btn--container justify-content-end">
                                <a href="{{ route('admin.seminar.list') }}" class="btn btn--reset">Cancel</a>
                                <button type="submit" class="btn btn--primary">{{ $seminar ? 'Update' : 'Save' }}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
