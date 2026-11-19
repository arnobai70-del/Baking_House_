<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandRequest;
use App\Models\Brand;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function index(): View
    {
        return view('admin.brands.index', ['brands' => Brand::query()->withCount('products')->orderBy('name')->get()]);
    }

    public function store(BrandRequest $request, AuditLogger $audit): RedirectResponse
    {
        $brand = Brand::create($request->validated());
        $audit->record('brand.created', $brand, after: $brand->only(['name', 'slug']));

        return back()->with('status', "Brand “{$brand->name}” added.");
    }

    public function update(BrandRequest $request, Brand $brand, AuditLogger $audit): RedirectResponse
    {
        $brand->fill($request->validated());
        $before = array_intersect_key($brand->getOriginal(), $brand->getDirty());
        $brand->save();

        if ($brand->wasChanged()) {
            $audit->record('brand.updated', $brand, $before, array_intersect_key($brand->getChanges(), $before));
        }

        return back()->with('status', "Brand “{$brand->name}” saved.");
    }
}
