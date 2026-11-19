<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;

/**
 * /sitemap.xml — home, shop, active categories and visible products only.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = [
            ['loc' => route('home'), 'lastmod' => null],
            ['loc' => route('catalog.shop'), 'lastmod' => null],
        ];

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

        return response()
            ->view('storefront.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
