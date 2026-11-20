<?php

namespace App\Services\Media;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\AuditLogger;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Real-images-only workflow for product photos.
 *
 * Upload (media.manage): the file is decoded and checked (real JPEG / PNG / WebP by content, size
 * and pixel limits, declared AI-generation metadata, duplicates), then RE-ENCODED with GD so no
 * embedded metadata (GPS, camera serials, scripts) survives, and stored on the PRIVATE disk.
 *
 * Approve (media.approve, by someone other than the uploader): requires complete source /
 * licence details, alt text and an authenticity confirmation. Only then are public renditions
 * generated (400 / 800 / 1200 px; JPEG, plus WebP and AVIF where the server supports them).
 * Reject / withdraw / licence expiry delete the public renditions again.
 *
 * AI-generated images cannot be detected reliably by software. The controls are: provenance
 * documentation, a staff attestation, rejection of files that DECLARE AI generation in their
 * metadata (IPTC / C2PA "trainedAlgorithmicMedia", known generator names), and human review.
 */
class ProductImageService
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    public const MIN_EDGE = 600;

    public const MAX_EDGE = 8000;

    public const MAX_PIXELS = 40_000_000;

    /** Metadata markers written by AI image generators / editors (case-insensitive). */
    private const AI_MARKERS = [
        'trainedAlgorithmicMedia', 'compositeWithTrainedAlgorithmicMedia', 'algorithmicMedia',
        'Midjourney', 'DALL-E', 'DALL·E', 'Stable Diffusion', 'StableDiffusion', 'Adobe Firefly',
        'Generative Fill', 'Ideogram', 'Leonardo.Ai', 'NovelAI', 'c2pa.ai_generative',
    ];

    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $details  provenance + alt text (validated by the request)
     *
     * @throws ValidationException
     */
    public function upload(Product $product, UploadedFile $file, array $details, bool $attested, User $uploader): ProductImage
    {
        $bytes = (string) file_get_contents($file->getRealPath());
        $this->assertAcceptable($bytes);

        $hash = hash('sha256', $bytes);
        if (ProductImage::query()->where('sha256', $hash)->exists()) {
            throw ValidationException::withMessages(['photo' => 'This exact photo has already been uploaded.']);
        }

        [$master, $width, $height] = $this->reencode($bytes);
        $originalPath = 'media/originals/'.Str::uuid().'.jpg';
        Storage::disk('local')->put($originalPath, $master);

        $image = new ProductImage($details);
        $image->forceFill([
            'product_id' => $product->id,
            'path' => '',
            'original_path' => $originalPath,
            'review_status' => ProductImage::STATUS_PENDING,
            'sort_order' => (int) $product->images()->max('sort_order') + 1,
            'is_primary' => false,
            'authenticity_attested' => $attested,
            'mime' => 'image/jpeg',
            'width' => $width,
            'height' => $height,
            'bytes' => strlen($master),
            'sha256' => $hash,
            'uploaded_by' => $uploader->id,
        ])->save();

        $this->audit->record('media.uploaded', $image, after: [
            'product_id' => $product->id, 'source_type' => $image->source_type?->value, 'width' => $width, 'height' => $height,
        ], actorId: $uploader->id);

        return $image;
    }

    /**
     * Edit alt text / provenance. Changing provenance of an approved photo sends it back to review
     * (and unpublishes it); alt text edits do not.
     *
     * @param  array<string, mixed>  $details
     */
    public function update(ProductImage $image, array $details, bool $attested, User $actor): ProductImage
    {
        $before = $image->only(['alt_text', 'alt_text_bn', 'source_type', 'creator', 'source_url', 'license_type', 'license_reference', 'license_expires_at', 'product_variant_id']);
        $image->fill($details);
        $image->authenticity_attested = $attested;
        $provenanceChanged = $image->isDirty(['source_type', 'creator', 'source_url', 'license_type', 'license_reference', 'license_expires_at', 'authenticity_attested']);
        $image->save();

        if ($provenanceChanged && $image->isApproved()) {
            $this->unpublish($image, ProductImage::STATUS_PENDING, $actor, 'Provenance changed; needs review again.');
        }

        $this->audit->record('media.updated', $image, $this->stringify($before), $this->stringify($image->only(array_keys($before))), actorId: $actor->id);

        return $image;
    }

    /**
     * @throws ValidationException
     */
    public function approve(ProductImage $image, User $reviewer, ?string $note = null): ProductImage
    {
        if ($image->review_status !== ProductImage::STATUS_PENDING) {
            throw ValidationException::withMessages(['review' => 'Only photos waiting for review can be approved.']);
        }
        if (config('media.require_second_reviewer', true) && $image->uploaded_by === $reviewer->id) {
            throw ValidationException::withMessages(['review' => 'A different staff member must approve a photo you uploaded.']);
        }
        $missing = $image->missingForApproval();
        if ($missing !== []) {
            throw ValidationException::withMessages(['review' => 'Cannot approve yet. Missing: '.implode(', ', $missing).'.']);
        }

        $master = Storage::disk('local')->get((string) $image->original_path);
        if ($master === null) {
            throw ValidationException::withMessages(['review' => 'The original file is missing.']);
        }
        $renditions = $this->renditions($image, $master);

        DB::transaction(function () use ($image, $reviewer, $note, $renditions): void {
            $largestJpeg = collect($renditions)->where('format', 'jpg')->sortByDesc('width')->first();
            $hasPrimary = ProductImage::query()->where('product_id', $image->product_id)->where('review_status', ProductImage::STATUS_APPROVED)->where('is_primary', true)->exists();

            $image->forceFill([
                'review_status' => ProductImage::STATUS_APPROVED,
                'path' => $largestJpeg['path'],
                'renditions' => $renditions,
                'is_primary' => ! $hasPrimary,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ])->save();
        });

        $this->audit->record('media.approved', $image, ['review_status' => ProductImage::STATUS_PENDING], [
            'review_status' => ProductImage::STATUS_APPROVED, 'renditions' => count($renditions), 'formats' => array_values(array_unique(array_column($renditions, 'format'))),
        ], $note, $reviewer->id);

        return $image;
    }

    /**
     * @throws ValidationException
     */
    public function reject(ProductImage $image, User $reviewer, string $reason): ProductImage
    {
        if ($image->review_status !== ProductImage::STATUS_PENDING) {
            throw ValidationException::withMessages(['review' => 'Only photos waiting for review can be rejected.']);
        }
        $this->requireReason($reason);

        $this->unpublish($image, ProductImage::STATUS_REJECTED, $reviewer, $reason);

        return $image;
    }

    /**
     * Take an approved photo off the website (wrong product, licence problem, ...).
     *
     * @throws ValidationException
     */
    public function withdraw(ProductImage $image, ?User $actor, string $reason): ProductImage
    {
        if (! $image->isApproved()) {
            throw ValidationException::withMessages(['review' => 'Only published photos can be withdrawn.']);
        }
        $this->requireReason($reason);

        $this->unpublish($image, ProductImage::STATUS_WITHDRAWN, $actor, $reason);

        return $image;
    }

    /**
     * @throws ValidationException
     */
    public function makePrimary(ProductImage $image, User $actor): void
    {
        if (! $image->isApproved()) {
            throw ValidationException::withMessages(['review' => 'Only an approved photo can be the main photo.']);
        }

        DB::transaction(function () use ($image): void {
            ProductImage::query()->where('product_id', $image->product_id)->update(['is_primary' => false]);
            $image->forceFill(['is_primary' => true])->save();
        });
        $this->audit->record('media.primary_set', $image, after: ['product_id' => $image->product_id], actorId: $actor->id);
    }

    /**
     * Move a photo one place up (-1) or down (+1) in the product's gallery order.
     */
    public function move(ProductImage $image, int $direction, User $actor): void
    {
        $siblings = ProductImage::query()->where('product_id', $image->product_id)->orderBy('sort_order')->orderBy('id')->get()->values();
        $index = $siblings->search(fn (ProductImage $sibling) => $sibling->is($image));
        $target = $index + ($direction < 0 ? -1 : 1);
        if ($index === false || $target < 0 || $target >= $siblings->count()) {
            return;
        }

        $ordered = $siblings->all();
        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];
        DB::transaction(function () use ($ordered): void {
            foreach ($ordered as $position => $sibling) {
                $sibling->forceFill(['sort_order' => $position + 1])->saveQuietly();
            }
        });
        $this->audit->record('media.reordered', $image, after: ['direction' => $direction < 0 ? 'up' : 'down'], actorId: $actor->id);
    }

    /**
     * Delete a photo that is not published (pending / rejected / withdrawn), including its private file.
     *
     * @throws ValidationException
     */
    public function delete(ProductImage $image, User $actor): void
    {
        if ($image->isApproved()) {
            throw ValidationException::withMessages(['review' => 'Withdraw a published photo before deleting it.']);
        }

        $this->deleteRenditions($image);
        if ($image->original_path) {
            Storage::disk('local')->delete($image->original_path);
        }
        $this->audit->record('media.deleted', $image, ['product_id' => $image->product_id, 'review_status' => $image->review_status], actorId: $actor->id);
        $image->delete();
    }

    /**
     * Withdraw published photos whose licence has expired. Returns how many.
     */
    public function withdrawExpiredLicences(): int
    {
        $count = 0;
        ProductImage::query()->where('review_status', ProductImage::STATUS_APPROVED)
            ->whereNotNull('license_expires_at')->whereDate('license_expires_at', '<', now('Asia/Dhaka')->toDateString())
            ->orderBy('id')->each(function (ProductImage $image) use (&$count): void {
                $this->unpublish($image, ProductImage::STATUS_WITHDRAWN, null, 'Licence expired');
                $count++;
            });

        return $count;
    }

    /**
     * @throws ValidationException
     */
    public function assertAcceptable(string $bytes): void
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['photo' => $message]);

        if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            $fail('The photo must be between 1 byte and 10 MB.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $fail('Upload a JPEG, PNG or WebP photograph.');
        }

        $info = @getimagesizefromstring($bytes);
        if ($info === false) {
            $fail('The file is not a readable image.');
        }
        [$width, $height] = $info;
        if (min($width, $height) < self::MIN_EDGE) {
            $fail('The photo is too small: at least '.self::MIN_EDGE.' px on each side is required for a sharp product page.');
        }
        if (max($width, $height) > self::MAX_EDGE || $width * $height > self::MAX_PIXELS) {
            $fail('The photo is too large: at most '.self::MAX_EDGE.' px per side.');
        }

        foreach (self::AI_MARKERS as $marker) {
            if (stripos($bytes, $marker) !== false) {
                $fail("This file's metadata declares AI-generated or AI-edited content (\"{$marker}\"). Only real photographs are accepted.");
            }
        }
    }

    /**
     * Decode and re-encode as a clean JPEG master (drops all metadata). PNG / WebP transparency is
     * flattened onto white.
     *
     * @return array{0: string, 1: int, 2: int}
     *
     * @throws ValidationException
     */
    private function reencode(string $bytes): array
    {
        $source = @imagecreatefromstring($bytes);
        if (! $source instanceof GdImage) {
            throw ValidationException::withMessages(['photo' => 'The file is not a readable image.']);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);

        return [$this->encode($canvas, 'jpg', 92), $width, $height];
    }

    /**
     * @return list<array{width: int, height: int, format: string, path: string}>
     */
    private function renditions(ProductImage $image, string $master): array
    {
        $source = imagecreatefromstring($master);
        $originalWidth = imagesx($source);
        $widths = array_values(array_unique(array_map(fn (int $width) => min($width, $originalWidth), ProductImage::WIDTHS)));
        $formats = ['jpg'];
        if (function_exists('imagewebp')) {
            $formats[] = 'webp';
        }
        if (function_exists('imageavif') && config('media.avif', true)) {
            $formats[] = 'avif';
        }

        $this->deleteRenditions($image);
        $renditions = [];
        foreach ($widths as $width) {
            $resized = imagescale($source, $width, -1, IMG_BICUBIC);
            foreach ($formats as $format) {
                $encoded = $this->encode($resized, $format);
                if ($encoded === '') {
                    continue;
                }
                $path = "media/products/{$image->product_id}/{$image->id}-".Str::lower(Str::random(8))."-{$width}.{$format}";
                Storage::disk('public')->put($path, $encoded);
                $renditions[] = ['width' => $width, 'height' => imagesy($resized), 'format' => $format, 'path' => $path];
            }
        }

        return $renditions;
    }

    private function encode(GdImage $image, string $format, ?int $quality = null): string
    {
        ob_start();
        $ok = match ($format) {
            'jpg' => imagejpeg($image, null, $quality ?? 82),
            'webp' => imagewebp($image, null, $quality ?? 80),
            'avif' => @imageavif($image, null, $quality ?? 55, 8),
        };
        $data = (string) ob_get_clean();

        return $ok ? $data : '';
    }

    private function unpublish(ProductImage $image, string $status, ?User $actor, string $reason): void
    {
        $before = $image->review_status;
        $this->deleteRenditions($image);
        $wasPrimary = $image->is_primary;

        $image->forceFill([
            'review_status' => $status,
            'path' => '',
            'renditions' => null,
            'is_primary' => false,
            'reviewed_by' => $actor?->id ?? $image->reviewed_by,
            'reviewed_at' => now(),
            'review_note' => $reason,
        ])->save();

        if ($wasPrimary) {
            ProductImage::query()->where('product_id', $image->product_id)->where('review_status', ProductImage::STATUS_APPROVED)
                ->orderBy('sort_order')->first()?->forceFill(['is_primary' => true])->save();
        }

        $this->audit->record('media.'.$status, $image, ['review_status' => $before], ['review_status' => $status], $reason, $actor?->id);
    }

    private function deleteRenditions(ProductImage $image): void
    {
        $paths = array_column($image->renditions ?? [], 'path');
        if ($paths !== []) {
            Storage::disk('public')->delete($paths);
        }
    }

    /**
     * @throws ValidationException
     */
    private function requireReason(string $reason): void
    {
        if (mb_strlen(trim($reason)) < 3) {
            throw ValidationException::withMessages(['reason' => 'Please give a reason.']);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function stringify(array $values): array
    {
        return array_map(fn ($value) => $value instanceof \BackedEnum ? $value->value : ($value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value), $values);
    }
}
