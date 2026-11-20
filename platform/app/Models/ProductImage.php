<?php

namespace App\Models;

use App\Enums\ImageSourceType;
use Database\Factories\ProductImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Product photo under the real-images-only policy.
 *
 * The uploaded original is kept on the PRIVATE disk. Public renditions (resized JPEG / WebP /
 * AVIF) are generated only when a second staff member approves the photo with complete
 * provenance and licence details, and are deleted again when it is rejected, withdrawn or its
 * licence expires, so an unapproved photo has no public URL at all. The storefront only ever
 * reads approved photos (Product::approvedImages).
 */
#[Fillable(['product_variant_id', 'alt_text', 'alt_text_bn', 'source_type', 'creator', 'source_url', 'license_type', 'license_reference', 'license_expires_at'])]
class ProductImage extends Model
{
    /** @use HasFactory<ProductImageFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_WITHDRAWN = 'withdrawn';

    /** Rendition widths in pixels (never upscaled). */
    public const WIDTHS = [400, 800, 1200];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => ImageSourceType::class,
            'license_expires_at' => 'date',
            'authenticity_attested' => 'boolean',
            'is_primary' => 'boolean',
            'renditions' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isApproved(): bool
    {
        return $this->review_status === self::STATUS_APPROVED;
    }

    /**
     * Default public URL (the largest JPEG rendition). Only meaningful while approved.
     */
    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /**
     * srcset for one format ("jpg", "webp", "avif"), or null when that format was not generated.
     */
    public function srcset(string $format): ?string
    {
        $entries = array_filter($this->renditions ?? [], fn (array $rendition) => $rendition['format'] === $format);
        if ($entries === []) {
            return null;
        }

        return implode(', ', array_map(fn (array $rendition) => Storage::disk('public')->url($rendition['path']).' '.$rendition['width'].'w', $entries));
    }

    /**
     * Alt text in the shopper's language, falling back to English.
     */
    public function localizedAlt(): string
    {
        return app()->getLocale() === 'bn' && filled($this->alt_text_bn) ? (string) $this->alt_text_bn : (string) $this->alt_text;
    }

    /**
     * Provenance and licence details are complete enough to approve.
     *
     * @return list<string> missing items (empty when complete)
     */
    public function missingForApproval(): array
    {
        $missing = [];
        if ($this->source_type === null) {
            $missing[] = 'image source';
        }
        if (blank($this->creator)) {
            $missing[] = 'photographer / rights holder';
        }
        if (blank($this->license_type)) {
            $missing[] = 'licence type';
        }
        if ($this->source_type === ImageSourceType::LicensedStock && blank($this->license_reference)) {
            $missing[] = 'licence reference / invoice number';
        }
        if ($this->license_expires_at !== null && $this->license_expires_at->endOfDay()->isPast()) {
            $missing[] = 'a licence that has not expired';
        }
        if (blank($this->alt_text)) {
            $missing[] = 'English alt text';
        }
        if (! $this->authenticity_attested) {
            $missing[] = 'authenticity confirmation (real photo, not AI-generated or AI-altered)';
        }

        return $missing;
    }
}
