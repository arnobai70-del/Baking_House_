@extends('layouts.admin', ['title' => 'Categories'])

@section('admin')
    <div class="admin-top">
        <h1>Categories</h1><span class="spacer"></span>
        <a class="btn btn-navy btn-sm" href="{{ route('admin.categories.create') }}"><x-icon name="plus" /> New category</a>
    </div>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Category</th><th>Slug</th><th>Products</th><th>Order</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                @forelse ($categories as $category)
                    @foreach ([$category, ...$category->children] as $row)
                        <tr>
                            <td>
                                @if ($row->parent_id)<span class="muted" aria-hidden="true">↳ </span>@endif
                                <x-icon :name="$row->icon" /> <strong>{{ $row->name }}</strong>
                            </td>
                            <td class="small"><code>{{ $row->slug }}</code></td>
                            <td class="tnum">{{ $row->products_count }}</td>
                            <td class="tnum">{{ $row->sort_order }}</td>
                            <td><span class="status {{ $row->is_active ? 'ok' : 'warn' }}">{{ $row->is_active ? 'Active' : 'Hidden' }}</span></td>
                            <td><a class="btn btn-outline btn-sm" href="{{ route('admin.categories.edit', $row) }}">Edit</a></td>
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="6"><div class="empty-state"><p><strong>No categories yet</strong></p></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
