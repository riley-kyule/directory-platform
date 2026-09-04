<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\ProfileStatus;
use App\Enums\ProviderType;
use App\Jobs\ProcessProfileVideo;
use App\Models\Location;
use App\Models\Profile;
use App\Models\ProfileVideo;
use App\Models\TaxonomyOption;
use App\Models\User;
use App\Services\ProfileImageVisibility;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProfileVideoProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('quarantine');
        Storage::fake('media_staging');
        Storage::fake('media_review');
        Storage::fake('profile_media');
        $this->seed(DirectoryDefaultsSeeder::class);
    }

    public function test_valid_mp4_is_moved_to_pending_review(): void
    {
        $profile = $this->profile();
        $video = $this->quarantinedVideo($profile, $this->mp4Bytes());

        (new ProcessProfileVideo($video->id))->handle();

        $video->refresh();
        $this->assertSame('pending_review', $video->status);
        Storage::disk('quarantine')->assertMissing('videos/'.$profile->public_id.'/'.$video->public_id.'.upload');
        Storage::disk('media_review')->assertExists($video->storage_directory.'/'.$video->sourceFilename());
        Storage::disk('profile_media')->assertMissing($video->storage_directory.'/'.$video->sourceFilename());
    }

    public function test_processing_on_a_live_profile_publishes_automatically(): void
    {
        $profile = $this->profile();
        $profile->update(['status' => ProfileStatus::Active, 'expires_at' => now()->addDays(30)]);
        $video = $this->quarantinedVideo($profile, $this->mp4Bytes());

        (new ProcessProfileVideo($video->id))->handle();

        $video->refresh();
        $this->assertSame('approved', $video->status);
        Storage::disk('media_review')->assertMissing($video->storage_directory.'/'.$video->sourceFilename());
        Storage::disk('profile_media')->assertExists($video->storage_directory.'/'.$video->sourceFilename());
    }

    public function test_junk_bytes_are_rejected_with_a_reason(): void
    {
        $profile = $this->profile();
        $video = $this->quarantinedVideo($profile, 'this is definitely not a video file at all');

        $job = new ProcessProfileVideo($video->id);
        try {
            $job->handle();
            $this->fail('Expected the job to reject the junk upload.');
        } catch (\Throwable $exception) {
            $job->failed($exception);
        }

        $video->refresh();
        $this->assertSame('rejected', $video->status);
        $this->assertNotNull($video->processing_error);
        Storage::disk('quarantine')->assertMissing('videos/'.$profile->public_id.'/'.$video->public_id.'.upload');
    }

    public function test_publish_and_unpublish_move_the_directory(): void
    {
        $profile = $this->profile();
        $video = $this->quarantinedVideo($profile, $this->mp4Bytes());
        (new ProcessProfileVideo($video->id))->handle();

        $profile->update(['status' => ProfileStatus::Active, 'expires_at' => now()->addDays(30)]);
        app(ProfileImageVisibility::class)->publishVideos($profile);

        $video->refresh();
        $this->assertSame('approved', $video->status);
        Storage::disk('profile_media')->assertExists($video->storage_directory.'/'.$video->sourceFilename());
        Storage::disk('media_review')->assertMissing($video->storage_directory.'/'.$video->sourceFilename());

        app(ProfileImageVisibility::class)->unpublishVideos($profile);
        $video->refresh();
        $this->assertSame('pending_review', $video->status);
        Storage::disk('media_review')->assertExists($video->storage_directory.'/'.$video->sourceFilename());
    }

    private function quarantinedVideo(Profile $profile, string $bytes): ProfileVideo
    {
        $video = $profile->videos()->create([
            'public_id' => (string) Str::uuid(),
            'storage_directory' => 'pending',
            'sort_order' => 10,
            'status' => 'quarantined',
            'mime_type' => 'video/mp4',
            'file_size' => strlen($bytes),
            'file_extension' => 'mp4',
            'exact_hash' => hash('sha256', $bytes),
        ]);
        Storage::disk('quarantine')->put('videos/'.$profile->public_id.'/'.$video->public_id.'.upload', $bytes);

        return $video;
    }

    private function mp4Bytes(): string
    {
        return "\x00\x00\x00\x20ftypisom\x00\x00\x02\x00isomiso2avc1mp41".str_repeat("\x00", 512);
    }

    private function profile(): Profile
    {
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
        $owner = User::factory()->create([
            'account_type' => AccountType::Provider,
            'provider_type' => ProviderType::Independent,
        ]);

        return Profile::query()->create([
            'owner_user_id' => $owner->id,
            'display_name' => 'Video Processing Profile',
            'slug' => 'video-processing-profile',
            'description' => 'A valid profile used to verify secure video processing behavior.',
            'primary_location_id' => $location->id,
            'sublocation_id' => $sublocation->id,
            'gender_option_id' => TaxonomyOption::query()->ofType('gender')->firstOrFail()->id,
            'date_of_birth' => now()->subYears(25),
            'ethnicity_option_id' => $ethnicity->id,
            'build_option_id' => TaxonomyOption::query()->ofType('build')->firstOrFail()->id,
            'allows_incall' => true,
            'status' => ProfileStatus::Draft,
        ]);
    }
}
