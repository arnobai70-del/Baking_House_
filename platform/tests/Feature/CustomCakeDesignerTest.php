<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PricingMode;
use App\Enums\SellingUnit;
use App\Models\AuditLog;
use App\Models\CakeDesign;
use App\Models\CakeLibraryItem;
use App\Models\CakeOption;
use App\Models\CakeProduct;
use App\Models\CakeUpload;
use App\Models\Cart;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\PickupLocation;
use App\Models\Product;
use App\Models\User;
use App\Services\Cakes\CakeCanvas;
use App\Services\Cakes\CakeGeometry;
use App\Services\Cakes\CakeLibrary;
use App\Services\Cakes\CakePricer;
use App\Services\Cakes\CakeSettings;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutDetails;
use App\Services\Checkout\CheckoutService;
use App\Services\Orders\OrderPlacer;
use App\Services\Pricing\PricingContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\CommerceHelpers;
use Tests\TestCase;

/**
 * Custom cakes, Part 2: style options and the hand-made top priced on the server, the canvas sanitiser,
 * private photo uploads with rights and print approval, saved / shared / duplicated / re-ordered designs,
 * the admin design library, the baker job sheet and the checkout pickup fix.
 */
class CustomCakeDesignerTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    private CakeProduct $cake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-24 03:00:00', 'UTC'));
        Storage::fake('local');
        $this->withCredentials(); // send the cart cookie with JSON requests too, like a browser
        $this->zones();
        $this->cake = $this->cakeProduct();
        app(CakeSettings::class)->save(['cake.orders_open' => true, 'cake.daily_capacity' => 5]);
    }

    public function test_style_choices_and_top_design_are_priced_on_the_server(): void
    {
        $priced = $this->price([
            'palette' => 'pastel', 'base_colour' => '#f7d6e0', 'accent_colour' => '#c9e4de', 'drip' => 'chocolate',
            'pattern' => 'polka', 'border_top' => 'shell', 'border_bottom' => 'shell',
            'canvas' => ['layers' => [
                ['type' => 'text', 'text' => 'শুভ জন্মদিন', 'font' => 'sans'],
                ['type' => 'item', 'kind' => 'topper', 'item' => 'star'],
                ['type' => 'item', 'kind' => 'topper', 'item' => 'star'],
            ]],
        ]);

        $this->assertTrue($priced['ok'], (string) $priced['error']);
        $lines = collect($priced['breakdown'])->pluck('paisa', 'label')->all();
        $this->assertSame(20000, $lines['Chocolate drip']);
        $this->assertSame(5000, $lines['Custom message'], 'Text on the top is the message.');
        $this->assertSame(12000, $lines['Polka dots']);
        $this->assertSame(10000, $lines['Shell piping'], 'Same border top and bottom is one library item, charged once.');
        $this->assertSame(15000, $lines['Star topper'], 'A topper used twice is charged once.');
        $this->assertArrayNotHasKey('Custom cream colour', $lines, 'Palette colours are included.');
        $this->assertSame(120000 + 20000 + 5000 + 12000 + 10000 + 15000, $priced['line_paisa']);
    }

    public function test_colours_outside_the_palette_add_the_custom_colour_fee(): void
    {
        $lines = collect($this->price(['palette' => 'pastel', 'base_colour' => '#123456'])['breakdown'])->pluck('paisa', 'label');
        $this->assertSame(10000, $lines['Custom cream colour']);

        CakeOption::query()->where('group', 'colour')->delete();
        $this->assertFalse($this->price(['base_colour' => '#123456'])['ok']);
        $this->assertTrue($this->price(['base_colour' => '#fffaf3'])['ok'], 'Plain cream is always free.');
    }

    public function test_photos_add_the_print_and_items_must_exist_and_suit_the_shape(): void
    {
        $lines = collect($this->price(['canvas' => ['layers' => [['type' => 'image', 'upload' => (string) Str::uuid()]]]])['breakdown'])->pluck('paisa', 'label');
        $this->assertSame(30000, $lines['Edible photo print']);

        $this->assertFalse($this->price(['canvas' => ['layers' => [['type' => 'item', 'kind' => 'topper', 'item' => 'unknown']]]])['ok']);
        $this->assertFalse($this->price(['pattern' => 'scallop', 'shape' => 'heart'])['ok'], 'Scallops are only for round cakes.');
        $this->assertTrue($this->price(['pattern' => 'scallop', 'shape' => 'round'])['ok']);
        $this->assertFalse($this->price(['drip' => 'gold'])['ok']);
    }

    public function test_test_library_prices_block_ordering_outside_local_and_complexity_follows_items(): void
    {
        CakeOption::query()->update(['is_test' => false]);
        $this->assertFalse($this->price()['uses_test_prices']);
        $this->assertSame('complex', $this->price(['canvas' => ['layers' => [['type' => 'item', 'kind' => 'topper', 'item' => 'crown']]]])['complexity']);

        $this->app['env'] = 'production';
        $this->assertTrue($this->price()['ok']);
        $this->assertFalse($this->price(['border_top' => 'shell'])['ok'], 'A TEST library price blocks checkout in production.');
    }

    public function test_canvas_sanitiser_keeps_only_known_fields_and_the_visitors_own_photos(): void
    {
        $mine = CakeUpload::query()->forceCreate(['uuid' => (string) Str::uuid(), 'path' => 'x.jpg', 'width' => 2000, 'height' => 1500, 'bytes' => 1, 'sha256' => 'x', 'rights_confirmed_at' => now()]);
        $layers = app(CakeCanvas::class)->sanitize([
            ['type' => 'text', 'text' => "Hi\x07<b>there</b>", 'x' => 9, 'size' => 3, 'fill' => 'red', 'onclick' => 'alert(1)', 'curve' => 5],
            ['type' => 'image', 'upload' => $mine->uuid, 'crop' => ['x' => 0.9, 'w' => 0.9]],
            ['type' => 'image', 'upload' => (string) Str::uuid()],
            ['type' => 'item', 'kind' => 'script', 'item' => 'x'],
            ['type' => 'svg', 'd' => '<script>'],
        ], fn (string $uuid) => $uuid === $mine->uuid ? $mine : null);

        $this->assertCount(2, $layers, 'Foreign photos and unknown layer types are dropped.');
        $this->assertSame(['id', 'type', 'x', 'y', 'w', 'rotation', 'opacity', 'text', 'font', 'size', 'fill', 'stroke', 'stroke_width', 'curve'], array_keys($layers[0]));
        $this->assertSame(['Hi<b>there</b>', 1.2, 0.3, '#103b56', 1.0], [$layers[0]['text'], $layers[0]['x'], $layers[0]['size'], $layers[0]['fill'], $layers[0]['curve']]);
        $this->assertEqualsWithDelta(0.1, $layers[1]['crop']['w'], 0.0001, 'Crop stays inside the photo.');
        $this->assertCount(CakeCanvas::MAX_LAYERS, app(CakeCanvas::class)->sanitize(array_fill(0, 50, ['type' => 'text', 'text' => 'a'])));
    }

    public function test_print_quality_is_plain_pixels_per_printed_inch(): void
    {
        $this->assertSame(20.0, CakeGeometry::baseDiameterCm(1000));
        $this->assertSame(['dpi' => 254, 'level' => 'good'], CakeGeometry::printQuality(1000, 10));
        $this->assertSame(['dpi' => 127, 'level' => 'fair'], CakeGeometry::printQuality(500, 10));
        $this->assertSame(['dpi' => 50, 'level' => 'low'], CakeGeometry::printQuality(400, 20.32));
    }

    public function test_photo_upload_needs_the_rights_box_and_stays_private_to_its_owner(): void
    {
        [$cart, $cookie] = $this->guest();
        $this->withCookies($cookie)->postJson('/custom-cake/uploads', ['photo' => UploadedFile::fake()->image('me.jpg', 800, 600)])->assertUnprocessable()->assertJsonValidationErrors('rights');
        $this->withCookies($cookie)->postJson('/custom-cake/uploads', ['photo' => UploadedFile::fake()->image('tiny.jpg', 100, 100), 'rights' => 1])->assertUnprocessable();
        $this->withCookies($cookie)->postJson('/custom-cake/uploads', ['photo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'), 'rights' => 1])->assertUnprocessable();

        $response = $this->withCookies($cookie)->postJson('/custom-cake/uploads', ['photo' => UploadedFile::fake()->image('me.jpg', 1200, 900), 'rights' => 1])->assertCreated();
        $upload = CakeUpload::query()->sole();
        $this->assertSame([1200, 900, CakeUpload::PENDING], [$upload->width, $upload->height, $upload->review_status]);
        $this->assertNotNull($upload->rights_confirmed_at);
        Storage::disk('local')->assertExists($upload->path);
        $this->assertStringStartsWith('cakes/uploads/', $upload->path);
        $this->assertSame($response->json('url'), route('cakes.uploads.show', $upload->uuid));

        $this->withCookies($cookie)->get(route('cakes.uploads.show', $upload->uuid))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        [, $stranger] = $this->guest();
        $this->withCookies($stranger)->get(route('cakes.uploads.show', $upload->uuid))->assertNotFound();
        $this->get(route('cakes.uploads.show', $upload->uuid))->assertNotFound();
    }

    public function test_adding_a_cake_with_photos_needs_rights_and_saves_its_preview_pictures(): void
    {
        [$cart, $cookie] = $this->guest();
        $upload = $this->upload($cart);
        $form = $this->form(['canvas' => json_encode(['layers' => [['type' => 'image', 'upload' => $upload->uuid, 'w' => 0.5]]]), 'snapshot' => $this->png(), 'top_image' => $this->png()]);

        $this->withCookies($cookie)->post('/custom-cake', $form)->assertSessionHasErrors('rights');
        $this->withCookies($cookie)->post('/custom-cake', $form + ['rights' => 1])->assertRedirect(route('cart.show'));

        $design = CakeDesign::query()->sole();
        $this->assertSame($upload->uuid, $design->spec['canvas']['layers'][0]['upload']);
        $this->assertSame($design->id, $upload->fresh()->cake_design_id);
        $this->assertNotNull($design->snapshot_path);
        $this->assertNotNull($design->top_path);
        Storage::disk('local')->assertExists($design->snapshot_path);
        Storage::disk('local')->assertExists($design->top_path);
        $this->withCookies($cookie)->get(route('cakes.design-image', [$design->uuid, 'snapshot']))->assertOk();
        [, $stranger] = $this->guest();
        $this->withCookies($stranger)->get(route('cakes.design-image', [$design->uuid, 'snapshot']))->assertNotFound();
        $this->withCookies($cookie)->get('/cart')->assertOk()->assertSee(route('cakes.design-image', [$design->uuid, 'snapshot']), false);
    }

    public function test_broken_or_non_image_preview_data_is_ignored_not_stored(): void
    {
        [, $cookie] = $this->guest();
        $this->withCookies($cookie)->post('/custom-cake', $this->form([
            'snapshot' => 'data:image/png;base64,'.base64_encode('<?php echo 1; ?>'), 'top_image' => 'data:text/html;base64,PHNjcmlwdD4=',
        ]))->assertRedirect(route('cart.show'));

        $design = CakeDesign::query()->sole();
        $this->assertNull($design->snapshot_path);
        $this->assertNull($design->top_path);
        $this->assertSame([], Storage::disk('local')->allFiles('cakes/designs'));
    }

    public function test_someone_elses_photo_cannot_be_put_on_a_cake(): void
    {
        [$owner] = $this->guest();
        $upload = $this->upload($owner);
        [, $cookie] = $this->guest();

        $this->withCookies($cookie)->post('/custom-cake', $this->form(['canvas' => json_encode(['layers' => [['type' => 'image', 'upload' => $upload->uuid]]]), 'rights' => 1]));

        $this->assertSame([], CakeDesign::query()->sole()->spec['canvas']['layers']);
    }

    public function test_share_link_shows_the_design_without_photos_and_copies_start_fresh(): void
    {
        [$cart, $cookie] = $this->guest();
        $upload = $this->upload($cart);
        $this->withCookies($cookie)->post('/custom-cake', $this->form(['rights' => 1, 'name' => 'Ayaan 5', 'canvas' => json_encode(['layers' => [
            ['type' => 'image', 'upload' => $upload->uuid], ['type' => 'text', 'text' => 'Happy 5th Ayaan'],
        ]])]));
        $design = CakeDesign::query()->sole();

        [, $stranger] = $this->guest();
        $this->withCookies($stranger)->postJson(route('cakes.share', $design->uuid))->assertNotFound();
        $url = $this->withCookies($cookie)->postJson(route('cakes.share', $design->uuid))->assertOk()->json('url');

        $page = $this->get($url)->assertOk()->assertSee('A shared cake design')->assertSee('Happy 5th Ayaan')->assertSee('noindex', false);
        $this->assertStringNotContainsString($upload->uuid, $page->getContent(), 'Photos are never part of a shared design.');

        $this->withCookies($stranger)->post($url.'/use')->assertRedirect();
        $copy = CakeDesign::query()->where('copied_from_id', $design->id)->sole();
        $this->assertSame(['text'], array_column($copy->spec['canvas']['layers'], 'type'));
        $this->assertSame(CakeDesign::DRAFT, $copy->status);
        $this->assertNull($copy->scheduled_for);
    }

    public function test_signed_in_customers_save_designs_and_order_them_again(): void
    {
        $this->post('/custom-cake/save', $this->form())->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->post('/custom-cake/save', $this->form(['name' => 'Office party', 'date' => '', 'slot' => '']))->assertSessionHasNoErrors();
        $saved = CakeDesign::query()->sole();
        $this->assertSame([CakeDesign::DRAFT, $user->id, 'Office party'], [$saved->status, $saved->user_id, $saved->name]);
        $this->assertSame(0, Cart::query()->whereHas('items')->count(), 'Saving does not touch the cart.');

        $this->actingAs($user)->get('/account/cake-designs')->assertOk()->assertSee('Office party')->assertSee('Duplicate');
        $this->actingAs($user)->get('/account')->assertOk()->assertSee('My cake designs');

        $saved->forceFill(['status' => CakeDesign::ORDERED])->save();
        $this->actingAs($user)->get('/account/cake-designs')->assertSee('Order again');
        $this->actingAs($user)->post(route('cakes.duplicate', $saved->uuid))->assertRedirect();
        $again = CakeDesign::query()->where('copied_from_id', $saved->id)->sole();
        $this->assertSame([CakeDesign::DRAFT, $user->id], [$again->status, $again->user_id]);

        $other = User::factory()->create();
        $this->actingAs($other)->post(route('cakes.duplicate', $saved->uuid))->assertNotFound();
    }

    public function test_guest_photos_and_designs_move_to_the_account_at_sign_in(): void
    {
        [$cart] = $this->guest();
        $upload = $this->upload($cart);
        $user = User::factory()->create();

        app(CartService::class)->mergeGuestCart($cart->token, $user);

        $this->assertSame([$user->id, null], [$upload->fresh()->user_id, $upload->fresh()->owner_token_hash]);
    }

    public function test_admin_library_validates_drawings_resets_prices_to_test_and_only_the_owner_approves(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->superAdmin()->create();
        $payload = json_encode(['viewBox' => '0 0 100 100', 'paths' => [['d' => 'M10 10 L90 10 L50 90 Z', 'fill' => 'accent']]]);

        $this->staff($admin)->get('/admin/cakes/library')->assertOk()->assertSee('Star topper');
        $this->staff($admin)->get('/admin/cakes/library/new?kind=decoration')->assertOk();
        $this->staff($admin)->post('/admin/cakes/library', ['kind' => 'decoration', 'code' => 'tri', 'name' => 'Triangle', 'price' => '40', 'complexity' => 'simple', 'is_active' => 1,
            'payload' => json_encode(['viewBox' => '0 0 100 100', 'paths' => [['d' => 'M0 0 <script>alert(1)</script>', 'fill' => '#ffffff']]])])->assertSessionHasErrors('payload');
        $this->staff($admin)->post('/admin/cakes/library', ['kind' => 'decoration', 'code' => 'tri', 'name' => 'Triangle', 'tags' => 'Kids, পার্টি', 'price' => '40', 'complexity' => 'simple',
            'allowed_shapes' => ['round'], 'is_active' => 1, 'payload' => $payload])->assertSessionHasNoErrors();
        $item = CakeLibraryItem::query()->where('code', 'tri')->sole();
        $this->assertSame([true, 4000, ['kids', 'পার্টি'], ['round']], [$item->is_test, $item->price_paisa, $item->tags, $item->allowed_shapes]);

        $this->staff($admin)->post('/admin/cakes/library/approve', ['items' => [$item->id]])->assertForbidden();
        $this->staff($owner)->post('/admin/cakes/library/approve', ['items' => [$item->id]])->assertSessionHasNoErrors();
        $this->assertFalse($item->fresh()->is_test);
        $this->staff($admin)->put('/admin/cakes/library/'.$item->id, ['code' => 'tri', 'name' => 'Triangle', 'price' => '45', 'complexity' => 'simple', 'is_active' => 1, 'payload' => $payload])->assertSessionHasNoErrors();
        $this->assertTrue($item->fresh()->is_test, 'A new price needs approval again.');
        $this->assertTrue(AuditLog::query()->where('action', 'cake.library_prices_approved')->exists());
    }

    public function test_the_customer_sees_the_3d_snapshot_on_their_order_and_others_do_not(): void
    {
        $customer = User::factory()->create();
        $cart = app(CartService::class)->forUser($customer);
        $this->actingAs($customer)->post('/custom-cake', $this->form(['snapshot' => $this->png()]))->assertRedirect(route('cart.show'));
        $summary = app(CheckoutService::class)->summarize($cart, PricingContext::retail($customer->id), 'Dhaka', PaymentMethod::Bkash);
        $details = new CheckoutDetails('Rahim Uddin', '01712345678', null, 'House 1', 'Mirpur', 'Dhaka', null, PaymentMethod::Bkash, Str::random(40), $summary['fingerprint']);
        $order = app(OrderPlacer::class)->place($cart, PricingContext::retail($customer->id), $details, $customer)->order;
        $item = $order->items()->sole();
        $url = route('orders.cake-snapshot', [$order, $item]);

        $this->actingAs($customer)->get(route('orders.show', $order))->assertOk()->assertSee($url, false)->assertSee('Thu 26 Nov');
        $this->actingAs($customer)->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
        $this->get($url)->assertNotFound();
    }

    public function test_staff_review_prints_and_open_the_baker_job_sheet(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->admin()->create();
        [$cart, $cookie] = $this->guest();
        $upload = $this->upload($cart);
        $this->withCookies($cookie)->post('/custom-cake', $this->form(['rights' => 1, 'snapshot' => $this->png(), 'canvas' => json_encode(['layers' => [
            ['type' => 'image', 'upload' => $upload->uuid, 'w' => 0.5], ['type' => 'text', 'text' => 'শুভ জন্মদিন', 'font' => 'sans'], ['type' => 'item', 'kind' => 'topper', 'item' => 'star'],
        ]])]));
        $order = $this->placeCart($cart);
        $item = $order->items()->sole();

        $this->staff($admin)->get(route('admin.orders.show', $order))->assertOk()->assertSee('1 print(s) awaiting approval')->assertSee('Baker job sheet');
        $this->staff($admin)->get(route('admin.cakes.uploads.show', $upload))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('admin.cakes.uploads.show', $upload))->assertForbidden();

        $this->staff($admin)->post(route('admin.cakes.uploads.review', $upload), ['decision' => 'rejected'])->assertSessionHasErrors('note');
        $this->staff($admin)->post(route('admin.cakes.uploads.review', $upload), ['decision' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame([CakeUpload::APPROVED, $admin->id], [$upload->fresh()->review_status, $upload->fresh()->reviewed_by]);

        $this->staff($admin)->get(route('admin.orders.cake-sheet', [$order, $item]))->assertOk()
            ->assertSee('Baker job sheet')->assertSee('শুভ জন্মদিন')->assertSee('Star topper')->assertSee('Approved')->assertSee('dpi');
    }

    public function test_checkout_hides_the_pickup_box_until_pickup_is_chosen(): void
    {
        PickupLocation::query()->create(['name' => 'Main kitchen', 'address' => 'Road 1', 'is_active' => true]);
        [$cart, $cookie] = $this->guest();
        $this->withCookies($cookie)->post('/custom-cake', $this->form());

        $html = $this->withCookies($cookie)->get('/checkout')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<div data-pickup-box\s+hidden/', $html);
        $this->withCookies($cookie)->get('/checkout?fulfilment=pickup')->assertOk()->assertDontSee('data-pickup-box hidden', false);
    }

    public function test_designer_page_offers_six_steps_the_3d_preview_and_the_library(): void
    {
        $this->get('/custom-cake')->assertOk()
            ->assertSee('data-three=', false)->assertSee('vendor/three/three.module.min.js', false)->assertSee('vendor/konva/konva.min.js', false)
            ->assertSee('Design the top')->assertSee('Review')->assertSee('Pastel party')->assertSee('Shell piping')->assertSee('data-config=', false);
        $this->assertFileExists(public_path('vendor/three/three.module.min.js'));
        $this->assertFileExists(public_path('vendor/konva/konva.min.js'));
    }

    public function test_readiness_flags_test_library_prices(): void
    {
        CakeOption::query()->update(['is_test' => false]);
        $this->artisan('app:readiness', ['--target' => 'staging'])->expectsOutputToContain('library item(s) still use TEST prices')->assertFailed();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function price(array $overrides = []): array
    {
        $spec = array_merge(['shape' => 'round', 'grams' => 1000, 'tiers' => 1, 'flavour' => 'vanilla', 'base_colour' => '#fffaf3', 'accent_colour' => '#fffaf3'], $overrides);
        app(CakeLibrary::class)->forget();

        return app(CakePricer::class)->price($this->cake->product->variants()->sole()->load('product.cakeProfile'), $spec);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return array_merge(['cake' => $this->cake->id, 'shape' => 'round', 'grams' => 1000, 'tiers' => 1, 'flavour' => 'vanilla', 'date' => '2026-11-26', 'slot' => '14:00-17:00'], $overrides);
    }

    /**
     * @return array{0: Cart, 1: array<string, string>}
     */
    private function guest(): array
    {
        $cart = Cart::query()->create(['token' => Str::random(40)]);

        return [$cart, [CartService::COOKIE => $cart->token]];
    }

    private function upload(Cart $cart): CakeUpload
    {
        $this->withCookies([CartService::COOKIE => $cart->token])->postJson('/custom-cake/uploads', ['photo' => UploadedFile::fake()->image('me.jpg', 1200, 900), 'rights' => 1])->assertCreated();

        return CakeUpload::query()->latest('id')->firstOrFail();
    }

    /**
     * A real 96 × 96 PNG as a data URL, like the browser's 3D snapshot.
     */
    private function png(): string
    {
        $image = imagecreatetruecolor(96, 96);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 240, 200, 210));
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    private function staff(User $user): static
    {
        return $this->actingAs($user)->withSession(['mfa' => ['verified_at' => now()->timestamp]]);
    }

    private function placeCart(Cart $cart): Order
    {
        $summary = app(CheckoutService::class)->summarize($cart, PricingContext::retail(), 'Dhaka', PaymentMethod::Bkash);
        $details = new CheckoutDetails('Rahim Uddin', '01712345678', null, 'House 1', 'Mirpur', 'Dhaka', null, PaymentMethod::Bkash, Str::random(40), $summary['fingerprint']);

        return app(OrderPlacer::class)->place($cart, PricingContext::retail(), $details, null)->order;
    }

    private function cakeProduct(): CakeProduct
    {
        $product = Product::factory()->create(['slug' => 'custom-cake', 'name' => 'Custom Cake']);
        $product->variants()->delete();
        $product->variants()->create(['sku' => 'CAKE-1', 'name' => 'Custom cake', 'pricing_mode' => PricingMode::QuoteOnly, 'selling_unit' => SellingUnit::Piece, 'stock_quantity' => 0, 'is_active' => true]);
        $cake = CakeProduct::query()->create(['product_id' => $product->id, 'min_grams' => 500, 'max_grams' => 3000, 'step_grams' => 500, 'max_tiers' => 2]);

        foreach ([
            ['flavour', 'vanilla', 'per_kg', 1200, 'simple'], ['shape', 'round', 'flat', 0, 'simple'], ['shape', 'heart', 'flat', 250, 'standard'],
            ['message', 'standard', 'flat', 50, 'simple'], ['print', 'standard', 'flat', 300, 'standard'], ['drip', 'chocolate', 'flat', 200, 'standard'],
            ['colour', 'custom', 'flat', 100, 'simple'], ['tier', '2', 'flat', 1500, 'complex'],
        ] as $i => [$group, $code, $type, $taka, $complexity]) {
            CakeOption::query()->create(['group' => $group, 'code' => $code, 'name' => match ($group) {
                'message' => 'Custom message', 'print' => 'Edible photo print', 'drip' => 'Chocolate drip', 'colour' => 'Custom cream colour', default => Str::headline($code)
            }, 'price_type' => $type, 'price_paisa' => $taka * 100, 'is_test' => true, 'complexity' => $complexity, 'is_active' => true, 'sort_order' => $i]);
        }

        $library = app(CakeLibrary::class);
        $star = ['viewBox' => '0 0 100 100', 'paths' => [['d' => 'M50 5 L61 38 L96 38 L68 59 L79 93 L50 72 L21 93 L32 59 L4 38 L39 38 Z', 'fill' => 'accent']]];
        foreach ([
            ['palette', 'pastel', 'Pastel party', 0, 'simple', null, ['colours' => ['#f7d6e0', '#c9e4de', '#fbe7a1']]],
            ['pattern', 'polka', 'Polka dots', 120, 'standard', null, ['style' => 'dots', 'colour' => 'accent']],
            ['pattern', 'scallop', 'Scallops', 150, 'complex', ['round'], ['style' => 'scallop', 'colour' => 'accent']],
            ['border', 'shell', 'Shell piping', 100, 'standard', null, ['style' => 'shell', 'colour' => 'accent']],
            ['font', 'sans', 'Clear sans', 0, 'simple', null, ['family' => 'Noto Sans', 'stack' => '"Noto Sans", sans-serif', 'weight' => 700, 'scripts' => ['en', 'bn']]],
            ['topper', 'star', 'Star topper', 150, 'standard', null, $star],
            ['topper', 'crown', 'Crown topper', 250, 'complex', null, $star],
        ] as $i => [$kind, $code, $name, $taka, $complexity, $shapes, $payload]) {
            CakeLibraryItem::query()->create(['kind' => $kind, 'code' => $code, 'name' => $name, 'price_paisa' => $taka * 100, 'is_test' => $kind !== 'palette' && $kind !== 'font' && $kind !== 'topper',
                'complexity' => $complexity, 'allowed_shapes' => $shapes, 'payload' => $library->normalisePayload($kind, $payload), 'is_active' => true, 'sort_order' => $i]);
        }
        DeliveryZone::forDistrict('Dhaka')->forceFill(['cake_delivery_enabled' => true, 'cake_charge_paisa' => 15000, 'cake_tiered_allowed' => true])->save();

        return $cake;
    }
}
