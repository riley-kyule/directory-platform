<?php

namespace Tests\Feature;

use App\Enums\ProfileStatus;
use App\Models\Location;
use App\Models\Package;
use App\Models\Profile;
use App\Models\TaxonomyOption;
use App\Models\User;
use App\Services\LocationInventoryService;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MicroLocationSeoTest extends TestCase
{
    use RefreshDatabase;

    private Location $city;

    private Location $neighbourhood;

    private Location $micro;

    private TaxonomyOption $ethnicity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DirectoryDefaultsSeeder::class);

        $this->city = $this->location('city', 'Nairobi', 'nairobi');
        $this->neighbourhood = $this->location('neighbourhood', 'Westlands', 'nairobi/westlands', $this->city);
        $this->micro = $this->location('landmark', 'Sarit Centre', 'nairobi/westlands/sarit-centre', $this->neighbourhood);
        DB::table('location_contents')->insert([
            'location_id' => $this->micro->id,
            'heading' => 'Sarit Centre Escorts',
            'intro_content' => 'Browse active profiles specifically associated with the Sarit Centre area in Westlands.',
            'seo_title' => 'Sarit Centre Escorts in Westlands, Nairobi',
            'meta_description' => 'Browse active provider profiles near Sarit Centre in Westlands, Nairobi, with current listing information.',
            'canonical_path' => '/nairobi/westlands/sarit-centre-escorts',
            'content_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->ethnicity = TaxonomyOption::query()->create([
            'type' => 'ethnicity',
            'slug' => 'african',
            'label' => 'African',
            'is_active' => true,
        ]);
    }

    public function test_micro_location_remains_noindex_at_five_profiles_and_indexes_at_six(): void
    {
        foreach (range(1, 5) as $number) {
            $this->activeProfile($number, $this->micro);
        }
        app(LocationInventoryService::class)->syncForProfile(Profile::query()->latest('id')->firstOrFail());

        $this->assertSame(5, $this->micro->refresh()->active_profile_count);
        $this->assertFalse($this->micro->is_indexable);
        $this->assertTrue(Location::query()
            ->where('slug', 'sarit-centre')
            ->whereHas('parent', fn ($query) => $query
                ->where('slug', 'westlands')
                ->whereHas('parent', fn ($query) => $query->where('slug', 'nairobi')))
            ->exists());
        $this->get(route('sitemaps.index'))->assertOk()->assertDontSee('locations-1.xml');
        $this->get('/nairobi/westlands/sarit-centre-escorts')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false)
            ->assertSee('Sarit Profile 1')
            ->assertSee('<link rel="canonical" href="http://localhost/nairobi/westlands/sarit-centre-escorts">', false)
            ->assertSeeInOrder(['Nairobi', 'Westlands', 'Sarit Centre']);

        $this->activeProfile(6, $this->micro);
        app(LocationInventoryService::class)->syncForProfile(Profile::query()->latest('id')->firstOrFail());

        $this->assertSame(6, $this->micro->refresh()->active_profile_count);
        $this->assertTrue($this->micro->is_indexable);
        $this->get(route('sitemaps.index'))->assertOk()->assertSee('locations-1.xml');
        $this->get(route('sitemaps.locations', 1))
            ->assertOk()
            ->assertSee(url('/nairobi/westlands/sarit-centre-escorts'), false);
        $this->get('/nairobi/westlands/sarit-centre-escorts')
            ->assertOk()
            ->assertSee('<meta name="robots" content="index,follow">', false)
            ->assertSee('Sarit Profile 6');
    }

    public function test_micro_location_page_excludes_profiles_from_the_parent_neighbourhood(): void
    {
        $this->activeProfile(1, $this->micro);
        $this->activeProfile(2, null);

        $this->get('/nairobi/westlands/sarit-centre-escorts')
            ->assertOk()
            ->assertSee('Sarit Profile 1')
            ->assertDontSee('Sarit Profile 2');
    }

    private function activeProfile(int $number, ?Location $micro): Profile
    {
        $owner = User::factory()->create();
        $profile = Profile::query()->create([
            'owner_user_id' => $owner->id,
            'display_name' => 'Sarit Profile '.$number,
            'slug' => 'sarit-profile-'.$number,
            'description' => 'A complete public provider profile associated with this location.',
            'primary_location_id' => $this->city->id,
            'sublocation_id' => $this->neighbourhood->id,
            'micro_location_id' => $micro?->id,
            'gender_option_id' => TaxonomyOption::query()->ofType('gender')->firstOrFail()->id,
            'date_of_birth' => now()->subYears(25),
            'ethnicity_option_id' => $this->ethnicity->id,
            'build_option_id' => TaxonomyOption::query()->ofType('build')->firstOrFail()->id,
            'allows_incall' => true,
            'status' => ProfileStatus::Active,
            'published_at' => now(),
            'last_activated_at' => now(),
            'expires_at' => now()->addMonth(),
            'listing_rank' => $number,
        ]);
        $profile->packageAssignments()->create([
            'package_id' => Package::query()->where('code', 'basic')->value('id'),
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
            'status' => 'active',
            'assigned_by' => $owner->id,
            'assignment_source' => 'manual',
            'reason' => 'Micro-location SEO test.',
        ]);

        return $profile;
    }

    private function location(string $type, string $name, string $fullSlug, ?Location $parent = null): Location
    {
        return Location::query()->create([
            'parent_id' => $parent?->id,
            'country_code' => 'KE',
            'type' => $type,
            'name' => $name,
            'slug' => str($name)->slug(),
            'full_slug' => $fullSlug,
            'status' => 'published',
        ]);
    }
}
