<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => Category::query()
                ->roots()
                ->with(['children' => fn ($query) => $query->withCount('products')])
                ->withCount('products')
                ->orderBy('sort_order')->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.form', ['category' => new Category(['is_active' => true, 'icon' => 'box']), 'parents' => $this->parents()]);
    }

    public function store(CategoryRequest $request, AuditLogger $audit): RedirectResponse
    {
        $category = Category::create($request->validated());
        $audit->record('category.created', $category, after: $category->only(['name', 'slug', 'parent_id', 'is_active']));

        return redirect()->route('admin.categories.index')->with('status', "Category “{$category->name}” created.");
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', ['category' => $category, 'parents' => $this->parents($category)]);
    }

    public function update(CategoryRequest $request, Category $category, AuditLogger $audit): RedirectResponse
    {
        $category->fill($request->validated());
        $before = array_intersect_key($category->getOriginal(), $category->getDirty());
        $category->save();

        if ($category->wasChanged()) {
            $audit->record('category.updated', $category, $before, array_intersect_key($category->getChanges(), $before));
        }

        return redirect()->route('admin.categories.index')->with('status', "Category “{$category->name}” saved.");
    }

    /**
     * Only top-level categories can be parents (two-level tree).
     *
     * @return Collection<int, Category>
     */
    private function parents(?Category $except = null)
    {
        return Category::query()->roots()->when($except?->exists, fn ($query) => $query->whereKeyNot($except->id))->orderBy('name')->get();
    }
}
