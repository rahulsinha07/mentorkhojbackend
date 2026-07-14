@extends('layouts.admin.app')

@section('title', $internship ? 'Edit Internship' : 'Add Internship')

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-briefcase"></i></span>
                <span>{{ $internship ? 'Edit internship' : 'Add internship' }}</span>
            </h1>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ $internship ? route('admin.internship.update', $internship?->id) : route('admin.internship.store') }}" method="post">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label class="input-label">Role title *</label>
                                <input type="text" name="role" class="form-control" value="{{ old('role', $internship?->role ?? '') }}" required>
                            </div>
                            <div class="form-group">
                                <label class="input-label">Slug</label>
                                <input type="text" name="slug" class="form-control" value="{{ old('slug', $internship?->slug ?? '') }}" placeholder="auto-generated if empty">
                            </div>
                            <div class="form-group">
                                <label class="input-label">Description</label>
                                <textarea name="blurb" rows="4" class="form-control">{{ old('blurb', $internship?->blurb ?? '') }}</textarea>
                            </div>
                            <div class="form-group">
                                <label class="input-label">Skills (comma-separated)</label>
                                <input type="text" name="skills" class="form-control"
                                       value="{{ old('skills', isset($internship) && $internship?->skills ? implode(', ', $internship?->skills) : '') }}"
                                       placeholder="React, Next.js, TypeScript">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="input-label">Team</label>
                                <input type="text" name="team" class="form-control" value="{{ old('team', $internship?->team ?? '') }}">
                            </div>
                            <div class="form-group">
                                <label class="input-label">Location</label>
                                <input type="text" name="location" class="form-control" value="{{ old('location', $internship?->location ?? '') }}">
                            </div>
                            <div class="form-group">
                                <label class="input-label">Type</label>
                                <select name="type" class="form-control">
                                    @foreach(['Remote', 'Hybrid', 'On-site'] as $type)
                                        <option value="{{ $type }}" {{ old('type', $internship?->type ?? 'Remote') === $type ? 'selected' : '' }}>{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="input-label">Duration</label>
                                <input type="text" name="duration" class="form-control" value="{{ old('duration', $internship?->duration ?? '') }}">
                            </div>
                            <div class="form-group">
                                <label class="input-label">Stipend</label>
                                <input type="text" name="stipend" class="form-control" value="{{ old('stipend', $internship?->stipend ?? '') }}">
                            </div>
                            <div class="form-group">
                                <label class="input-label">Sort order</label>
                                <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $internship?->sort_order ?? 0) }}" min="0">
                            </div>
                            <div class="form-group">
                                <label class="input-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="active" {{ old('status', $internship?->status ?? 'active') === 'active' ? 'selected' : '' }}>Active (accepting applications)</option>
                                    <option value="paused" {{ old('status', $internship?->status ?? '') === 'paused' ? 'selected' : '' }}>Paused</option>
                                    <option value="draft" {{ old('status', $internship?->status ?? '') === 'draft' ? 'selected' : '' }}>Draft</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="toggle-switch d-flex align-items-center">
                                    <input type="checkbox" name="is_published" value="1" class="toggle-switch-input" {{ old('is_published', $internship?->is_published ?? true) ? 'checked' : '' }}>
                                    <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                    <span class="ml-2">Published on MentorKhoj website</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="btn--container justify-content-end">
                                <a href="{{ route('admin.internship.list') }}" class="btn btn--reset">Cancel</a>
                                <button type="submit" class="btn btn--primary">{{ $internship ? 'Update' : 'Save' }}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
