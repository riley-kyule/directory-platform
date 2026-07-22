<?php

namespace Tests\Feature;

use App\Enums\ProfileStatus;
use App\Models\Location;
use App\Models\Package;
use App\Models\Profile;
use App\Models\TaxonomyOption;
use App\Models\User;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicDirectoryPagesTest extends TestCase
{
    use RefreshDatabase;

    private Location $city;

    private Location $neighbourhood;

    private Profile $profile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DirectoryDefaultsSeeder::class);

        $this->city = Location::query()->create([
            'country_code' => 'KE', 'type' => 'city', 'name' => 'Nairobi', 'slug' => 'nairobi',
            'full_slug' => 'nairobi', 'status' => 'published', 'is_indexable' => true,
        ]);
        $this->neighbourhood = Location::query()->create([
            'parent_id' => $this->city->id, 'country_code' => 'KE', 'type' => 'neighbourhood',
            'name' => 'Westlands', 'slug' => 'westlands', 'full_slug' => 'nairobi/westlands',
            'status' => 'published', 'is_indexable' => false,
        ]);
        DB::table('location_contents')->insert([
            'location_id' => $this->neighbourhood->id,
            'intro_content' => 'Original guide to active providers in Westlands.',
            'seo_title' => 'Westlands Escorts | Directory Platform',
            'meta_description' => 'Browse active and recently added provider profiles in Westlands, Nairobi.',
            'canonical_path' => '/nairobi/westlands-escorts',
            'content_status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $ethnicity = TaxonomyOption::query()->create([
            'type' => 'ethnicity', 'slug' => 'african', 'label' => 'African', 'is_active' => true,
        ]);
        $owner = User::factory()->create(['last_seen_at' => now()]);
        $this->profile = Profile::query()->create([
            'owner_user_id' => $owner->id,
            'display_name' => 'Jane Public', 'slug' => 'jane-public',
            'description' => 'A complete and welcoming public provider biography.',
            'primary_location_id' => $this->city->id,
            'sublocation_id' => $this->neighbourhood->id,
            'gender_option_id' => TaxonomyOption::query()->ofType('gender')->where('slug', 'woman')->value('id'),
            'date_of_birth' => now()->subYears(25),
            'ethnicity_option_id' => $ethnicity->id,
            'build_option_id' => TaxonomyOption::query()->ofType('build')->firstOrFail()->id,
            'allows_incall' => true, 'allows_outcall' => true,
            'status' => ProfileStatus::Active,
            'last_activated_at' => now()->subDay(), 'expires_at' => now()->addMonth(), 'listing_rank' => 10,
        ]);
        $this->profile->packageAssignments()->create([
            'package_id' => Package::query()->where('code', 'vip')->value('id'),
            'starts_at' => now(), 'expires_at' => now()->addMonth(), 'status' => 'active',
            'assigned_by' => $owner->id, 'assignment_source' => 'manual', 'reason' => 'Approved for test.',
        ]);
        $this->profile->contacts()->createMany([
            ['type' => 'call', 'normalized_value' => '+254700000000', 'display_value' => '+254 700 000 000', 'sort_order' => 10],
            ['type' => 'sms', 'normalized_value' => '+254700000000', 'display_value' => '+254 700 000 000', 'sort_order' => 20],
            ['type' => 'whatsapp', 'normalized_value' => '+254700000000', 'display_value' => '+254 700 000 000', 'sort_order' => 30],
            ['type' => 'telegram_username', 'normalized_value' => 'janepublic', 'display_value' => '@janepublic', 'sort_order' => 40],
        ]);
    }

    public function test_homepage_renders_required_sections_in_order(): void
    {
        $this->get(route('directory.home'))
            ->assertOk()
            ->assertSeeInOrder(['VIP Escorts', 'Premium Escorts', 'Basic Escorts', 'New Escorts'])
            ->assertSee('Jane Public')
            ->assertSee('Call Jane Public');
    }

    public function test_location_url_uses_approved_seo_data_and_inventory_robots_rule(): void
    {
        $this->get('/nairobi/westlands-escorts')
            ->assertOk()
            ->assertSee('<title>Westlands Escorts | Directory Platform</title>', false)
            ->assertSee('<meta name="robots" content="noindex,follow">', false)
            ->assertSee('<link rel="canonical" href="http://localhost/nairobi/westlands-escorts">', false)
            ->assertSee('Original guide to active providers in Westlands.');
    }

    public function test_public_profile_has_all_contact_actions_without_exposing_date_of_birth(): void
    {
        $response = $this->get(route('directory.profiles.show', $this->profile->slug));

        $response->assertOk()
            ->assertSee('About Jane Public')
            ->assertSee('tel:+254700000000', false)
            ->assertSee('sms:+254700000000', false)
            ->assertSee('https://wa.me/254700000000', false)
            ->assertSee('https://t.me/janepublic', false)
            ->assertDontSee($this->profile->date_of_birth->toDateString());
    }

    public function test_non_public_profile_returns_not_found(): void
    {
        $this->profile->update(['status' => ProfileStatus::Expired]);

        $this->get(route('directory.profiles.show', $this->profile->slug))->assertNotFound();
    }
}
