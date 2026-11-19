@extends('layouts.admin', ['title' => $category->exists ? 'Edit category' : 'New category'])

@section('admin')
    <div class="admin-top">
        <h1>{{ $category->exists ? 'Edit: '.$category->name : 'New category' }}</h1>
        <span class="spacer"></span>
        @if ($category->exists && $category->is_active)
            <a class="btn btn-ghost btn-sm" href="{{ $category->url() }}" target="_blank" rel="noopener">View on store ↗</a>
        @endif
    </div>
    <form method="POST" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="card card-pad" novalidate>
        @csrf
        @if ($category->exists) @method('PUT') @endif
        <div class="form-grid">
            <div class="field"><label for="name">Name (English)</label><input class="input" id="name" name="name" value="{{ old('name', $category->name) }}" required></div>
            <div class="field"><label for="name_bn">Name (Bangla, optional)</label><input class="input" id="name_bn" name="name_bn" lang="bn" value="{{ old('name_bn', $category->name_bn) }}"></div>
            <div class="field"><label for="slug">URL slug</label><input class="input" id="slug" name="slug" value="{{ old('slug', $category->slug) }}" placeholder="auto from name"><span class="hint">/category/<em>slug</em></span></div>
            <div class="field"><label for="parent_id">Parent</label>
                <select class="input" id="parent_id" name="parent_id">
                    <option value="">— Top level —</option>
                    @foreach ($parents as $parent)
                        <option value="{{ $parent->id }}" @selected((int) old('parent_id', $category->parent_id) === $parent->id)>{{ $parent->name }}</option>
                    @endforeach
                </select></div>
            <div class="field"><label for="icon">Icon</label>
                <select class="input" id="icon" name="icon">
                    @foreach (\App\Models\Category::ICONS as $icon)
                        <option value="{{ $icon }}" @selected(old('icon', $category->icon) === $icon)>{{ $icon }}</option>
                    @endforeach
                </select></div>
            <div class="field"><label for="sort_order">Display order</label><input class="input" id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $category->sort_order ?? 0) }}"></div>
            <div class="field full"><label for="summary">Short summary (shown on tiles)</label><input class="input" id="summary" name="summary" value="{{ old('summary', $category->summary) }}"></div>
            <div class="field full"><label for="description">Description (SEO copy on category page)</label><textarea class="input" id="description" name="description" rows="4">{{ old('description', $category->description) }}</textarea></div>
            <div class="field"><label for="seo_title">SEO title (optional)</label><input class="input" id="seo_title" name="seo_title" value="{{ old('seo_title', $category->seo_title) }}"></div>
            <div class="field"><label for="seo_description">SEO description (optional)</label><input class="input" id="seo_description" name="seo_description" value="{{ old('seo_description', $category->seo_description) }}"></div>
        </div>
        <label class="check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active))> Visible on the storefront</label>
        <div class="row" style="margin-top:14px">
            <button class="btn btn-primary" type="submit">Save category</button>
            <a class="btn btn-ghost" href="{{ route('admin.categories.index') }}">Cancel</a>
        </div>
    </form>
@endsection
