<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\Seo;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Published information pages (/page/{slug}). Drafts are not found.
 */
class PageController extends Controller
{
    public function __invoke(string $slug): View
    {
        $page = Page::query()->where('slug', $slug)->where('is_published', true)->firstOrFail();

        return view('storefront.page', [
            'page' => $page,
            'seo' => new Seo(
                title: $page->seo_title ?: (string) $page->localized('title'),
                description: $page->seo_description ?: Str::limit(trim(strip_tags($page->html())), 155),
                canonical: $page->url(),
            ),
        ]);
    }
}
