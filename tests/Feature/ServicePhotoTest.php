<?php

namespace Tests\Feature;

use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\User;
use App\Services\ServicePhotoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ServicePhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    #[DataProvider('validPhotos')]
    public function test_admins_can_create_services_with_optimized_photos(string $role, string $extension, int $width, int $height, int $expectedWidth, int $expectedHeight): void
    {
        $admin = User::factory()->create(['role' => $role]);

        $this->actingAs($admin)->post(route('admin.services.store'), [
            ...$this->payload(),
            'photo' => UploadedFile::fake()->image('treatment.'.$extension, $width, $height),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $service = Service::query()->where('name', 'Photo Treatment')->firstOrFail();
        $disk = Storage::disk('public');
        $disk->assertExists($service->photo_path);
        $info = getimagesizefromstring($disk->get($service->photo_path));
        $this->assertSame('image/webp', $info['mime']);
        $this->assertSame([$expectedWidth, $expectedHeight], [$info[0], $info[1]]);
        $this->assertStringStartsWith('service-photos/', $service->photo_path);
        $this->assertStringNotContainsString('treatment.', $service->photo_path);
    }

    public static function validPhotos(): array
    {
        return [
            'admin JPEG landscape' => [User::ROLE_ADMIN, 'jpg', 2400, 1600, 1200, 800],
            'super admin PNG without upscaling' => [User::ROLE_SUPER_ADMIN, 'png', 300, 200, 300, 200],
            'admin WebP portrait' => [User::ROLE_ADMIN, 'webp', 800, 1600, 600, 1200],
        ];
    }

    public function test_replacement_uses_a_new_filename_and_deletes_the_previous_upload(): void
    {
        $admin = User::factory()->admin()->create();
        $service = $this->serviceWithPhoto();
        $oldPath = $service->photo_path;

        $this->actingAs($admin)->patch(route('admin.services.update', $service), [
            ...$this->payload($service),
            'photo' => UploadedFile::fake()->image('replacement.png'),
            'remove_photo' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $newPath = $service->fresh()->photo_path;
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_removal_restores_the_sample_without_deleting_bundled_assets(): void
    {
        $admin = User::factory()->admin()->create();
        $service = $this->serviceWithPhoto(['name' => 'GAIA TOUCH', 'slug' => 'gaia-touch']);
        $oldPath = $service->photo_path;

        $this->actingAs($admin)->patch(route('admin.services.update', $service), [
            ...$this->payload($service),
            'remove_photo' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertNull($service->fresh()->photo_path);
        Storage::disk('public')->assertMissing($oldPath);
        $this->get('/')->assertOk()->assertSee('images/spa/casa-massage-720.webp');
        $this->assertFileExists(public_path('images/spa/casa-massage-720.webp'));
    }

    public function test_text_only_updates_and_renaming_preserve_the_uploaded_photo(): void
    {
        $admin = User::factory()->admin()->create();
        $service = $this->serviceWithPhoto();
        $path = $service->photo_path;

        $this->actingAs($admin)->patch(route('admin.services.update', $service), [
            ...$this->payload($service),
            'name' => 'Renamed Treatment',
            'photo_path' => 'images/spa/casa-massage-720.webp',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $updated = $service->fresh();
        $this->assertSame('renamed-treatment', $updated->slug);
        $this->assertSame($path, $updated->photo_path);
        Storage::disk('public')->assertExists($path);
    }

    #[DataProvider('invalidPhotos')]
    public function test_invalid_photos_do_not_change_services_or_existing_files(string $kind): void
    {
        $admin = User::factory()->admin()->create();
        $service = $this->serviceWithPhoto(['name' => 'Original Treatment']);
        $path = $service->photo_path;
        $count = Service::query()->count();

        foreach (['create', 'update'] as $operation) {
            $photo = match ($kind) {
                'text' => UploadedFile::fake()->create('photo.txt', 1, 'text/plain'),
                'disguised' => UploadedFile::fake()->createWithContent('photo.jpg', '<?php echo "invalid";'),
                'svg' => UploadedFile::fake()->createWithContent('photo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>'),
                'gif' => UploadedFile::fake()->image('photo.gif'),
                'size' => UploadedFile::fake()->image('photo.jpg')->size(2049),
                'width' => UploadedFile::fake()->image('photo.png', 4001, 5),
                'height' => UploadedFile::fake()->image('photo.jpg', 5, 4001),
            };
            $payload = [...$this->payload($service), 'name' => 'Invalid Change', 'photo' => $photo, 'remove_photo' => '1'];
            $this->actingAs($admin);
            $response = $operation === 'create'
                ? $this->post(route('admin.services.store'), $payload)
                : $this->patch(route('admin.services.update', $service), $payload);
            $response->assertRedirect()->assertSessionHasErrors('photo');

            $this->assertSame($count, Service::query()->count());
            $this->assertSame('Original Treatment', $service->fresh()->name);
            $this->assertSame($path, $service->fresh()->photo_path);
            $this->assertSame([$path], Storage::disk('public')->allFiles('service-photos'));
        }
    }

    public static function invalidPhotos(): array
    {
        return array_combine(
            ['text', 'disguised', 'svg', 'gif', 'size', 'width', 'height'],
            array_map(fn (string $kind): array => [$kind], ['text', 'disguised', 'svg', 'gif', 'size', 'width', 'height']),
        );
    }

    public function test_other_roles_cannot_upload_or_remove_service_photos(): void
    {
        $service = $this->serviceWithPhoto();
        $path = $service->photo_path;

        foreach ([User::ROLE_CUSTOMER, User::ROLE_STAFF, User::ROLE_RECEPTIONIST] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->post(route('admin.services.store'), [
                ...$this->payload(), 'photo' => UploadedFile::fake()->image('photo.jpg'),
            ])->assertForbidden();
            $this->actingAs($user)->patch(route('admin.services.update', $service), [
                ...$this->payload($service), 'remove_photo' => '1',
            ])->assertForbidden();
        }

        $this->assertSame($path, $service->fresh()->photo_path);
        $this->assertSame([$path], Storage::disk('public')->allFiles('service-photos'));
    }

    public function test_storage_write_failure_preserves_the_previous_photo_and_details(): void
    {
        $admin = User::factory()->admin()->create();
        $service = $this->serviceWithPhoto(['name' => 'Original Treatment']);
        $path = $service->photo_path;
        $disk = Storage::disk('public');
        $failingDisk = Mockery::mock($disk);
        $failingDisk->shouldReceive('put')->once()->andReturnFalse();
        Storage::shouldReceive('disk')->with('public')->andReturn($failingDisk);

        $this->actingAs($admin)->from(route('admin.services.edit', $service))->patch(route('admin.services.update', $service), [
            ...$this->payload($service),
            'name' => 'Failed Change',
            'photo' => UploadedFile::fake()->image('replacement.jpg'),
        ])->assertRedirect(route('admin.services.edit', $service))->assertSessionHasErrors('photo');

        $this->assertSame('Original Treatment', $service->fresh()->name);
        $this->assertSame($path, $service->fresh()->photo_path);
        $this->assertSame([$path], $disk->allFiles('service-photos'));
    }

    public function test_database_save_failure_removes_the_new_upload_and_preserves_the_old_record(): void
    {
        $admin = User::factory()->admin()->create();
        $service = $this->serviceWithPhoto(['name' => 'Original Treatment']);
        $path = $service->photo_path;
        Event::listen('eloquent.saving: '.Service::class, function (): void {
            throw new RuntimeException('Simulated service save failure.');
        });
        $this->withoutExceptionHandling();
        $caught = null;

        try {
            $this->actingAs($admin)->patch(route('admin.services.update', $service), [
                ...$this->payload($service), 'name' => 'Failed Change',
                'photo' => UploadedFile::fake()->image('replacement.png'),
            ]);
        } catch (RuntimeException $exception) {
            $caught = $exception;
        } finally {
            Event::forget('eloquent.saving: '.Service::class);
        }

        $this->assertSame('Simulated service save failure.', $caught?->getMessage());
        $this->assertSame('Original Treatment', $service->fresh()->name);
        $this->assertSame($path, $service->fresh()->photo_path);
        $this->assertSame([$path], Storage::disk('public')->allFiles('service-photos'));
    }

    public function test_photo_forms_show_preview_upload_controls_and_validation_errors(): void
    {
        $admin = User::factory()->admin()->create();
        $service = $this->serviceWithPhoto();
        $url = Storage::disk('public')->url($service->photo_path);

        $this->actingAs($admin)->get(route('admin.services.create'))->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)->assertSee('name="photo"', false);
        $this->get(route('admin.services.edit', $service))->assertOk()
            ->assertSee($url)->assertSee('name="remove_photo"', false);

        $this->from(route('admin.services.edit', $service))->patch(route('admin.services.update', $service), [
            ...$this->payload($service), 'name' => 'Retained Draft', 'remove_photo' => '1',
            'photo' => UploadedFile::fake()->create('invalid.txt', 1, 'text/plain'),
        ])->assertSessionHasErrors('photo');
        $this->get(route('admin.services.edit', $service))->assertOk()
            ->assertSee('Retained Draft')->assertSee($url)->assertSee('The photo field');
    }

    public function test_homepage_and_both_booking_views_share_uploaded_and_fallback_photos(): void
    {
        $customer = CustomerProfile::factory()->create();
        $uploaded = $this->serviceWithPhoto(['name' => 'Uploaded Treatment']);
        $url = Storage::disk('public')->url($uploaded->photo_path);
        Service::factory()->create(['name' => 'GAIA TOUCH', 'slug' => 'gaia-touch']);
        Service::factory()->create(['name' => 'Missing Photo Treatment', 'slug' => 'tethys-flow', 'photo_path' => 'service-photos/'.Str::uuid().'.webp']);
        Service::factory()->create(['name' => 'Custom Treatment', 'slug' => 'custom-treatment']);
        $inactive = $this->serviceWithPhoto(['name' => 'Inactive Treatment', 'is_active' => false]);

        $responses = [
            $this->get('/'),
            $this->actingAs($customer->user)->get(route('customer.appointments.create')),
            $this->get(route('customer.appointments.create', ['modal' => 1]), ['X-Casa-Panel' => '1']),
        ];

        foreach ($responses as $response) {
            $response->assertOk()->assertSee($url)
                ->assertSee('images/spa/casa-massage-720.webp')
                ->assertSee('images/spa/casa-hot-compress-720.webp')
                ->assertSee('images/spa/casa-towel-detail-720.webp')
                ->assertDontSee('Inactive Treatment')
                ->assertDontSee(Storage::disk('public')->url($inactive->photo_path));
        }
    }

    private function serviceWithPhoto(array $attributes = []): Service
    {
        $service = Service::factory()->create($attributes);

        return app(ServicePhotoService::class)->save($service, [], UploadedFile::fake()->image('original.jpg'));
    }

    private function payload(?Service $service = null): array
    {
        return [
            'name' => $service?->name ?? 'Photo Treatment',
            'description' => 'Treatment photo test.',
            'duration_minutes' => 60,
            'price' => 850,
            'is_active' => '1',
        ];
    }
}
