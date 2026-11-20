<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Real-images-only workflow: private until a second staff member approves complete provenance;
 * only approved photos ever get public files or appear on the storefront.
 */
class MediaApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $uploader;

    private User $reviewer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->uploader = User::factory()->admin()->create();
        $this->reviewer = User::factory()->admin()->create();
        $this->product = Product::factory()->withFixedVariant()->create(['name' => 'Steel Wok 36 cm']);
    }

    /**
     * A real JPEG (random colour so every file is unique), optionally with a JPEG comment segment.
     */
    private function photo(int $width = 1200, int $height = 900, ?string $comment = null, string $name = 'wok.jpg'): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        imagefilledrectangle($image, 50, 50, 300, 300, (int) imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        ob_start();
        imagejpeg($image, null, 90);
        $jpeg = (string) ob_get_clean();
        if ($comment !== null) {
            $jpeg = "\xFF\xD8\xFF\xFE".pack('n', strlen($comment) + 2).$comment.substr($jpeg, 2);
        }

        return UploadedFile::fake()->createWithContent($name, $jpeg);
    }

    /**
     * @return array<string, mixed>
     */
    private function details(array $overrides = []): array
    {
        return $overrides + [
            'alt_text' => 'Steel wok 36 cm with wooden handle, front view',
            'alt_text_bn' => 'কাঠের হাতলসহ স্টিলের কড়াই',
            'source_type' => 'own_photography',
            'creator' => 'Baking House studio',
            'license_type' => 'Owned',
            'attested' => '1',
        ];
    }

    private function upload(?UploadedFile $file = null, array $overrides = []): ProductImage
    {
        $this->actingAsStaff($this->uploader)->post(route('admin.media.store', $this->product), ['photo' => $file ?? $this->photo()] + $this->details($overrides))
            ->assertSessionHasNoErrors();

        return ProductImage::query()->latest('id')->firstOrFail();
    }

    public function test_upload_is_private_reencoded_and_not_on_the_storefront(): void
    {
        $image = $this->upload($this->photo(comment: 'GPS 23.7808N 90.4152E camera serial X123'));

        $this->assertSame(ProductImage::STATUS_PENDING, $image->review_status);
        $this->assertSame('', $image->path);
        $this->assertSame([], Storage::disk('public')->allFiles(), 'No public file exists for an unapproved photo.');
        Storage::disk('local')->assertExists($image->original_path);
        $this->assertStringNotContainsString('GPS 23.7808N', Storage::disk('local')->get($image->original_path), 'Metadata is stripped by re-encoding.');
        $this->assertSame([1200, 900], [$image->width, $image->height]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'media.uploaded']);

        $this->get($this->product->url())->assertOk()->assertSee('Real product photo pending')->assertDontSee('<picture', false);
    }

    public function test_unsafe_fake_small_ai_declared_and_duplicate_files_are_refused(): void
    {
        $post = fn (UploadedFile $file) => $this->actingAsStaff($this->uploader)->post(route('admin.media.store', $this->product), ['photo' => $file] + $this->details());

        $post(UploadedFile::fake()->createWithContent('evil.jpg', '<?php echo "hi"; ?>'))->assertSessionHasErrors('photo');
        $post(UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'))->assertSessionHasErrors('photo');
        $post($this->photo(500, 500))->assertSessionHasErrors('photo');
        $post($this->photo(comment: 'Iptc4xmpExt:DigitalSourceType=http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia'))->assertSessionHasErrors('photo');
        $post($this->photo(comment: 'Made with Midjourney v6'))->assertSessionHasErrors('photo');

        $file = $this->photo();
        $post($file)->assertSessionHasNoErrors();
        $post(UploadedFile::fake()->createWithContent('again.jpg', (string) file_get_contents($file->getRealPath())))->assertSessionHasErrors('photo');

        $this->actingAsStaff($this->uploader)->post(route('admin.media.store', $this->product), ['photo' => $this->photo()] + $this->details(['attested' => null]))
            ->assertSessionHasErrors('attested');
        $this->assertSame(1, ProductImage::query()->count());
    }

    public function test_the_uploader_cannot_approve_their_own_photo(): void
    {
        $image = $this->upload();

        $this->actingAsStaff($this->uploader)->post(route('admin.media.approve', $image))->assertSessionHasErrors('review');
        $this->assertSame(ProductImage::STATUS_PENDING, $image->fresh()->review_status);
    }

    public function test_incomplete_or_expired_licences_cannot_be_approved(): void
    {
        $image = $this->upload(overrides: ['source_type' => 'licensed_stock', 'license_type' => 'Commercial licence']);
        $this->actingAsStaff($this->reviewer)->post(route('admin.media.approve', $image))->assertSessionHasErrors('review');

        $image->forceFill(['license_reference' => 'INV-2026-0042', 'license_expires_at' => now()->subDay()->toDateString()])->save();
        $this->actingAsStaff($this->reviewer)->post(route('admin.media.approve', $image))->assertSessionHasErrors('review');

        $image->forceFill(['license_expires_at' => now()->addYear()->toDateString()])->save();
        $this->actingAsStaff($this->reviewer)->post(route('admin.media.approve', $image))->assertSessionHasNoErrors();
        $this->assertTrue($image->fresh()->isApproved());
    }

    public function test_approval_publishes_responsive_renditions_and_the_storefront_shows_them(): void
    {
        $image = $this->upload();

        $this->actingAsStaff($this->reviewer)->post(route('admin.media.approve', $image), ['note' => 'Matches SKU'])->assertSessionHasNoErrors();
        $image->refresh();

        $this->assertTrue($image->isApproved());
        $this->assertTrue($image->is_primary, 'The first approved photo becomes the main photo.');
        $this->assertSame($this->reviewer->id, $image->reviewed_by);
        $formats = collect($image->renditions)->pluck('format')->unique()->values()->all();
        $this->assertContains('jpg', $formats);
        if (function_exists('imagewebp')) {
            $this->assertContains('webp', $formats);
        }
        $this->assertSame([400, 800, 1200], collect($image->renditions)->where('format', 'jpg')->pluck('width')->sort()->values()->all());
        foreach ($image->renditions as $rendition) {
            Storage::disk('public')->assertExists($rendition['path']);
        }
        $this->assertDatabaseHas('audit_logs', ['action' => 'media.approved', 'actor_id' => $this->reviewer->id]);

        $page = $this->get($this->product->url())->assertOk()
            ->assertSee('<picture', false)->assertSee('Steel wok 36 cm with wooden handle', false)->assertDontSee('Real product photo pending');
        if (function_exists('imagewebp')) {
            $page->assertSee('type="image/webp"', false);
        }
        $this->get('/shop')->assertOk()->assertSee('<picture', false);
    }

    public function test_rejection_needs_a_reason_and_never_publishes(): void
    {
        $image = $this->upload();

        $this->actingAsStaff($this->reviewer)->post(route('admin.media.reject', $image), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAsStaff($this->reviewer)->post(route('admin.media.reject', $image), ['reason' => 'Shows the 32 cm model, not 36 cm'])->assertSessionHasNoErrors();

        $this->assertSame(ProductImage::STATUS_REJECTED, $image->fresh()->review_status);
        $this->assertSame('Shows the 32 cm model, not 36 cm', $image->fresh()->review_note);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->actingAsStaff($this->reviewer)->post(route('admin.media.approve', $image))->assertSessionHasErrors('review');
    }

    public function test_withdrawing_removes_public_files_and_moves_the_main_photo(): void
    {
        $first = $this->upload();
        $second = $this->upload();
        $this->actingAsStaff($this->reviewer)->post(route('admin.media.approve', $first));
        $this->actingAsStaff($this->reviewer)->post(route('admin.media.approve', $second));
        $this->assertTrue($first->fresh()->is_primary);
        $paths = array_column($first->fresh()->renditions, 'path');

        $this->actingAsStaff($this->reviewer)->post(route('admin.media.withdraw', $first), ['reason' => 'Supplier withdrew permission'])->assertSessionHasNoErrors();

        $this->assertSame(ProductImage::STATUS_WITHDRAWN, $first->fresh()->review_status);
        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
        $this->assertTrue($second->fresh()->is_primary, 'The next approved photo becomes the main photo.');
    }

    public function test_changing_provenance_of_a_published_photo_sends_it_back_to_review(): void
    {
        $image = $this->upload();
        $this->actingAsStaff($this->reviewer)->post(route('admin.media.approve', $image));

        $this->actingAsStaff($this->uploader)->put(route('admin.media.update', $image), $this->details(['alt_text' => 'Steel wok 36 cm, side view']))->assertSessionHasNoErrors();
        $this->assertTrue($image->fresh()->isApproved(), 'Alt text edits keep the photo published.');

        $this->actingAsStaff($this->uploader)->put(route('admin.media.update', $image), $this->details(['creator' => 'Another photographer']))->assertSessionHasNoErrors();
        $this->assertSame(ProductImage::STATUS_PENDING, $image->fresh()->review_status);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_expired_licences_are_withdrawn_by_the_scheduled_command(): void
    {
        $image = $this->upload(overrides: ['license_expires_at' => now('Asia/Dhaka')->addDays(2)->toDateString()]);
        $this->actingAsStaff($this->reviewer)->post(route('admin.media.approve', $image));

        $this->travel(4)->days();
        $this->artisan('media:withdraw-expired')->expectsOutputToContain('Withdrew 1');

        $this->assertSame(ProductImage::STATUS_WITHDRAWN, $image->fresh()->review_status);
        $this->assertSame('Licence expired', $image->fresh()->review_note);
    }

    public function test_ordering_and_main_photo_rules(): void
    {
        $first = $this->upload();
        $second = $this->upload();

        $this->actingAsStaff($this->uploader)->post(route('admin.media.primary', $second))->assertSessionHasErrors('review');
        $this->actingAsStaff($this->uploader)->post(route('admin.media.move', $second), ['direction' => 'up']);
        $this->assertSame([$second->id, $first->id], ProductImage::query()->orderBy('sort_order')->pluck('id')->all());

        $this->actingAsStaff($this->reviewer)->post(route('admin.media.approve', $first));
        $this->actingAsStaff($this->reviewer)->post(route('admin.media.approve', $second));
        $this->actingAsStaff($this->uploader)->post(route('admin.media.primary', $second))->assertSessionHasNoErrors();
        $this->assertSame([$second->id], ProductImage::query()->where('is_primary', true)->pluck('id')->all());

        $this->actingAsStaff($this->uploader)->delete(route('admin.media.destroy', $second))->assertSessionHasErrors('review');
    }

    public function test_permissions_and_private_preview(): void
    {
        $image = $this->upload();
        $role = Role::create(['name' => 'photographer', 'label' => 'Photographer']);
        $role->permissions()->sync(Permission::query()->whereIn('name', ['admin.access', 'media.manage'])->pluck('id'));
        $photographer = User::factory()->withMfa()->withRole('photographer')->create();

        $this->actingAsStaff($photographer)->get(route('admin.media.product', $this->product))->assertOk();
        $this->actingAsStaff($photographer)->post(route('admin.media.approve', $image))->assertForbidden();
        $this->actingAsStaff($photographer)->get(route('admin.media.queue'))->assertForbidden();
        $this->actingAsStaff($photographer)->get(route('admin.media.preview', $image))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $this->actingAs(User::factory()->create())->get(route('admin.media.preview', $image))->assertForbidden();
        $this->actingAsStaff($this->reviewer)->get(route('admin.media.queue'))->assertOk()->assertSee('Steel Wok 36 cm');
    }
}
