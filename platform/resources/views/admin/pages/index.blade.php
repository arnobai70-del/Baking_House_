@extends('layouts.admin', ['title' => 'Pages'])

@section('admin')
    <div class="admin-top"><h1>Information pages</h1><span class="spacer"></span><a class="btn btn-navy btn-sm" href="{{ route('admin.pages.create') }}">New page</a></div>
    <div class="alert alert-info" style="margin-bottom:16px"><x-icon name="info" /><span>Write only verified owner information. Policies, contact details and legal text are not pre-filled; a page is public only while it is published.</span></div>

    <div class="card" style="margin-bottom:16px">
        <div class="panel-title"><h2>Standard pages (linked from the footer)</h2></div>
        <div class="table-wrap"><table class="table"><tbody>
            @foreach ($standard as $slug => $label)
                @php $page = $pages->firstWhere('slug', $slug); @endphp
                <tr><td><strong>{{ $label }}</strong><div class="muted small">/page/{{ $slug }}</div></td>
                    <td>@if ($page)<span class="status {{ $page->is_published ? 'ok' : 'warn' }}">{{ $page->is_published ? 'Published' : 'Draft' }}</span>@else<span class="status">Not written</span>@endif</td>
                    <td>@if ($page)<a class="btn btn-outline btn-sm" href="{{ route('admin.pages.edit', $page) }}">Edit</a>@else<a class="btn btn-outline btn-sm" href="{{ route('admin.pages.create', ['slug' => $slug]) }}">Write</a>@endif</td></tr>
            @endforeach
        </tbody></table></div>
    </div>

    @php $other = $pages->reject(fn ($page) => array_key_exists($page->slug, $standard)); @endphp
    @if ($other->isNotEmpty())
        <div class="card"><div class="panel-title"><h2>Other pages</h2></div>
            <div class="table-wrap"><table class="table"><tbody>
                @foreach ($other as $page)
                    <tr><td><strong>{{ $page->title }}</strong><div class="muted small">/page/{{ $page->slug }}</div></td>
                        <td><span class="status {{ $page->is_published ? 'ok' : 'warn' }}">{{ $page->is_published ? 'Published' : 'Draft' }}</span></td>
                        <td><a class="btn btn-outline btn-sm" href="{{ route('admin.pages.edit', $page) }}">Edit</a></td></tr>
                @endforeach
            </tbody></table></div></div>
    @endif
@endsection
