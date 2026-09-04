<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\PackageRequestStatus;
use App\Enums\ProfileStatus;
use App\Enums\ProviderType;
use App\Jobs\ProcessProfileImage;
use App\Models\Location;
use App\Models\Package;
use App\Models\Profile;
use App\Models\TaxonomyOption;
use App\Models\User;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProfileMediaUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Profile $profile;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('quarantine');
        Storage::fake('media_staging');
        Storage::fake('media_review');
        Storage::fake('profile_media');
        Queue::fake();
        $this->seed(DirectoryDefaultsSeeder::class);

        $location = Location::query()->create([
            'country_code' => 'KE', 'type' => 'city', 'name' => 'Nairobi',
            'slug' => 'nairobi', 'full_slug' => 'nairobi', 'status' => 'published',
        ]);
        $sublocation = Location::query()->create([
            'parent_id' => $location->id, 'country_code' => 'KE', 'type' => 'area',
            'name' => 'Westlands', 'slug' => 'westlands', 'full_slug' => 'nairobi/westlands', 'status' => 'published',
        ]);
        $ethnicity = TaxonomyOption::query()->create([
            'type' => 'ethnicity', 'slug' => 'african', 'label' => 'African', 'is_active' => true,
        ]);
        $this->owner = User::factory()->create([
            'account_type' => AccountType::Provider,
            'provider_type' => ProviderType::Independent,
        ]);
        $this->profile = Profile::query()->create([
            'owner_user_id' => $this->owner->id,
            'display_name' => 'Upload Profile',
            'slug' => 'upload-profile',
            'description' => 'A complete profile used for secure upload authorization testing.',
            'primary_location_id' => $location->id,
            'sublocation_id' => $sublocation->id,
            'gender_option_id' => TaxonomyOption::query()->ofType('gender')->firstOrFail()->id,
            'date_of_birth' => now()->subYears(25),
            'ethnicity_option_id' => $ethnicity->id,
            'build_option_id' => TaxonomyOption::query()->ofType('build')->firstOrFail()->id,
            'allows_incall' => true,
            'status' => ProfileStatus::Draft,
        ]);
        $this->profile->packageRequests()->create([
            'requested_package_id' => Package::query()->where('code', 'basic')->firstOrFail()->id,
            'status' => PackageRequestStatus::Pending,
            'requested_by' => $this->owner->id,
            'requested_at' => now(),
        ]);
    }

    public function test_owner_can_open_the_media_manager(): void
    {
        $this->actingAs($this->owner)->get(route('profiles.media.index', $this->profile))
            ->assertOk()
            ->assertSee('Photos')
            ->assertSee('Videos');
    }

    public function test_media_manager_updates_itself_while_a_video_is_processing(): void
    {
        $this->profile->videos()->create([
            'public_id' => (string) Str::uuid(),
            'storage_directory' => 'videos/'.$this->profile->public_id.'/processing.upload',
            'sort_order' => 10,
            'status' => 'processing',
            'mime_type' => 'video/mp4',
            'file_size' => 1000,
            'file_extension' => 'mp4',
            'exact_hash' => hash('sha256', 'processing-video'),
        ]);

        $this->actingAs($this->owner)->get(route('profiles.media.index', $this->profile))
            ->assertOk()
            ->assertSee('A video is still processing')
            ->assertSee('pollForVideo: true', false);
    }

    public function test_owner_can_upload_a_photo_and_it_processes_immediately(): void
    {
        $response = $this->actingAs($this->owner)->post(route('profiles.media.store', $this->profile), [
            'image' => UploadedFile::fake()->image('portrait.jpg', 800, 1000),
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $image = $this->profile->images()->firstOrFail();
        // Draft profile: the photo is fully processed and waiting for activation.
        $this->assertSame('pending_review', $image->status);
        $this->assertNotEmpty($image->derivatives);
        Storage::disk('media_review')->assertExists($image->storage_directory.'/card-640.webp');
        Queue::assertNotPushed(ProcessProfileImage::class);
    }

    public function test_undersized_image_is_rejected_before_quarantine(): void
    {
        $this->actingAs($this->owner)
            ->from(route('profiles.media.index', $this->profile))
            ->post(route('profiles.media.store', $this->profile), [
                'image' => UploadedFile::fake()->image('small.jpg', 300, 300),
            ])
            ->assertSessionHasErrors('image');

        $this->assertDatabaseCount('profile_images', 0);
    }

    public function test_unrelated_subscriber_cannot_view_or_upload_profile_media(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get(route('profiles.media.index', $this->profile))->assertForbidden();
        $this->actingAs($member)->post(route('profiles.media.store', $this->profile), [
            'image' => UploadedFile::fake()->image('portrait.jpg', 800, 1000),
        ])->assertForbidden();
    }

    public function test_basic_package_cannot_exceed_five_images(): void
    {
        for ($position = 1; $position <= 5; $position++) {
            $this->profile->images()->create([
                'storage_directory' => 'test/'.$position,
                'sort_order' => $position * 10,
                'status' => 'pending_review',
                'width' => 800,
                'height' => 1000,
                'aspect_ratio' => 0.8,
                'mime_type' => 'image/webp',
                'file_size' => 1000,
                'exact_hash' => hash('sha256', 'image-'.$position),
            ]);
        }

        $this->actingAs($this->owner)->post(route('profiles.media.store', $this->profile), [
            'image' => UploadedFile::fake()->image('sixth.jpg', 800, 1000),
        ])->assertStatus(422);

        $this->assertCount(5, $this->profile->images);
    }

    public function test_json_upload_returns_a_json_status(): void
    {
        $response = $this->actingAs($this->owner)->postJson(route('profiles.media.store', $this->profile), [
            'image' => UploadedFile::fake()->image('portrait.jpg', 800, 1000),
        ]);

        $response->assertOk()->assertJsonStructure(['status']);
        $this->assertSame('pending_review', $this->profile->images()->firstOrFail()->status);
    }

    public function test_invalid_json_upload_returns_structured_validation_errors(): void
    {
        $this->actingAs($this->owner)->postJson(route('profiles.media.store', $this->profile), [
            'image' => UploadedFile::fake()->image('small.jpg', 300, 300),
        ])->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    public function test_media_uploads_are_rate_limited_per_actor_and_profile(): void
    {
        // 40 hits are allowed per minute; a wrong file type still consumes one
        // (throttle runs before validation), which keeps this fast.
        for ($attempt = 1; $attempt <= 40; $attempt++) {
            $this->actingAs($this->owner)->post(route('profiles.media.store', $this->profile), [
                'image' => UploadedFile::fake()->create('not-image-'.$attempt.'.txt', 4),
            ])->assertRedirect();
        }

        $this->actingAs($this->owner)->post(route('profiles.media.store', $this->profile), [
            'image' => UploadedFile::fake()->image('blocked.jpg', 800, 1000),
        ])->assertTooManyRequests();
    }

    public function test_owner_can_retry_a_rejected_image(): void
    {
        $image = $this->profile->images()->create([
            'public_id' => (string) Str::uuid(),
            'storage_directory' => $this->profile->public_id.'/pending.upload',
            'sort_order' => 10,
            'status' => 'rejected',
            'width' => 800, 'height' => 1000, 'aspect_ratio' => 0.8,
            'mime_type' => 'image/jpeg', 'file_size' => 1000,
            'exact_hash' => hash('sha256', 'retry'),
            'processing_error' => 'Something went wrong.',
        ]);
        Storage::disk('quarantine')->put(
            $this->profile->public_id.'/'.$image->public_id.'.upload',
            UploadedFile::fake()->image('retry.jpg', 800, 1000)->get(),
        );

        $this->actingAs($this->owner)->post(route('profiles.media.retry', [$this->profile, $image]))
            ->assertRedirect()->assertSessionHasNoErrors();

        // Retry now processes in the request too.
        $this->assertSame('pending_review', $image->refresh()->status);
        $this->assertNull($image->processing_error);
    }

    public function test_retry_without_the_original_upload_asks_for_a_re_upload(): void
    {
        $image = $this->profile->images()->create([
            'public_id' => (string) Str::uuid(),
            'storage_directory' => $this->profile->public_id.'/gone.upload',
            'sort_order' => 10, 'status' => 'rejected',
            'width' => 800, 'height' => 1000, 'aspect_ratio' => 0.8,
            'mime_type' => 'image/jpeg', 'file_size' => 1000,
            'exact_hash' => hash('sha256', 'gone'),
        ]);

        $this->actingAs($this->owner)
            ->from(route('profiles.media.index', $this->profile))
            ->post(route('profiles.media.retry', [$this->profile, $image]))
            ->assertSessionHasErrors('image');

        $this->assertSame('rejected', $image->refresh()->status);
    }
}
