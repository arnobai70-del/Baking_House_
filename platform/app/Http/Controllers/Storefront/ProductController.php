<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\PricingMode;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Seo;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(string $slug): View
    {
        $product = Product::query()
            ->visible()
            ->where('slug', $slug)
            ->with([
                'primaryCategory', 'brand:id,name',
                'variants' => fn ($query) => $query->where('is_active', true),
                'approvedImages',
            ])
            ->firstOrFail();

        $trail = $product->primaryCategory->trail();

        $seo = new Seo(
            title: $product->seo_title ?: $product->name,
            description: $product->seo_description ?: $product->short_description,
            canonical: $product->url(),
            ogType: 'product',
        );
        $seo->breadcrumbs([
            ['name' => 'Home', 'url' => route('home')],
            ...array_map(fn (Category $crumb) => ['name' => $crumb->name, 'url' => $crumb->url()], $trail),
            ['name' => $product->name, 'url' => $product->url()],
        ]);
        $seo->addJsonLd($this->productSchema($product));

        $related = Product::query()->visible()
            ->whereKeyNot($product->id)
            ->whereHas('categories', fn ($query) => $query->where('categories.id', $product->primary_category_id))
            ->with(['primaryCategory:id,name,slug,icon', 'variants' => fn ($query) => $query->where('is_active', true)])
            ->limit(5)->get();

        return view('storefront.product', [
            'seo' => $seo,
            'product' => $product,
            'trail' => $trail,
            'variants' => $product->variants,
            'related' => $related,
        ]);
    }

    /**
     * Product + Offer markup built only from real stored price and availability.
     * Weight items are offered per kg; quote-only items carry no Offer.
     *
     * @return array<string, mixed>
     */
    private function productSchema(Product $product): array
    {
        $schema = [
            '@type' => 'Product',
            'name' => $product->name,
            'sku' => $product->defaultVariant()?->sku,
            'description' => $product->short_description ?: strip_tags((string) $product->description),
            'category' => $product->primaryCategory->name,
            'url' => $product->url(),
        ];

        if ($product->brand !== null) {
            $schema['brand'] = ['@type' => 'Brand', 'name' => $product->brand->name];
        }

        $images = $product->approvedImages->map(fn ($image) => $image->url())->all();
        if ($images !== []) {
            $schema['image'] = $images;
        }

        $offers = $product->variants
            ->filter(fn (ProductVariant $variant) => $variant->pricing_mode !== PricingMode::QuoteOnly && $variant->listPricePaisa() !== null)
            ->map(fn (ProductVariant $variant) => array_filter([
                '@type' => 'Offer',
                'sku' => $variant->sku,
                'priceCurrency' => 'BDT',
                'price' => number_format($variant->listPricePaisa() / 100, 2, '.', ''),
                'availability' => $variant->isInStock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url' => $product->url(),
                'unitCode' => $variant->isWeight() ? 'KGM' : null,
            ]))
            ->values()
            ->all();

        if ($offers !== []) {
            $schema['offers'] = count($offers) === 1 ? $offers[0] : $offers;
        }

        return array_filter($schema, fn ($value) => $value !== null && $value !== '');
    }
}
