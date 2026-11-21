<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Catalog\ProductListing;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /shop, /category/{slug} and /search share one listing. Filtered or sorted variants of a
 * listing canonicalise to the clean URL and are noindex, so they never compete in search.
 */
class ShopController extends Controller
{
    private const FILTER_KEYS = ['q', 'category', 'brand', 'min', 'max', 'in_stock', 'weight', 'sort', 'view'];

    public function __construct(private ProductListing $listing) {}

    public function index(Request $request): View
    {
        $seo = new Seo(
            title: __('Shop all products'),
            description: __('Browse baking ingredients, cake tools, restaurant and Chinese kitchen supplies, commercial equipment and packaging.'),
            canonical: $this->canonical(route('catalog.shop'), $request),
        );
        $seo->breadcrumbs([['name' => __('Home'), 'url' => route('home')], ['name' => __('Shop'), 'url' => route('catalog.shop')]]);

        return $this->render($request, $seo, null, __('All products'));
    }

    public function category(Request $request, string $slug): View
    {
        $category = Category::query()->active()->where('slug', $slug)->firstOrFail();
        abort_if($category->parent_id !== null && $category->parent()->value('is_active') === false, 404);

        /* SEO overrides are written in English; Bangla pages use the translated name / summary. */
        $english = app()->getLocale() === 'en';
        $seo = new Seo(
            title: ($english ? $category->seo_title : null) ?: (string) $category->localized('name'),
            description: ($english ? $category->seo_description : null) ?: $category->localized('summary'),
            canonical: $this->canonical($category->url(), $request),
        );
        $seo->breadcrumbs([
            ['name' => __('Home'), 'url' => route('home')],
            ...array_map(fn (Category $crumb) => ['name' => (string) $crumb->localized('name'), 'url' => $crumb->url()], $category->trail()),
        ]);

        return $this->render($request, $seo, $category, (string) $category->localized('name'));
    }

    public function search(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $seo = (new Seo(
            title: $term === '' ? __('Search') : __('Search results for “:term”', ['term' => $term]),
            canonical: route('catalog.search'),
        ))->noindex();

        return $this->render($request, $seo, null, $term === '' ? __('Search products') : __('Results for “:term”', ['term' => $term]));
    }

    private function render(Request $request, Seo $seo, ?Category $category, string $heading): View
    {
        $filters = $request->only(self::FILTER_KEYS);

        if (array_filter($filters, fn ($value) => $value !== null && $value !== '' && $value !== []) !== []) {
            $seo->noindex();
        }

        return view('storefront.shop', [
            'seo' => $seo,
            'heading' => $heading,
            'category' => $category,
            'trail' => $category?->trail() ?? [],
            'filters' => $filters,
            'products' => $this->listing->paginate($filters, $category),
            'facets' => $this->listing->facets($category),
            'sorts' => ProductListing::SORTS,
            'view' => ($filters['view'] ?? 'grid') === 'list' ? 'list' : 'grid',
        ]);
    }

    /**
     * Keep only the page number in the canonical URL; any filter/sort makes the page noindex.
     */
    private function canonical(string $base, Request $request): string
    {
        $page = (int) $request->query('page', 1);

        return $page > 1 ? "{$base}?page={$page}" : $base;
    }
}
