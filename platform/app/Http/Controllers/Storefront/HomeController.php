<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\ProductListing;
use App\Services\Pricing\PricingContextResolver;
use App\Services\Promotions\BundlePricer;
use App\Support\RepresentativeImage;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class HomeController extends Controller
{
    /** Products per department tab on the homepage. */
    private const PER_DEPARTMENT = 5;

    public function __invoke(Request $request, ProductListing $listing, PricingContextResolver $contexts, BundlePricer $pricer): View
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

        // Departments (same set and counts the shop filters show) for the tiles, tabs and banners.
        $categories = $listing->facets()['categories'];

        return view('storefront.home', [
            'seo' => $seo,
            'categories' => $categories,
            'departments' => $this->departmentShelves($categories),
            'combo' => $this->featuredCombo($request, $contexts, $pricer),
        ]);
    }

    /**
     * Up to five visible products per department (the department and its sub-categories), featured
     * and in-stock first. Departments without products are left out.
     *
     * @param  Collection<int, Category>  $categories
     * @return Collection<int, array{category: Category, products: Collection<int, Product>, photographed: int}>
     */
    private function departmentShelves(Collection $categories): Collection
    {
        return $categories->map(function (Category $category) {
            $ids = $category->children->pluck('id')->push($category->id)->all();
            $products = Product::query()->visible()
                ->whereHas('categories', fn ($query) => $query->whereIn('categories.id', $ids))
                ->with(['primaryImage', 'primaryCategory:id,name,name_bn,slug,icon', 'variants' => fn ($query) => $query->where('is_active', true)])
                ->orderByDesc('is_featured')->orderByDesc('in_stock')->orderBy('id')
                ->limit(self::PER_DEPARTMENT)->get();

            $photographed = $products->filter(fn (Product $product) => $product->primaryImage !== null || RepresentativeImage::forSlug($product->slug) !== null)->count();

            return ['category' => $category, 'products' => $products, 'photographed' => $photographed];
        })->filter(fn (array $shelf) => $shelf['products']->isNotEmpty())->values();
    }

    /**
     * The first live combo this buyer may purchase, priced on the server (null when none applies).
     *
     * @return array{bundle: Bundle, quote: array<string, mixed>}|null
     */
    private function featuredCombo(Request $request, PricingContextResolver $contexts, BundlePricer $pricer): ?array
    {
        $context = $contexts->forWeb($request);
        $bundle = Bundle::query()->effectiveAt($context->at)->with('items.variant.product')->orderBy('name')->get()
            ->first(fn (Bundle $bundle) => $bundle->allowsAudience($context->audience));

        return $bundle ? ['bundle' => $bundle, 'quote' => $pricer->quote($bundle, 1, $context)] : null;
    }
}
