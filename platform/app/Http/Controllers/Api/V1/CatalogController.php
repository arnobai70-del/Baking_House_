<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\ProductListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public, read-only catalog API (blueprint §10). Visible products only; public retail prices only.
 */
class CatalogController extends Controller
{
    public function __construct(private ProductListing $listing) {}

    public function categories(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::navigation());
    }

    public function products(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['q', 'category', 'brand', 'min', 'max', 'in_stock', 'weight', 'sort']);
        $perPage = min(max((int) $request->query('per_page', 24), 1), 60);

        return ProductResource::collection($this->listing->paginate($filters, null, $perPage));
    }

    public function product(string $slug): ProductResource
    {
        $product = Product::query()->visible()->where('slug', $slug)
            ->with(['primaryCategory:id,name,name_bn,slug,icon', 'brand:id,name', 'variants' => fn ($query) => $query->where('is_active', true)])
            ->firstOrFail();

        return new ProductResource($product);
    }

    /**
     * GET /api/v1/search?q= — header search suggestions.
     */
    public function search(Request $request): JsonResponse
    {
        $term = (string) $request->query('q', '');

        return response()->json([
            'data' => $this->listing->suggest($term)->map(fn (Product $product) => [
                'name' => $product->name,
                'url' => $product->url(),
                'category' => $product->primaryCategory?->name,
                'icon' => $product->primaryCategory?->icon,
            ]),
            'all_results_url' => route('catalog.search', ['q' => $term]),
        ]);
    }
}
