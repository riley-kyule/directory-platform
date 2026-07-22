<?php

namespace Tests\Feature;

use App\Enums\ProfileStatus;
use App\Models\Location;
use App\Models\Package;
use App\Models\Profile;
use App\Models\TaxonomyOption;
use App\Models\User;
use App\Services\PublicProfileListings;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProfileListingsTest extends TestCase
{
    use RefreshDatabase;

    private Location $city;

    private Location $neighbourhood;

    private TaxonomyOption $ethnicity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DirectoryDefaultsSeeder::class);

        $this->city = Location::query()->create([
            'country_code' => 'KE', 'type' => 'city', 'name' => 'Nairobi',
            'slug' => 'nairobi', 'full_slug' => 'nairobi', 'status' => 'published',
        ]);
        $this->neighbourhood = Location::query()->create([
            'parent_id' => $this->city->id, 'country_code' => 'KE', 'type' => 'neighbourhood',
            'name' => 'Westlands', 'slug' => 'westlands', 'full_slug' => 'nairobi/westlands',
            'status' => 'published',
        ]);
        $this->ethnicity = TaxonomyOption::query()->create([
            'type' => 'ethnicity', 'slug' => 'african', 'label' => 'African', 'is_active' => true,
        ]);
    }

    public function test_sections_are_package_ordered_and_new_profiles_are_repeated(): void
    {
        $vip = $this->activeProfile('Fresh VIP', 'vip', 200, now()->subDay());
        $premium = $this->activeProfile('Fresh Premium', 'premium', 100, now()->subDays(2));
        $basic = $this->activeProfile('Older Basic', 'basic', 50, now()->subDays(30));

        $sections = app(PublicProfileListings::class)->sections($this->city);

        $this->assertSame(['vip', 'premium', 'basic', 'new'], array_keys($sections));
        $this->assertTrue($sections['vip']->contains($vip));
        $this->assertTrue($sections['premium']->contains($premium));
        $this->assertTrue($sections['basic']->contains($basic));
        $this->assertTrue($sections['new']->contains($vip));
        $this->assertTrue($sections['new']->contains($premium));
        $this->assertFalse($sections['new']->contains($basic));
    }

    public function test_private_and_expired_profiles_never_enter_public_sections(): void
    {
        $private = $this->activeProfile('Private Profile', 'vip', 10, now());
        $private->update(['status' => ProfileStatus::Deactivated]);

        $expired = $this->activeProfile('Expired Profile', 'premium', 20, now());
        $expired->update(['expires_at' => now()->subMinute()]);

        $sections = app(PublicProfileListings::class)->sections($this->city);

        $this->assertFalse(collect($sections)->flatten()->contains($private));
        $this->assertFalse(collect($sections)->flatten()->contains($expired));
    }

    public function test_stored_rank_produces_stable_order_until_rotation(): void
    {
        $later = $this->activeProfile('Later VIP', 'vip', 900, now()->subDay());
        $earlier = $this->activeProfile('Earlier VIP', 'vip', 100, now()->subDay());
        $listings = app(PublicProfileListings::class);

        $firstRequest = $listings->forPackage('vip', $this->city)->pluck('id')->all();
        $secondRequest = $listings->forPackage('vip', $this->city)->pluck('id')->all();

        $this->assertSame([$earlier->id, $later->id], $firstRequest);
        $this->assertSame($firstRequest, $secondRequest);

        $this->artisan('profiles:rotate-listing-order')
            ->expectsOutput('Rotated 2 profile listing rank(s).')
            ->assertSuccessful();
        $this->assertGreaterThan(0, $earlier->refresh()->listing_rank);
    }

    private function activeProfile(string $name, string $packageCode, int $rank, mixed $activatedAt): Profile
    {
        $profile = Profile::query()->create([
            'owner_user_id' => User::factory()->create()->id,
            'display_name' => $name,
            'slug' => str($name)->slug(),
            'description' => 'A complete public profile description for listing tests.',
            'primary_location_id' => $this->city->id,
            'sublocation_id' => $this->neighbourhood->id,
            'gender_option_id' => TaxonomyOption::query()->ofType('gender')->firstOrFail()->id,
            'date_of_birth' => now()->subYears(25),
            'ethnicity_option_id' => $this->ethnicity->id,
            'build_option_id' => TaxonomyOption::query()->ofType('build')->firstOrFail()->id,
            'allows_incall' => true,
            'status' => ProfileStatus::Active,
            'last_activated_at' => $activatedAt,
            'expires_at' => now()->addMonth(),
            'listing_rank' => $rank,
        ]);
        $profile->packageAssignments()->create([
            'package_id' => Package::query()->where('code', $packageCode)->value('id'),
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
            'status' => 'active',
            'assigned_by' => $profile->owner_user_id,
            'assignment_source' => 'manual',
            'reason' => 'Test assignment.',
        ]);

        return $profile;
    }
}
