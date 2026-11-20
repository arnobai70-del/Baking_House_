<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Information pages (cms.manage). Content must come from the owner: nothing is pre-written, and a
 * page is only public while published. Every save and publish change is audited.
 */
class PageController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.pages.index', [
            'pages' => Page::query()->orderBy('title')->get(),
            'standard' => Page::STANDARD,
        ]);
    }

    public function create(Request $request): View
    {
        $slug = (string) $request->query('slug', '');

        return view('admin.pages.form', ['page' => new Page([
            'slug' => $slug,
            'title' => Page::STANDARD[$slug] ?? '',
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $page = new Page(Arr::except($this->validated($request), ['is_published']));
        $this->savePage($request, $page);

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Page saved.');
    }

    public function edit(Page $page): View
    {
        return view('admin.pages.form', ['page' => $page]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $page->fill(Arr::except($this->validated($request, $page), ['is_published']));
        $this->savePage($request, $page);

        return back()->with('status', 'Page saved.');
    }

    private function savePage(Request $request, Page $page): void
    {
        $wasPublished = $page->exists && $page->getOriginal('is_published');
        $publish = $request->boolean('is_published');
        $page->forceFill([
            'is_published' => $publish,
            'published_at' => $publish ? ($page->published_at ?? now()) : null,
            'updated_by' => $request->user()->id,
        ])->save();

        $this->audit->record($publish && ! $wasPublished ? 'page.published' : ($wasPublished && ! $publish ? 'page.unpublished' : 'page.saved'), $page, after: [
            'slug' => $page->slug, 'published' => $publish,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Page $page = null): array
    {
        return $request->validate([
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('pages', 'slug')->ignore($page?->id)],
            'title' => ['required', 'string', 'max:255'],
            'title_bn' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:60000'],
            'body_bn' => ['nullable', 'string', 'max:60000'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'is_published' => ['nullable', 'boolean'],
        ]);
    }
}
