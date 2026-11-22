@extends('layouts.admin', ['title' => 'Brands'])

@section('admin')
    <div class="admin-top"><h1>Brands</h1></div>

    <form method="POST" action="{{ route('admin.brands.store') }}" class="card card-pad row" style="margin-bottom:16px;align-items:flex-end" novalidate>
        @csrf
        <div class="field" style="flex:1;margin:0"><label for="new-brand">New brand name</label><input class="input" id="new-brand" name="name" required></div>
        <input type="hidden" name="is_active" value="1">
        <button class="btn btn-navy" type="submit"><x-icon name="plus" /> Add brand</button>
    </form>
    <p class="hint">Only list brands you are authorised to sell; brand names and logos need permission before launch.</p>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Brand</th><th>Products</th><th>Status</th><th><span class="sr-only">Save</span></th></tr></thead>
                <tbody>
                @forelse ($brands as $brand)
                    <tr>
                        <td>
                            <form id="brand-{{ $brand->id }}" method="POST" action="{{ route('admin.brands.update', $brand) }}">@csrf @method('PUT')</form>
                            <label class="sr-only" for="brand-name-{{ $brand->id }}">Name</label>
                            <input class="input" id="brand-name-{{ $brand->id }}" form="brand-{{ $brand->id }}" name="name" value="{{ $brand->name }}" style="min-height:36px">
                        </td>
                        <td class="tnum">{{ $brand->products_count }}</td>
                        <td><label class="check"><input type="hidden" form="brand-{{ $brand->id }}" name="is_active" value="0"><input type="checkbox" form="brand-{{ $brand->id }}" name="is_active" value="1" @checked($brand->is_active)> Active</label></td>
                        <td><button class="btn btn-outline btn-sm" form="brand-{{ $brand->id }}" type="submit">Save</button></td>
                    </tr>
                @empty
                    <tr><td colspan="4"><div class="empty-state"><p><strong>No brands yet</strong></p></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
