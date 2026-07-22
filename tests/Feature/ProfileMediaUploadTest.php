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

    public function test_owner_can_upload_valid_image_into_private_quarantine(): void
    {
        $response = $this->actingAs($this->owner)->post(route('profiles.media.store', $this->profile), [
            'image' => UploadedFile::fake()->image('portrait.jpg', 800, 1000),
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $image = $this->profile->images()->firstOrFail();
        $this->assertSame('quarantined', $image->status);
        Storage::disk('quarantine')->assertExists($image->storage_directory);
        Queue::assertPushed(ProcessProfileImage::class, fn ($job) => $job->profileImageId === $image->id);
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
        Queue::assertNothingPushed();
    }
}
