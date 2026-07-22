<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\ProfileStatus;
use App\Enums\ProviderType;
use App\Jobs\ProcessProfileImage;
use App\Models\Location;
use App\Models\Profile;
use App\Models\TaxonomyOption;
use App\Models\User;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileImageProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_quarantined_image_is_reencoded_and_published_as_complete_webp_set(): void
    {
        Storage::fake('quarantine');
        Storage::fake('media_staging');
        Storage::fake('profile_media');
        $this->seed(DirectoryDefaultsSeeder::class);

        $profile = $this->profile();
        $bytes = $this->jpeg(800, 1000);
        $publicId = '12345678-1234-4234-9234-123456789abc';
        $quarantinePath = $profile->public_id.'/'.$publicId.'.upload';
        Storage::disk('quarantine')->put($quarantinePath, $bytes);

        $image = $profile->images()->create([
            'public_id' => $publicId,
            'storage_directory' => $quarantinePath,
            'sort_order' => 10,
            'status' => 'quarantined',
            'width' => 800,
            'height' => 1000,
            'aspect_ratio' => 0.8,
            'mime_type' => 'image/jpeg',
            'file_size' => strlen($bytes),
            'exact_hash' => hash('sha256', $bytes),
        ]);

        (new ProcessProfileImage($image->id))->handle();

        $image->refresh();
        $this->assertSame('pending_review', $image->status);
        $this->assertSame('image/webp', $image->mime_type);
        $this->assertNotNull($image->perceptual_hash);
        $this->assertCount(4, $image->derivatives);
        Storage::disk('quarantine')->assertMissing($quarantinePath);

        foreach (['thumb-320.webp', 'card-640.webp', 'profile-960.webp', 'full-1280.webp'] as $filename) {
            Storage::disk('profile_media')->assertExists($image->storage_directory.'/'.$filename);
            $this->assertSame('image/webp', (new \finfo(FILEINFO_MIME_TYPE))->file(
                Storage::disk('profile_media')->path($image->storage_directory.'/'.$filename),
            ));
        }
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
            'display_name' => 'Media Profile',
            'slug' => 'media-profile',
            'description' => 'A valid profile used to verify secure image processing behavior.',
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

    private function jpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, 160, 90, 120);
        imagefill($image, 0, 0, $color);
        ob_start();
        imagejpeg($image, null, 90);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
