<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CakeDesign;
use App\Models\CakeOption;
use App\Models\CakeProduct;
use App\Models\CakeUpload;
use App\Models\Cart;
use App\Models\ProductVariant;
use App\Services\Cakes\CakeCanvas;
use App\Services\Cakes\CakeGeometry;
use App\Services\Cakes\CakeImageStore;
use App\Services\Cakes\CakeLibrary;
use App\Services\Cakes\CakePricer;
use App\Services\Cakes\CakeSchedule;
use App\Services\Cakes\CakeSettings;
use App\Services\Cart\CartService;
use App\Support\Seo;
use App\Support\Taka;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * /custom-cake (and /bn/custom-cake): the 6-step cake designer. Choices are priced on the server line by line
 * (CakePricer), the hand-made top design is a sanitised list of layers (CakeCanvas), customer photos stay private
 * (CakeImageStore), and a finished design goes into the normal cart → checkout → order pipeline.
 *
 * Saved designs, share links (photos never shared), duplicates and re-orders all copy the specification only.
 */
class CakeDesignerController extends Controller
{
    public function __construct(
        private CakePricer $pricer,
        private CakeSchedule $schedule,
        private CakeSettings $settings,
        private CartService $carts,
        private CakeCanvas $canvas,
        private CakeLibrary $library,
    ) {}

    public function show(Request $request): View
    {
        $design = $this->ownedDesign($request, $request->query('design'));
        $cakes = $this->cakeProducts();
        $cake = $design?->variant?->product?->cakeProfile
            ?? $cakes->first(fn (CakeProduct $c) => $c->product->slug === $request->query('product'))
            ?? $cakes->first();

        return view('storefront.cakes.designer', $this->designerData($request, $cakes, $cake, $design, $design?->spec ?? ($cake ? $this->defaultSpec($cake) : []), false));
    }

    /**
     * Read-only view of a shared design. Photos are private and are never part of a shared design.
     */
    public function shared(Request $request, string $token): View
    {
        $design = CakeDesign::query()->where('share_token', $token)->firstOrFail();
        $cake = $design->variant?->product?->cakeProfile;
        abort_if($cake === null, 404);

        return view('storefront.cakes.designer', array_merge($this->designerData($request, collect([$cake]), $cake, null, $this->withoutPhotos((array) $design->spec), true), [
            'sharedToken' => $token,
            'seo' => new Seo(title: __('A shared cake design'), canonical: route('cakes.designer'), indexable: false),
        ]));
    }

    /**
     * Live price + available dates for the current choices (JSON; nothing is stored).
     */
    public function price(Request $request): JsonResponse
    {
        $cake = $this->cakeFromRequest($request);
        $spec = $this->specFromRequest($request, $cake);
        $priced = $this->pricer->price($this->variantOf($cake), $spec, $request->input('date') ?: null, $request->input('slot') ?: null);
        $scheduleError = $priced['ok'] && $request->filled('date')
            ? $this->schedule->check($cake, $priced['complexity'], (string) $request->input('date'), $request->input('slot'))
            : null;

        return response()->json([
            'ok' => $priced['ok'] && $scheduleError === null,
            'error' => $priced['error'] ?? $scheduleError,
            'requires_quote' => $priced['requires_quote'],
            'complexity' => $priced['complexity'],
            'breakdown' => array_map(fn (array $part) => ['label' => $part['label'], 'amount' => Taka::format($part['paisa'])], $priced['breakdown']),
            'total' => Taka::format($priced['line_paisa']),
            'test' => $priced['uses_test_prices'],
            'dates' => $this->schedule->availableDates($cake, $priced['complexity']),
            'print_quality' => $this->printQuality($request, $spec),
        ]);
    }

    /**
     * Save the design and put it in the cart (or update a design already in the cart).
     */
    public function store(Request $request, CakeImageStore $images): RedirectResponse
    {
        $cake = $this->cakeFromRequest($request);
        $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'slot' => ['required', 'string', 'max:32'],
            'name' => ['nullable', 'string', 'max:80'],
        ], ['date.required' => __('Choose a date for your cake.'), 'slot.required' => __('Choose a time slot.')]);

        $variant = $this->variantOf($cake);
        $spec = $this->specFromRequest($request, $cake);
        $this->requireRights($request, $spec);
        $priced = $this->pricer->price($variant, $spec, (string) $request->input('date'), (string) $request->input('slot'));
        if (! $priced['ok']) {
            throw ValidationException::withMessages(['design' => (string) $priced['error']]);
        }
        $error = $this->schedule->check($cake, $priced['complexity'], (string) $request->input('date'), (string) $request->input('slot'));
        if ($error !== null) {
            throw ValidationException::withMessages(['date' => $error]);
        }

        $cart = $this->carts->forRequest($request, true);
        $design = $this->saveDesign($request, $variant, $spec, $images, $cart, [
            'scheduled_for' => (string) $request->input('date'),
            'time_slot' => CakeSettings::normaliseSlot((string) $request->input('slot')),
            'price_snapshot' => ['line_paisa' => $priced['line_paisa'], 'breakdown' => $priced['breakdown']],
        ]);
        $this->carts->addCakeDesign($cart, $design);

        $response = redirect()->route('cart.show')->with('status', __('Your cake design is in the cart.'));

        return $request->user() === null
            ? $response->withCookie(cookie(CartService::COOKIE, $cart->token, CartService::COOKIE_MINUTES))
            : $response;
    }

    /**
     * Save a design to the account without a date (signed-in customers only).
     */
    public function save(Request $request, CakeImageStore $images): RedirectResponse
    {
        $request->validate(['name' => ['nullable', 'string', 'max:80']]);
        $cake = $this->cakeFromRequest($request);
        $spec = $this->specFromRequest($request, $cake);
        $this->requireRights($request, $spec);
        $date = $request->input('date');
        $design = $this->saveDesign($request, $this->variantOf($cake), $spec, $images, null, [
            'scheduled_for' => is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null,
            'time_slot' => $request->filled('slot') ? CakeSettings::normaliseSlot((string) $request->input('slot')) : null,
        ]);

        return redirect()->route('cakes.designer', ['design' => $design->uuid])->with('status', __('Design saved. Find it any time under My cake designs.'));
    }

    /**
     * Owner-only: create (or reuse) a share link. Photos are not included in what others see.
     */
    public function share(Request $request, string $uuid): JsonResponse
    {
        $design = $this->ownedDesign($request, $uuid, includeOrdered: true) ?? abort(404);
        if ($design->share_token === null) {
            $design->forceFill(['share_token' => Str::random(40)])->save();
        }

        return response()->json(['url' => route('cakes.shared', $design->share_token)]);
    }

    /**
     * Copy one of the visitor's own designs (also used for "Order again"): the specification and photos are
     * kept, the date and cart status are not.
     */
    public function duplicate(Request $request, string $uuid): RedirectResponse
    {
        $source = $this->ownedDesign($request, $uuid, includeOrdered: true) ?? abort(404);

        return $this->copy($request, $source, (array) $source->spec);
    }

    /**
     * Start a new design from someone's shared link (without their photos).
     */
    public function useShared(Request $request, string $token): RedirectResponse
    {
        $source = CakeDesign::query()->where('share_token', $token)->firstOrFail();

        return $this->copy($request, $source, $this->withoutPhotos((array) $source->spec));
    }

    /**
     * Upload a photo for an edible print. The customer must confirm they own it or have permission.
     */
    public function upload(Request $request, CakeImageStore $images): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'rights' => ['accepted'],
        ], ['rights.accepted' => __('Please confirm you own this photo or have permission to print it.')]);

        $cart = $request->user() === null ? $this->carts->forRequest($request, true) : null;
        $upload = $images->storeUpload($request->file('photo'), $request->user(), $cart?->token);

        $response = response()->json([
            'uuid' => $upload->uuid, 'url' => route('cakes.uploads.show', $upload->uuid), 'width' => $upload->width, 'height' => $upload->height,
        ], 201);

        return $cart !== null ? $response->withCookie(cookie(CartService::COOKIE, $cart->token, CartService::COOKIE_MINUTES)) : $response;
    }

    /**
     * A private photo, for its owner (or order staff) only.
     */
    public function showUpload(Request $request, string $uuid): Response
    {
        $upload = CakeUpload::query()->where('uuid', $uuid)->firstOrFail();
        $cart = $this->carts->forRequest($request);
        $staff = $request->user()?->can('orders.manage') ?? false;
        abort_unless($staff || $upload->ownedBy($request->user(), $cart?->token), 404);

        return Storage::disk('local')->response($upload->path, null, [
            'Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * A design's 3D snapshot or top-design picture, for its owner (or order staff) only.
     */
    public function designImage(Request $request, string $uuid, string $kind): Response
    {
        $design = CakeDesign::query()->where('uuid', $uuid)->firstOrFail();
        $staff = $request->user()?->can('orders.manage') ?? false;
        abort_unless($staff || $this->ownedDesign($request, $uuid, includeOrdered: true) !== null, 404);
        $path = $kind === 'top' ? $design->top_path : $design->snapshot_path;
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }

    /**
     * Signed-in customer's saved, in-cart and ordered designs.
     */
    public function mine(Request $request): View
    {
        return view('account.cake-designs', [
            'designs' => CakeDesign::query()->where('user_id', $request->user()->id)->with('variant.product')->latest('updated_at')->limit(60)->get(),
        ]);
    }

    /**
     * @param  Collection<int, CakeProduct>  $cakes
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    private function designerData(Request $request, Collection $cakes, ?CakeProduct $cake, ?CakeDesign $design, array $spec, bool $readOnly): array
    {
        $priced = $cake ? $this->pricer->price($this->variantOf($cake), $spec) : null;
        $options = $cake ? $this->pricer->optionsFor($cake)->groupBy('group') : collect();
        $owned = $readOnly ? collect() : $this->ownedUploads($request, $design?->uploadUuids() ?? []);

        return [
            'seo' => new Seo(title: __('Design your custom cake'), canonical: route('cakes.designer')),
            'cakes' => $cakes,
            'cake' => $cake,
            'options' => $options,
            'spec' => $spec,
            'design' => $design,
            'priced' => $priced,
            'readOnly' => $readOnly,
            'dates' => $cake && $priced && ! $readOnly ? $this->schedule->availableDates($cake, $priced['complexity']) : [],
            'ordersOpen' => $this->settings->ordersOpen(),
            'leadHours' => $cake ? $this->settings->leadHours('standard', $cake->lead_time_hours) : null,
            'client' => $cake === null ? [] : [
                'shapes' => $options->get('shape', collect())->pluck('code')->values(),
                'drips' => $options->get('drip', collect())->map(fn (CakeOption $o) => ['code' => $o->code, 'colour' => $o->meta['colour'] ?? '#5a3825'])->values(),
                'library' => $this->library->forClient(),
                'geometry' => ['cmPerSqrtKg' => CakeGeometry::CM_PER_SQRT_KG, 'tierScale' => CakeGeometry::TIER_SCALE, 'dpiGood' => CakeGeometry::DPI_GOOD, 'dpiFair' => CakeGeometry::DPI_FAIR],
                'uploads' => $owned->map(fn (CakeUpload $u) => ['uuid' => $u->uuid, 'url' => route('cakes.uploads.show', $u->uuid), 'width' => $u->width, 'height' => $u->height])->values(),
                'freeColours' => CakePricer::FREE_COLOURS,
                'customColourPaisa' => $options->get('colour', collect())->firstWhere('code', 'custom')?->price_paisa,
                'urls' => ['upload' => route('cakes.uploads.store'), 'share' => $design ? route('cakes.share', $design->uuid) : null],
                'maxImages' => CakeCanvas::MAX_IMAGES,
                'maxLayers' => CakeCanvas::MAX_LAYERS,
                'readOnly' => $readOnly,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $extra
     */
    private function saveDesign(Request $request, ProductVariant $variant, array $spec, CakeImageStore $images, ?Cart $cart, array $extra): CakeDesign
    {
        $cart ??= $request->user() === null ? $this->carts->forRequest($request, true) : null;
        $design = $this->ownedDesign($request, $request->input('design')) ?? new CakeDesign(['uuid' => (string) Str::uuid()]);
        $design->forceFill([
            'name' => trim((string) $request->input('name')) ?: $design->name,
            'user_id' => $request->user()?->id,
            'owner_token_hash' => $request->user() === null ? hash('sha256', (string) $cart?->token) : null,
            'product_variant_id' => $variant->id,
            'spec' => $spec,
        ] + $extra)->save();

        $images->storeDesignImage($design, 'snapshot', $request->input('snapshot'));
        $images->storeDesignImage($design, 'top', $request->input('top_image'));
        if ($design->uploadUuids() !== []) {
            CakeUpload::query()->whereIn('uuid', $design->uploadUuids())->update(['cake_design_id' => $design->id]);
        }

        return $design;
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function copy(Request $request, CakeDesign $source, array $spec): RedirectResponse
    {
        $cart = $request->user() === null ? $this->carts->forRequest($request, true) : null;
        $copy = new CakeDesign;
        $copy->forceFill([
            'uuid' => (string) Str::uuid(),
            'name' => $source->name ? Str::limit(__('Copy of :name', ['name' => $source->name]), 80, '') : null,
            'user_id' => $request->user()?->id,
            'owner_token_hash' => $cart !== null ? hash('sha256', $cart->token) : null,
            'product_variant_id' => $source->product_variant_id,
            'spec' => $spec,
            'status' => CakeDesign::DRAFT,
            'copied_from_id' => $source->id,
        ])->save();

        $response = redirect()->route('cakes.designer', ['design' => $copy->uuid])->with('status', __('A copy of the design is ready. Choose a date and add it to your cart.'));

        return $cart !== null ? $response->withCookie(cookie(CartService::COOKIE, $cart->token, CartService::COOKIE_MINUTES)) : $response;
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function requireRights(Request $request, array $spec): void
    {
        $hasPhotos = collect($spec['canvas']['layers'] ?? [])->contains(fn ($layer) => ($layer['type'] ?? '') === 'image');
        if ($hasPhotos && ! $request->boolean('rights')) {
            throw ValidationException::withMessages(['rights' => __('Please confirm you own the photos on this cake or have permission to print them.')]);
        }
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return list<array<string, mixed>>
     */
    private function printQuality(Request $request, array $spec): array
    {
        $uuids = array_column(array_filter((array) ($spec['canvas']['layers'] ?? []), fn ($l) => ($l['type'] ?? '') === 'image'), 'upload');
        $uploads = $this->ownedUploads($request, $uuids)->keyBy('uuid')->all();

        return $this->canvas->printQuality((array) ($spec['canvas']['layers'] ?? []), $uploads, CakeGeometry::topDiameterCm((int) ($spec['grams'] ?? 0), (int) ($spec['tiers'] ?? 1)));
    }

    /**
     * @param  list<string>  $uuids
     * @return Collection<int, CakeUpload>
     */
    private function ownedUploads(Request $request, array $uuids): Collection
    {
        if ($uuids === []) {
            return collect();
        }
        $cart = $this->carts->forRequest($request);

        return CakeUpload::query()->whereIn('uuid', $uuids)->get()->filter(fn (CakeUpload $u) => $u->ownedBy($request->user(), $cart?->token))->values();
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    private function withoutPhotos(array $spec): array
    {
        $layers = array_values(array_filter((array) ($spec['canvas']['layers'] ?? []), fn ($layer) => ($layer['type'] ?? '') !== 'image'));
        $spec['canvas'] = ['layers' => $layers];
        $spec['print'] = false;

        return $spec;
    }

    /**
     * @return Collection<int, CakeProduct>
     */
    private function cakeProducts(): Collection
    {
        return CakeProduct::query()->with('product')
            ->whereHas('product', fn ($product) => $product->visible())
            ->orderBy('id')->get();
    }

    private function cakeFromRequest(Request $request): CakeProduct
    {
        $cake = $this->cakeProducts()->firstWhere('id', (int) $request->input('cake'));
        if ($cake === null) {
            throw ValidationException::withMessages(['cake' => __('This cake is not available.')]);
        }

        return $cake;
    }

    private function variantOf(?CakeProduct $cake): ?ProductVariant
    {
        return $cake?->product?->variants()->where('is_active', true)->orderBy('id')->with('product.cakeProfile')->first();
    }

    /**
     * Only whitelisted keys are kept; values are validated by the pricer against the admin options and library,
     * and the top design by CakeCanvas (photos must be the visitor's own uploads).
     *
     * @return array<string, mixed>
     */
    private function specFromRequest(Request $request, CakeProduct $cake): array
    {
        $hex = fn (string $key) => preg_match('/^#[0-9a-f]{6}$/i', (string) $request->input($key)) ? strtolower((string) $request->input($key)) : '';
        $code = fn (string $key) => preg_match('/^[a-z0-9_-]{1,40}$/', (string) $request->input($key)) ? (string) $request->input($key) : '';
        $canvas = json_decode((string) $request->input('canvas', ''), true);
        $cart = $this->carts->forRequest($request);
        $uploads = [];
        $layers = $this->canvas->sanitize(is_array($canvas) ? (array) ($canvas['layers'] ?? []) : [], function (string $uuid) use ($request, $cart, &$uploads): ?CakeUpload {
            $uploads[$uuid] ??= CakeUpload::query()->where('uuid', $uuid)->first();

            return $uploads[$uuid]?->ownedBy($request->user(), $cart?->token) ? $uploads[$uuid] : null;
        });

        return [
            'shape' => $code('shape'),
            'grams' => (int) $request->input('grams', 0),
            'tiers' => max(1, min(3, (int) $request->input('tiers', 1))),
            'flavour' => $code('flavour'),
            'filling' => $code('filling'),
            'cream' => $code('cream'),
            'diets' => array_values(array_intersect((array) $request->input('diets', []), ['eggless', 'sugar_free'])),
            'extras' => array_values(array_filter(array_map('strval', (array) $request->input('extras', [])), fn ($c) => preg_match('/^[a-z0-9_-]{1,40}$/', $c))),
            'palette' => $code('palette'),
            'base_colour' => $hex('base_colour'),
            'accent_colour' => $hex('accent_colour'),
            'drip' => $code('drip'),
            'drip_colour' => $hex('drip_colour'),
            'pattern' => $code('pattern'),
            'border_top' => $code('border_top'),
            'border_bottom' => $code('border_bottom'),
            'template' => $code('template'),
            'canvas' => ['layers' => $layers],
            'message' => mb_substr(trim((string) $request->input('message', '')), 0, 60),
            'note' => mb_substr(trim((string) $request->input('note', '')), 0, 500),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultSpec(CakeProduct $cake): array
    {
        $options = $this->pricer->optionsFor($cake)->groupBy('group');
        $palette = collect($this->library->forClient()['palette'] ?? [])->first(fn ($p) => $p['price_paisa'] === 0);

        return [
            'shape' => (string) $options->get('shape')?->first()?->code, 'grams' => $cake->min_grams, 'tiers' => 1,
            'flavour' => (string) $options->get('flavour')?->first()?->code, 'filling' => '', 'cream' => '',
            'diets' => [], 'extras' => [], 'palette' => $palette['code'] ?? '',
            'base_colour' => CakePricer::FREE_COLOURS[0], 'accent_colour' => CakePricer::FREE_COLOURS[0],
            'drip' => '', 'drip_colour' => '', 'pattern' => '', 'border_top' => '', 'border_bottom' => '', 'template' => '',
            'canvas' => ['layers' => []], 'message' => '', 'note' => '',
        ];
    }

    /**
     * A design the current visitor may edit: their own (signed in) or the guest cart's. Ordered designs are
     * only reachable for copying / sharing.
     */
    private function ownedDesign(Request $request, mixed $uuid, bool $includeOrdered = false): ?CakeDesign
    {
        if (! is_string($uuid) || ! Str::isUuid($uuid)) {
            return null;
        }
        $design = CakeDesign::query()->where('uuid', $uuid)
            ->when(! $includeOrdered, fn ($q) => $q->where('status', '!=', CakeDesign::ORDERED))->first();
        if ($design === null) {
            return null;
        }
        $user = $request->user();
        if ($user !== null) {
            return $design->user_id === $user->id ? $design : null;
        }
        $cart = $this->carts->forRequest($request);

        return $cart instanceof Cart && $design->owner_token_hash !== null && hash_equals($design->owner_token_hash, hash('sha256', $cart->token)) ? $design : null;
    }
}
