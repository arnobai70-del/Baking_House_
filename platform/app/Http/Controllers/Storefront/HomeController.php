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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Homepage as one shopping journey, each block with its own job and no repeated products:
 * hero + department cards → service facts → featured products by department → restaurant & commercial
 * kitchen → combo → business / reseller entry points.
 */
class HomeController extends Controller
{
    /** Departments shown in their own "restaurant & commercial kitchen" block instead of the featured tabs. */
    public const COMMERCIAL_DEPARTMENTS = ['restaurant-supply', 'kitchen-equipment'];

    /** Products per featured department tab. */
    private const PER_DEPARTMENT = 5;

    /** Products in the restaurant & commercial kitchen block. */
    private const COMMERCIAL_LIMIT = 6;

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

        // Departments (same set and counts the shop filters show).
        $categories = $listing->facets()['categories'];
        [$commercial, $general] = $categories->partition(fn (Category $category) => in_array($category->slug, self::COMMERCIAL_DEPARTMENTS, true));

        $commercialProducts = $this->productsIn($commercial, collect(), self::COMMERCIAL_LIMIT);
        $shown = $commercialProducts->pluck('id');

        return view('storefront.home', [
            'seo' => $seo,
            'categories' => $categories,
            'commercialDepartments' => $commercial->values(),
            'commercialProducts' => $commercialProducts,
            'departments' => $this->departmentShelves($general->values(), $shown),
            'combo' => $this->featuredCombo($request, $contexts, $pricer),
        ]);
    }

    /**
     * Up to five visible products per department, featured and in-stock first, never repeating a product
     * already shown in another homepage block. Departments without products are left out.
     *
     * @param  Collection<int, Category>  $categories
     * @param  Collection<int, int>  $exclude
     * @return Collection<int, array{category: Category, products: Collection<int, Product>, photographed: int}>
     */
    private function departmentShelves(Collection $categories, Collection $exclude): Collection
    {
        return $categories->map(function (Category $category) use (&$exclude) {
            $products = $this->productsIn(collect([$category]), $exclude, self::PER_DEPARTMENT);
            $exclude = $exclude->merge($products->pluck('id'));
            $photographed = $products->filter(fn (Product $product) => $product->primaryImage !== null || RepresentativeImage::forSlug($product->slug) !== null)->count();

            return ['category' => $category, 'products' => $products, 'photographed' => $photographed];
        })->filter(fn (array $shelf) => $shelf['products']->isNotEmpty())->values();
    }

    /**
     * Visible products in the given departments (and their sub-categories), featured and in-stock first.
     *
     * @param  Collection<int, Category>  $departments
     * @param  Collection<int, int>  $exclude
     * @return Collection<int, Product>
     */
    private function productsIn(Collection $departments, Collection $exclude, int $limit): Collection
    {
        $ids = $departments->flatMap(fn (Category $category) => $category->children->pluck('id')->push($category->id))->all();
        if ($ids === []) {
            return new Collection;
        }

        return Product::query()->visible()
            ->whereHas('categories', fn (Builder $query) => $query->whereIn('categories.id', $ids))
            ->when($exclude->isNotEmpty(), fn (Builder $query) => $query->whereNotIn('products.id', $exclude->all()))
            ->with(['primaryImage', 'primaryCategory:id,name,name_bn,slug,icon', 'variants' => fn ($query) => $query->where('is_active', true)])
            ->orderByDesc('is_featured')->orderByDesc('in_stock')->orderBy('id')
            ->limit($limit)->get();
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
