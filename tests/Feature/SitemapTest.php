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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    private Location $city;

    private Profile $profile;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DirectoryDefaultsSeeder::class);
        $this->city = Location::query()->create([
            'country_code' => 'KE', 'type' => 'city', 'name' => 'Nairobi', 'slug' => 'nairobi',
            'full_slug' => 'nairobi', 'status' => 'published', 'is_indexable' => true,
        ]);
        $neighbourhood = Location::query()->create([
            'parent_id' => $this->city->id, 'country_code' => 'KE', 'type' => 'neighbourhood',
            'name' => 'Westlands', 'slug' => 'westlands', 'full_slug' => 'nairobi/westlands',
            'status' => 'published', 'is_indexable' => false,
        ]);
        DB::table('location_contents')->insert([
            'location_id' => $this->city->id, 'heading' => 'Nairobi Escorts',
            'intro_content' => str_repeat('Approved original Nairobi directory content. ', 4),
            'seo_title' => 'Nairobi Escorts',
            'meta_description' => 'Browse active independent provider profiles throughout Nairobi and its neighbourhoods.',
            'canonical_path' => '/nairobi-escorts', 'content_status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $ethnicity = TaxonomyOption::query()->create([
            'type' => 'ethnicity', 'slug' => 'african', 'label' => 'African', 'is_active' => true,
        ]);
        $agencyOwner = User::factory()->create();
        $this->agency = Agency::query()->create([
            'owner_user_id' => $agencyOwner->id, 'name' => 'Sitemap Agency', 'slug' => 'sitemap-agency',
            'description' => 'An active agency included in public discovery.', 'status' => 'active',
        ]);
        $this->profile = Profile::query()->create([
            'display_name' => 'Sitemap Jane', 'slug' => 'sitemap-jane',
            'description' => 'An active profile included in public discovery.',
            'primary_location_id' => $this->city->id, 'sublocation_id' => $neighbourhood->id,
            'gender_option_id' => TaxonomyOption::query()->ofType('gender')->firstOrFail()->id,
            'date_of_birth' => now()->subYears(25), 'ethnicity_option_id' => $ethnicity->id,
            'build_option_id' => TaxonomyOption::query()->ofType('build')->firstOrFail()->id,
            'allows_incall' => true, 'status' => ProfileStatus::Active,
            'last_activated_at' => now(), 'expires_at' => now()->addMonth(), 'listing_rank' => 10,
        ]);
        $this->profile->packageAssignments()->create([
            'package_id' => Package::query()->where('code', 'vip')->value('id'),
            'starts_at' => now(), 'expires_at' => now()->addMonth(), 'status' => 'active',
            'assigned_by' => $agencyOwner->id, 'assignment_source' => 'manual', 'reason' => 'Sitemap test.',
        ]);
        $this->agency->profiles()->attach($this->profile, [
            'assigned_by' => $agencyOwner->id, 'assigned_at' => now(),
        ]);
    }

    public function test_sitemap_index_discovers_each_non_empty_public_sitemap(): void
    {
        $this->get(route('sitemaps.index'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('sitemaps.editorial'), false)
            ->assertSee(route('sitemaps.locations', 1), false)
            ->assertSee(route('sitemaps.profiles', 1), false)
            ->assertSee(route('sitemaps.agencies', 1), false);
    }

    public function test_child_sitemaps_only_contain_canonical_public_urls(): void
    {
        $this->get(route('sitemaps.locations', 1))
            ->assertOk()
            ->assertSee(url('/nairobi-escorts'), false)
            ->assertDontSee('westlands-escorts');
        $this->get(route('sitemaps.profiles', 1))
            ->assertOk()
            ->assertSee(route('directory.profiles.show', 'sitemap-jane'), false);
        $this->get(route('sitemaps.agencies', 1))
            ->assertOk()
            ->assertSee(route('directory.agencies.show', 'sitemap-agency'), false);
        $this->get(route('sitemaps.profiles', 2))->assertNotFound();
    }

    public function test_private_or_package_expired_profiles_disappear_from_sitemap_discovery(): void
    {
        $this->profile->packageAssignments()->update(['expires_at' => now()->subMinute()]);

        $this->get(route('sitemaps.index'))
            ->assertOk()
            ->assertDontSee('profiles-1.xml')
            ->assertDontSee('agencies-1.xml');
        $this->get(route('directory.profiles.show', $this->profile->slug))->assertNotFound();
    }

    public function test_robots_file_points_to_the_absolute_sitemap_index(): void
    {
        $this->get(route('robots'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Sitemap: '.route('sitemaps.index'));
    }
}
