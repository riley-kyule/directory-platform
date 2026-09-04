<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\PackageRequestStatus;
use App\Enums\ProfileStatus;
use App\Enums\ProviderType;
use App\Jobs\ProcessProfileVideo;
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

class ProfileVideoUploadTest extends TestCase
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
            'display_name' => 'Video Profile',
            'slug' => 'video-profile',
            'description' => 'A complete profile used for secure video upload authorization testing.',
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

    public function test_owner_can_upload_a_video_into_private_quarantine(): void
    {
        $response = $this->actingAs($this->owner)->post(route('profiles.media.videos.store', $this->profile), [
            'video' => UploadedFile::fake()->create('clip.mp4', 4000, 'video/mp4'),
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $video = $this->profile->videos()->firstOrFail();
        $this->assertSame('quarantined', $video->status);
        Storage::disk('quarantine')->assertExists('videos/'.$this->profile->public_id.'/'.$video->public_id.'.upload');
        Queue::assertPushed(ProcessProfileVideo::class, fn ($job) => $job->profileVideoId === $video->id);
    }

    public function test_non_owner_cannot_upload_a_video(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('profiles.media.videos.store', $this->profile), [
                'video' => UploadedFile::fake()->create('clip.mp4', 1000, 'video/mp4'),
            ])->assertForbidden();
    }

    public function test_wrong_file_type_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->from(route('profiles.media.index', $this->profile))
            ->post(route('profiles.media.videos.store', $this->profile), [
                'video' => UploadedFile::fake()->create('notes.txt', 100, 'text/plain'),
            ])->assertSessionHasErrors('video');

        $this->assertDatabaseCount('profile_videos', 0);
    }

    public function test_basic_package_video_limit_is_enforced(): void
    {
        $this->profile->videos()->create([
            'storage_directory' => 'videos/x', 'sort_order' => 10, 'status' => 'pending_review',
            'mime_type' => 'video/mp4', 'file_size' => 1000, 'exact_hash' => hash('sha256', 'one'),
        ]);

        $this->actingAs($this->owner)->post(route('profiles.media.videos.store', $this->profile), [
            'video' => UploadedFile::fake()->create('second.mp4', 1000, 'video/mp4'),
        ])->assertStatus(422);

        $this->assertCount(1, $this->profile->videos);
        Queue::assertNothingPushed();
    }}
