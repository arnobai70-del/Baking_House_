<?php

namespace App\Models;

use App\Enums\PricingMode;
use App\Enums\ProductStatus;
use App\Models\Concerns\HasTranslations;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'brand_id', 'primary_category_id', 'name', 'name_bn', 'slug', 'short_description', 'short_description_bn', 'description', 'description_bn',
    'specifications', 'search_keywords', 'status', 'is_featured', 'seo_title', 'seo_description',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasTranslations, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'specifications' => 'array',
            'is_featured' => 'boolean',
            'in_stock' => 'boolean',
            'price_from_paisa' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            if ($product->status === ProductStatus::Active && $product->published_at === null) {
                $product->published_at = now();
            }
        });
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'primary_category_id');
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * Only photos that passed the licence / accuracy review (real-images-only policy).
     *
     * @return HasMany<ProductImage, $this>
     */
    public function approvedImages(): HasMany
    {
        return $this->hasMany(ProductImage::class)->where('review_status', ProductImage::STATUS_APPROVED)->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The approved main photo (product cards).
     *
     * @return HasOne<ProductImage, $this>
     */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('review_status', ProductImage::STATUS_APPROVED)->where('is_primary', true);
    }

    /**
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('status', ProductStatus::Active->value)
            ->whereHas('primaryCategory', fn (Builder $category) => $category->where('is_active', true));
    }

    public function isVisible(): bool
    {
        return $this->status === ProductStatus::Active && $this->primaryCategory?->is_active === true;
    }

    /**
     * Recompute the denormalised "from" price (per kg for weight variants) and stock flag used
     * for listing filters and sorting. Called whenever a variant changes.
     */
    public function refreshSummary(): void
    {
        $variants = $this->variants()->where('is_active', true)->get();

        $prices = $variants
            ->map(fn (ProductVariant $variant) => $variant->listPricePaisa())
            ->filter(fn (?int $price) => $price !== null);

        $this->forceFill([
            'price_from_paisa' => $prices->isEmpty() ? null : $prices->min(),
            'in_stock' => $variants->contains(fn (ProductVariant $variant) => $variant->isInStock()),
        ])->saveQuietly();
    }

    public function defaultVariant(): ?ProductVariant
    {
        return $this->variants->firstWhere('is_active', true);
    }

    public function isQuoteOnly(): bool
    {
        $variants = $this->variants->where('is_active', true);

        return $variants->isNotEmpty() && $variants->every(fn (ProductVariant $variant) => $variant->pricing_mode === PricingMode::QuoteOnly);
    }

    public function url(): string
    {
        return route('catalog.product', $this->slug);
    }
}
