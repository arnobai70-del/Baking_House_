<?php

namespace App\Support;

use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * Labelled stock photo for a generic product (D-071). Never replaces an approved product photo.
 */
final class RepresentativeImage
{
    public const DIRECTORY = 'images/representative';

    /**
     * @return array{src: string, srcset: ?string, alt: string}|null
     */
    public static function forSlug(?string $slug): ?array
    {
        $alt = $slug === null ? null : config('representative_images.'.$slug);
        $path = self::DIRECTORY.'/'.$slug.'.webp';
        if (! is_string($alt) || ! is_file(public_path($path))) {
            return null;
        }

        $small = self::DIRECTORY.'/'.$slug.'-400.webp';
        $srcset = is_file(public_path($small)) ? asset($small).' 400w, '.asset($path).' 800w' : null;

        return ['src' => asset($path), 'srcset' => $srcset, 'alt' => __($alt)];
    }

    /**
     * Representative images for cart lines, keyed by SKU; products that already have an approved photo get none.
     *
     * @param  Collection<int, string>  $skus
     * @return array<string, array{src: string, srcset: ?string, alt: string}>
     */
    public static function bySkus(Collection $skus): array
    {
        if ($skus->isEmpty()) {
            return [];
        }

        return ProductVariant::query()->whereIn('sku', $skus->all())
            ->with('product:id,slug')
            ->get(['sku', 'product_id'])
            ->reject(fn (ProductVariant $variant) => ProductImage::query()->where('product_id', $variant->product_id)->where('review_status', ProductImage::STATUS_APPROVED)->exists())
            ->mapWithKeys(fn (ProductVariant $variant) => [$variant->sku => self::forSlug($variant->product?->slug)])
            ->filter()
            ->all();
    }
}
