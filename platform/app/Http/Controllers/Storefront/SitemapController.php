<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Support\LocalizedRoutes;
use Illuminate\Http\Response;

/**
 * /sitemap.xml — home, shop, active categories and visible products only.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        /* Generate canonical English URLs regardless of the visitor's remembered language. */
        app()->setLocale('en');
        $urls = [
            ['loc' => route('home'), 'lastmod' => null],
            ['loc' => route('catalog.shop'), 'lastmod' => null],
            ['loc' => route('business.landing'), 'lastmod' => null],
            ['loc' => route('reseller.landing'), 'lastmod' => null],
        ];

        $urls[] = ['loc' => route('contact'), 'lastmod' => null];
        Page::query()->where('is_published', true)->orderBy('id')->get(['slug', 'updated_at'])
            ->each(function (Page $page) use (&$urls): void {
                $urls[] = ['loc' => $page->url(), 'lastmod' => $page->updated_at];
            });

        Category::query()->active()->orderBy('id')->get(['slug', 'parent_id', 'updated_at'])
            ->each(function (Category $category) use (&$urls): void {
                $urls[] = ['loc' => $category->url(), 'lastmod' => $category->updated_at];
            });

        Product::query()->visible()->orderBy('id')->select(['id', 'slug', 'updated_at', 'primary_category_id'])
            ->chunk(500, function ($products) use (&$urls): void {
                foreach ($products as $product) {
                    $urls[] = ['loc' => $product->url(), 'lastmod' => $product->updated_at];
                }
            });

        /* Each localized page is listed in both languages with hreflang alternates (D-059). */
        if (LocalizedRoutes::banglaEnabled()) {
            $urls = collect($urls)->flatMap(function (array $url): array {
                $alternates = ['en' => $url['loc'], 'bn' => LocalizedRoutes::banglaUrl($url['loc'])];

                return [$url + ['alternates' => $alternates], ['loc' => $alternates['bn'], 'lastmod' => $url['lastmod'], 'alternates' => $alternates]];
            })->all();
        }

        return response()
            ->view('storefront.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
