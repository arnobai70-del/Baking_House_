<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Catalog\ProductListing;
use App\Support\Seo;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(ProductListing $listing): View
    {
        $seo = new Seo(
            title: __('Bakery, Restaurant, Café & Food Business Supply in Bangladesh'),
            canonical: route('home'),
        );
        $seo->addJsonLd([
            '@type' => 'WebSite',
            'name' => config('seo.site_name'),
            'url' => route('home'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => route('catalog.search').'?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ]);

        return view('storefront.home', [
            'seo' => $seo,
            // Departments with the same product counts the shop filters show (Phase 9.5 hero panel).
            'categories' => $listing->facets()['categories'],
            'catalogCount' => Product::query()->visible()->count(),
            'featured' => Product::query()->visible()
                ->with(['primaryImage', 'primaryCategory:id,name,name_bn,slug,icon', 'variants' => fn ($query) => $query->where('is_active', true)])
                ->orderByDesc('is_featured')->orderByDesc('in_stock')->orderBy('id')
                ->limit(10)->get(),
        ]);
    }
}
