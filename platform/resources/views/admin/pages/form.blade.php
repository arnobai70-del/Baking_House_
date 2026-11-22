@extends('layouts.admin', ['title' => $page->exists ? 'Edit page' : 'New page'])

@section('admin')
    <div class="admin-top"><h1>{{ $page->exists ? 'Edit: '.$page->title : 'New page' }}</h1><span class="spacer"></span>
        @if ($page->exists && $page->is_published)<a class="btn btn-ghost btn-sm" href="{{ $page->url() }}" target="_blank" rel="noopener">View ↗</a>@endif
        <a class="btn btn-ghost btn-sm" href="{{ route('admin.pages.index') }}">← Pages</a></div>

    <form method="POST" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" class="card card-pad" novalidate>
        @csrf
        @if ($page->exists) @method('PUT') @endif
        <div class="form-grid">
            <div class="field"><label for="title">Title (English)</label><input class="input" id="title" name="title" value="{{ old('title', $page->title) }}" required></div>
            <div class="field"><label for="title_bn">Title (Bangla)</label><input class="input" id="title_bn" name="title_bn" value="{{ old('title_bn', $page->title_bn) }}" lang="bn"></div>
            <div class="field"><label for="slug">Address (slug)</label><input class="input" id="slug" name="slug" value="{{ old('slug', $page->slug) }}" required pattern="[a-z0-9-]+"><p class="hint">/page/<em>slug</em> — lowercase letters, numbers and dashes.</p></div>
            <div class="field full"><label for="body">Content (English) — Markdown: ## heading, - list, **bold**, [link](https://…)</label><textarea class="input" id="body" name="body" rows="14" required>{{ old('body', $page->body) }}</textarea></div>
            <div class="field full"><label for="body_bn">Content (Bangla, optional — English is shown when empty)</label><textarea class="input" id="body_bn" name="body_bn" rows="10" lang="bn">{{ old('body_bn', $page->body_bn) }}</textarea></div>
            <div class="field"><label for="seo_title">SEO title (optional)</label><input class="input" id="seo_title" name="seo_title" value="{{ old('seo_title', $page->seo_title) }}" maxlength="255"></div>
            <div class="field"><label for="seo_description">SEO description (optional, ≤ 160 characters recommended)</label><input class="input" id="seo_description" name="seo_description" value="{{ old('seo_description', $page->seo_description) }}" maxlength="320"></div>
            <div class="field full"><label class="row" style="gap:8px"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $page->is_published))> Published (visible on the website and in the sitemap)</label></div>
        </div>
        <button class="btn btn-navy" type="submit">Save page</button>
    </form>
@endsection
