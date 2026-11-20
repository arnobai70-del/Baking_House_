<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ImageSourceType;
use App\Enums\StaffPermission;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Media\ProductImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Product photos. media.manage: upload, describe, order, choose the main photo, delete
 * unpublished photos. media.approve: the review queue (approve / reject / withdraw).
 * Unpublished originals are only ever served to staff through the authenticated preview route.
 */
class MediaController extends Controller
{
    public function __construct(private ProductImageService $images) {}

    public function product(Product $product): View
    {
        return view('admin.media.product', [
            'product' => $product->load(['images.uploader:id,name', 'images.reviewer:id,name', 'variants:id,product_id,sku,name']),
            'sources' => ImageSourceType::cases(),
        ]);
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate(['photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'], 'attested' => ['accepted']] + $this->detailRules($product));

        $this->images->upload($product, $request->file('photo'), $this->details($validated), true, $request->user());

        return back()->with('status', 'Photo uploaded. It stays private until a second staff member approves it.');
    }

    public function update(Request $request, ProductImage $image): RedirectResponse
    {
        $validated = $request->validate(['attested' => ['nullable', 'boolean']] + $this->detailRules($image->product));

        $this->images->update($image, $this->details($validated), $request->boolean('attested'), $request->user());

        return back()->with('status', 'Photo details saved.');
    }

    public function move(Request $request, ProductImage $image): RedirectResponse
    {
        $validated = $request->validate(['direction' => ['required', 'in:up,down']]);
        $this->images->move($image, $validated['direction'] === 'up' ? -1 : 1, $request->user());

        return back();
    }

    public function primary(Request $request, ProductImage $image): RedirectResponse
    {
        $this->images->makePrimary($image, $request->user());

        return back()->with('status', 'Main photo updated.');
    }

    public function destroy(Request $request, ProductImage $image): RedirectResponse
    {
        $this->images->delete($image, $request->user());

        return back()->with('status', 'Photo deleted.');
    }

    public function queue(Request $request): View
    {
        $status = in_array($request->query('status'), [ProductImage::STATUS_PENDING, ProductImage::STATUS_APPROVED, ProductImage::STATUS_REJECTED, ProductImage::STATUS_WITHDRAWN], true)
            ? (string) $request->query('status') : ProductImage::STATUS_PENDING;

        return view('admin.media.queue', [
            'images' => ProductImage::query()->where('review_status', $status)
                ->with(['product:id,name,slug', 'uploader:id,name', 'reviewer:id,name', 'variant:id,sku'])
                ->latest('id')->paginate(24)->withQueryString(),
            'status' => $status,
            'counts' => ProductImage::query()->selectRaw('review_status, count(*) as aggregate')->groupBy('review_status')->pluck('aggregate', 'review_status'),
        ]);
    }

    public function approve(Request $request, ProductImage $image): RedirectResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $this->images->approve($image, $request->user(), $validated['note'] ?? null);

        return back()->with('status', 'Photo approved and published.');
    }

    public function reject(Request $request, ProductImage $image): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $this->images->reject($image, $request->user(), $validated['reason']);

        return back()->with('status', 'Photo rejected.');
    }

    public function withdraw(Request $request, ProductImage $image): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $this->images->withdraw($image, $request->user(), $validated['reason']);

        return back()->with('status', 'Photo withdrawn from the website.');
    }

    /**
     * Staff-only preview of the private original (any review status).
     */
    public function preview(Request $request, ProductImage $image): Response
    {
        abort_unless($request->user()->can(StaffPermission::ManageMedia->value) || $request->user()->can(StaffPermission::ApproveMedia->value), 403);
        abort_if(blank($image->original_path) || ! Storage::disk('local')->exists($image->original_path), 404);

        return response(Storage::disk('local')->get($image->original_path), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function detailRules(Product $product): array
    {
        return [
            'alt_text' => ['required', 'string', 'min:5', 'max:160'],
            'alt_text_bn' => ['nullable', 'string', 'max:160'],
            'source_type' => ['required', Rule::enum(ImageSourceType::class)],
            'creator' => ['required', 'string', 'max:120'],
            'source_url' => ['nullable', 'url:https,http', 'max:500'],
            'license_type' => ['required', 'string', 'max:60'],
            'license_reference' => ['nullable', 'string', 'max:160'],
            'license_expires_at' => ['nullable', 'date_format:Y-m-d'],
            'product_variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('product_id', $product->id)],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function details(array $validated): array
    {
        return [
            'alt_text' => trim($validated['alt_text']),
            'alt_text_bn' => filled($validated['alt_text_bn'] ?? null) ? trim($validated['alt_text_bn']) : null,
            'source_type' => $validated['source_type'],
            'creator' => trim($validated['creator']),
            'source_url' => $validated['source_url'] ?? null,
            'license_type' => trim($validated['license_type']),
            'license_reference' => $validated['license_reference'] ?? null,
            'license_expires_at' => $validated['license_expires_at'] ?? null,
            'product_variant_id' => $validated['product_variant_id'] ?? null,
        ];
    }
}
