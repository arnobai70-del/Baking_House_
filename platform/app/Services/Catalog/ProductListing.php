<?php

namespace App\Services\Catalog;

use App\Enums\PricingMode;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Storefront product listing: search, filters, sort and pagination. Used by /shop,
 * /category/{slug}, /search and the public catalog API so they always agree.
 *
 * Filters (all optional): q, category[] (slugs), brand[] (slugs), min / max (whole taka;
 * per kg for weight items), in_stock, weight, sort.
 */
class ProductListing
{
    public const SORTS = [
        'popular' => 'Popularity',
        'price_asc' => 'Price: low to high',
        'price_desc' => 'Price: high to low',
        'name' => 'Name A–Z',
        'newest' => 'Newest',
    ];

    public const PER_PAGE = 24;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(array $filters, ?Category $scope = null, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        return $this->query($filters, $scope)
            ->with(['primaryCategory:id,name,name_bn,slug,icon', 'brand:id,name', 'variants' => fn ($query) => $query->where('is_active', true)])
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Product>
     */
    public function query(array $filters, ?Category $scope = null): Builder
    {
        $query = Product::query()->visible();

        if ($scope !== null) {
            $ids = $scope->selfAndDescendantIds();
            $query->whereHas('categories', fn (Builder $category) => $category->whereIn('categories.id', $ids));
        }

        $this->applySearch($query, (string) ($filters['q'] ?? ''));

        $categorySlugs = $this->stringList($filters['category'] ?? []);
        if ($categorySlugs !== []) {
            $query->whereHas('categories', fn (Builder $category) => $category->whereIn('categories.slug', $categorySlugs));
        }

        $brandSlugs = $this->stringList($filters['brand'] ?? []);
        if ($brandSlugs !== []) {
            $query->whereHas('brand', fn (Builder $brand) => $brand->whereIn('slug', $brandSlugs));
        }

        if (is_numeric($filters['min'] ?? null)) {
            $query->where('price_from_paisa', '>=', (int) $filters['min'] * 100);
        }
        if (is_numeric($filters['max'] ?? null)) {
            $query->where('price_from_paisa', '<=', (int) $filters['max'] * 100);
        }
        if (! empty($filters['in_stock'])) {
            $query->where('in_stock', true);
        }
        if (! empty($filters['weight'])) {
            $query->whereHas('variants', fn (Builder $variant) => $variant->where('is_active', true)->where('pricing_mode', PricingMode::Weight->value));
        }

        return $this->applySort($query, (string) ($filters['sort'] ?? 'popular'));
    }

    /**
     * Facet counts for the filter sidebar within the current scope (before other filters).
     *
     * @return array{categories: Collection<int, Category>, brands: Collection<int, Brand>}
     */
    public function facets(?Category $scope = null): array
    {
        $base = fn () => $this->query([], $scope)->reorder();

        $categories = $scope?->children()->active()->get() ?? Category::navigation();
        $categories->each(function (Category $category) use ($base): void {
            $ids = $category->selfAndDescendantIds();
            $category->setAttribute('listing_count', $base()->whereHas('categories', fn (Builder $c) => $c->whereIn('categories.id', $ids))->count());
        });

        $brandCounts = $base()->whereNotNull('brand_id')->selectRaw('brand_id, count(*) as aggregate')->groupBy('brand_id')->pluck('aggregate', 'brand_id');
        $brands = Brand::query()->where('is_active', true)->whereIn('id', $brandCounts->keys())->orderBy('name')->get()
            ->each(fn (Brand $brand) => $brand->setAttribute('listing_count', (int) $brandCounts[$brand->id]));

        return ['categories' => $categories, 'brands' => $brands];
    }

    /**
     * Fast suggestions for the header search box.
     *
     * @return Collection<int, Product>
     */
    public function suggest(string $term, int $limit = 6): Collection
    {
        if (mb_strlen(trim($term)) < 2) {
            return collect();
        }

        return $this->query(['q' => $term])->with('primaryCategory:id,name,name_bn,icon')->limit($limit)->get();
    }

    /**
     * Every term must match the name, keywords (incl. Bangla synonyms), summary or a SKU.
     *
     * @param  Builder<Product>  $query
     */
    private function applySearch(Builder $query, string $term): void
    {
        $terms = array_slice(array_filter(preg_split('/\s+/u', trim($term)) ?: []), 0, 6);

        foreach ($terms as $word) {
            $like = '%'.addcslashes(mb_strtolower($word), '%_\\').'%';
            $query->where(function (Builder $match) use ($like): void {
                $match->whereRaw('lower(products.name) like ?', [$like])
                    ->orWhereRaw('lower(coalesce(products.search_keywords, \'\')) like ?', [$like])
                    ->orWhereRaw('lower(coalesce(products.short_description, \'\')) like ?', [$like])
                    ->orWhereHas('variants', fn (Builder $variant) => $variant->whereRaw('lower(sku) like ?', [$like]));
            });
        }
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'price_asc' => $query->orderByRaw('price_from_paisa is null')->orderBy('price_from_paisa')->orderBy('id'),
            'price_desc' => $query->orderByRaw('price_from_paisa is null')->orderByDesc('price_from_paisa')->orderBy('id'),
            'name' => $query->orderBy('name')->orderBy('id'),
            'newest' => $query->orderByDesc('published_at')->orderByDesc('id'),
            default => $query->orderByDesc('is_featured')->orderByDesc('in_stock')->orderBy('id'),
        };
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        return array_values(array_filter(array_map('strval', (array) $value), fn (string $item) => $item !== ''));
    }
}
