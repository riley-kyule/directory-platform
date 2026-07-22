<?php

namespace Tests\Feature;

use App\Enums\ProfileStatus;
use App\Models\Agency;
use App\Models\Location;
use App\Models\Package;
use App\Models\Profile;
use App\Models\TaxonomyOption;
use App\Models\User;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAgencyDirectoryTest extends TestCase
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
            'country_code' => 'KE', 'type' => 'city', 'name' => 'Nairobi', 'slug' => 'nairobi',
            'full_slug' => 'nairobi', 'status' => 'published',
        ]);
        $this->neighbourhood = Location::query()->create([
            'parent_id' => $this->city->id, 'country_code' => 'KE', 'type' => 'neighbourhood',
            'name' => 'Westlands', 'slug' => 'westlands', 'full_slug' => 'nairobi/westlands', 'status' => 'published',
        ]);
        $this->ethnicity = TaxonomyOption::query()->create([
            'type' => 'ethnicity', 'slug' => 'african', 'label' => 'African', 'is_active' => true,
        ]);
    }

    public function test_agency_index_only_lists_active_agencies_with_public_profiles(): void
    {
        $publicAgency = $this->agency('Public Agency', 'active');
        $publicProfile = $this->profile('Agency Jane');
        $this->attach($publicAgency, $publicProfile);

        $draftAgency = $this->agency('Draft Agency', 'draft');
        $this->attach($draftAgency, $this->profile('Draft Agency Profile'));
        $this->agency('Empty Active Agency', 'active');

        $this->get(route('directory.agencies.index'))
            ->assertOk()
            ->assertSee('Public Agency')
            ->assertSee('1 active profile')
            ->assertDontSee('Draft Agency')
            ->assertDontSee('Empty Active Agency');
    }

    public function test_agency_page_excludes_expired_and_unassigned_profiles(): void
    {
        $agency = $this->agency('Nairobi Agency', 'active');
        $active = $this->profile('Active Jane');
        $expired = $this->profile('Expired Jane', now()->subMinute());
        $unassigned = $this->profile('Former Jane');
        $this->attach($agency, $active);
        $this->attach($agency, $expired);
        $this->attach($agency, $unassigned, now());

        $this->get(route('directory.agencies.show', $agency->slug))
            ->assertOk()
            ->assertSee('Active Jane')
            ->assertDontSee('Expired Jane')
            ->assertDontSee('Former Jane');
    }

    public function test_agency_without_any_public_profiles_returns_not_found(): void
    {
        $agency = $this->agency('Private Agency', 'active');
        $this->attach($agency, $this->profile('Private Jane', now()->subDay()));

        $this->get(route('directory.agencies.show', $agency->slug))->assertNotFound();
    }

    private function agency(string $name, string $status): Agency
    {
        return Agency::query()->create([
            'owner_user_id' => User::factory()->create()->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'description' => 'A professionally managed directory agency.',
            'status' => $status,
        ]);
    }

    private function profile(string $name, $expiresAt = null): Profile
    {
        $expiresAt ??= now()->addMonth();
        $profile = Profile::query()->create([
            'display_name' => $name, 'slug' => str($name)->slug(),
            'description' => 'A complete agency provider profile.',
            'primary_location_id' => $this->city->id, 'sublocation_id' => $this->neighbourhood->id,
            'gender_option_id' => TaxonomyOption::query()->ofType('gender')->firstOrFail()->id,
            'date_of_birth' => now()->subYears(25), 'ethnicity_option_id' => $this->ethnicity->id,
            'build_option_id' => TaxonomyOption::query()->ofType('build')->firstOrFail()->id,
            'allows_incall' => true, 'status' => ProfileStatus::Active,
            'last_activated_at' => now(), 'expires_at' => $expiresAt, 'listing_rank' => 10,
        ]);
        $profile->packageAssignments()->create([
            'package_id' => Package::query()->where('code', 'vip')->value('id'),
            'starts_at' => now(), 'expires_at' => $expiresAt, 'status' => 'active',
            'assigned_by' => User::factory()->create()->id, 'assignment_source' => 'manual', 'reason' => 'Test assignment.',
        ]);

        return $profile;
    }

    private function attach(Agency $agency, Profile $profile, $unassignedAt = null): void
    {
        $agency->profiles()->attach($profile, [
            'assigned_by' => $agency->owner_user_id,
            'assigned_at' => now(),
            'unassigned_at' => $unassignedAt,
        ]);
    }
}
