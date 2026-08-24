<?php

namespace Tests\Feature;

use App\Enums\ProfileStatus;
use App\Models\DirectorySetting;
use App\Models\Location;
use App\Models\Package;
use App\Models\Profile;
use App\Models\ProfileConversionDaily;
use App\Models\ProfileViewDaily;
use App\Models\Role;
use App\Models\TaxonomyOption;
use App\Models\User;
use App\Services\ModerationEnforcementService;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            'verification_status' => 'verified',
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
            ->assertSee('Call Jane Public')
            ->assertSee('Name, service or profile text')
            ->assertSee('Browse locations')
            ->assertSee('mobile-public-navigation');
    }

    public function test_public_layout_has_skip_navigation_and_accessible_interaction_targets(): void
    {
        $this->get(route('directory.home'))
            ->assertOk()
            ->assertSee('href="#main-content"', false)
            ->assertSee('<main id="main-content" tabindex="-1">', false)
            ->assertSee('aria-controls="mobile-public-navigation"', false)
            ->assertSee('aria-controls="advanced-search-filters"', false)
            ->assertSee('role="region" aria-label="Advanced search filters"', false);
    }

    public function test_profile_card_call_button_truncates_instead_of_wrapping(): void
    {
        // The call button's label includes the display name (`Call {name}`),
        // which can run much longer than the sibling "View profile" button —
        // both share a 50/50 flex row, so without truncate + min-w-0 the
        // longer label wraps to a second line while its sibling stays on one,
        // breaking the two buttons' alignment (worse the narrower the card).
        $this->profile->update(['display_name' => 'A Genuinely Long Display Name For This Provider']);

        $response = $this->get(route('directory.home'))->assertOk();
        $response->assertSee('min-w-0 flex-1 truncate rounded-xl border', false);
        $response->assertSee('min-w-0 flex-1 truncate rounded-xl bg-rose-500', false);
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
            ->assertSee('data-placement="profile_page"', false)
            ->assertSee('data-placement="mobile_bar"', false)
            ->assertSee('name="profile-view-endpoint"', false)
            ->assertSee('name="profile-view-id" content="'.$this->profile->public_id.'"', false)
            ->assertSee('Verification reviewed.')
            ->assertSee('Independent listing')
            ->assertDontSee($this->profile->date_of_birth->toDateString());
    }

    public function test_profile_meta_description_uses_editable_dynamic_profile_values(): void
    {
        DirectorySetting::query()->create([
            'key' => 'seo.profile_meta_template',
            'value' => '{profile_title} is a {nationality} {gender} escort from {locality} in {city}, {country}. {pronoun} offers {availability}.',
            'value_type' => 'string', 'group' => 'seo',
        ]);

        $this->get(route('directory.profiles.show', $this->profile->slug))
            ->assertOk()
            ->assertSee('<meta name="description" content="Jane Public is a Kenyan Woman escort from Westlands in Nairobi, Kenya. She offers in-calls and outcalls.">', false);
    }

    public function test_contact_events_are_stored_only_as_daily_aggregates(): void
    {
        $payload = [
            'profile' => $this->profile->public_id,
            'channel' => 'call',
            'placement' => 'profile_page',
        ];

        $this->post(route('conversion.contact'), $payload)->assertNoContent();
        $this->post(route('conversion.contact'), $payload)->assertNoContent();

        $this->assertDatabaseHas('profile_conversion_daily', [
            'event_date' => now()->toDateString(),
            'profile_id' => $this->profile->id,
            'channel' => 'call',
            'placement' => 'profile_page',
            'contact_count' => 2,
        ]);
        $this->assertDatabaseCount('profile_conversion_daily', 1);
    }

    public function test_profile_views_are_stored_as_anonymous_daily_aggregates(): void
    {
        $payload = ['profile' => $this->profile->public_id];

        $this->post(route('conversion.profile-view'), $payload)->assertNoContent();
        $this->post(route('conversion.profile-view'), $payload)->assertNoContent();

        $this->assertDatabaseHas('profile_view_daily', [
            'event_date' => now()->toDateString(),
            'profile_id' => $this->profile->id,
            'view_count' => 2,
        ]);
        $this->assertDatabaseCount('profile_view_daily', 1);
        $this->assertEmpty(array_intersect(
            Schema::getColumnListing('profile_view_daily'),
            ['user_id', 'session_id', 'ip_address', 'user_agent', 'fingerprint', 'referrer'],
        ));
    }

    public function test_profile_view_tracking_ignores_crawlers_and_private_profiles(): void
    {
        $payload = ['profile' => $this->profile->public_id];

        $this->withHeader('User-Agent', 'Googlebot/2.1')
            ->post(route('conversion.profile-view'), $payload)
            ->assertNoContent();
        $this->assertDatabaseCount('profile_view_daily', 0);

        $this->profile->update(['status' => ProfileStatus::Deactivated]);
        $this->withHeader('User-Agent', 'Mozilla/5.0')
            ->post(route('conversion.profile-view'), $payload)
            ->assertNotFound();
        $this->assertDatabaseCount('profile_view_daily', 0);
    }

    public function test_authorized_staff_can_see_aggregated_profile_conversion_counts(): void
    {
        ProfileViewDaily::query()->create([
            'event_date' => now()->toDateString(),
            'profile_id' => $this->profile->id,
            'view_count' => 10,
        ]);
        foreach (range(1, 2) as $_) {
            $this->post(route('conversion.contact'), [
                'profile' => $this->profile->public_id,
                'channel' => 'whatsapp',
                'placement' => 'mobile_bar',
            ])->assertNoContent();
        }

        $this->seed(AccessControlSeeder::class);
        $seo = User::factory()->create();
        $seo->roles()->attach(Role::query()->where('slug', 'seo')->firstOrFail());

        $this->actingAs($seo)
            ->get(route('seo.search-insights.index'))
            ->assertOk()
            ->assertSee('Jane Public')
            ->assertSee('WhatsApp')
            ->assertSee('Mobile Bar')
            ->assertSee('20.0% CTR')
            ->assertSee('Search-engine setup');
    }

    public function test_contact_tracking_rejects_invalid_events_and_private_profiles(): void
    {
        $this->post(route('conversion.contact'), [
            'profile' => $this->profile->public_id,
            'channel' => 'email',
            'placement' => 'profile_page',
        ])->assertSessionHasErrors('channel');

        $this->profile->update(['status' => ProfileStatus::Deactivated]);
        $this->post(route('conversion.contact'), [
            'profile' => $this->profile->public_id,
            'channel' => 'call',
            'placement' => 'profile_page',
        ])->assertNotFound();

        $this->assertDatabaseCount('profile_conversion_daily', 0);
    }

    public function test_old_conversion_aggregates_are_pruned_by_retention_policy(): void
    {
        ProfileConversionDaily::query()->create([
            'event_date' => now()->subDays(401)->toDateString(),
            'profile_id' => $this->profile->id,
            'channel' => 'call',
            'placement' => 'profile_page',
            'contact_count' => 5,
        ]);
        ProfileViewDaily::query()->create([
            'event_date' => now()->subDays(401)->toDateString(),
            'profile_id' => $this->profile->id,
            'view_count' => 20,
        ]);
        ProfileConversionDaily::query()->create([
            'event_date' => now()->toDateString(),
            'profile_id' => $this->profile->id,
            'channel' => 'whatsapp',
            'placement' => 'mobile_bar',
            'contact_count' => 2,
        ]);

        $this->artisan('conversion:prune')->assertSuccessful();

        $this->assertDatabaseMissing('profile_conversion_daily', ['channel' => 'call']);
        $this->assertDatabaseHas('profile_conversion_daily', ['channel' => 'whatsapp']);
        $this->assertDatabaseCount('profile_view_daily', 0);
    }

    public function test_public_profile_exposes_social_metadata_and_safe_entity_schema(): void
    {
        $response = $this->get(route('directory.profiles.show', $this->profile->slug));

        $response->assertOk()
            ->assertSee('<meta property="og:type" content="profile">', false)
            ->assertSee('<meta property="og:title" content="Jane Public — Westlands, Nairobi">', false)
            ->assertSee('<meta property="og:url" content="'.route('directory.profiles.show', $this->profile->slug).'">', false)
            ->assertSee('<meta name="twitter:card" content="summary">', false)
            ->assertSee('"@type":"ProfilePage"', false)
            ->assertSee('"@type":"Person"', false)
            ->assertSee('"addressLocality":"Westlands"', false)
            ->assertDontSee($this->profile->date_of_birth->toDateString());
    }

    public function test_profile_gallery_exposes_accessible_open_and_close_controls(): void
    {
        $this->addApprovedImage();

        $this->get(route('directory.profiles.show', $this->profile->slug))
            ->assertOk()
            ->assertSee('Open image 1 of 1')
            ->assertSee('Close image gallery')
            ->assertSee('role="dialog"', false)
            ->assertSee('<link rel="preload" as="image"', false)
            ->assertSee('imagesrcset="', false)
            ->assertSee('imagesizes="(min-width: 1024px) 58vw', false)
            ->assertSee('srcset="', false)
            ->assertSee('sizes="(min-width: 1024px) 58vw', false)
            ->assertSee('loading="eager" fetchpriority="high" decoding="async"', false)
            ->assertSee('@keydown.tab="trapGalleryTab($event)"', false);
    }

    public function test_listing_page_preloads_and_prioritizes_only_one_card_image(): void
    {
        $this->addApprovedImage();

        $response = $this->get(route('directory.home'))->assertOk();

        $this->assertSame(2, substr_count($response->getContent(), 'fetchpriority="high"'));
        $response->assertSee('imagesizes="(min-width: 1280px) 280px', false)
            ->assertSee('loading="eager" fetchpriority="high" decoding="async"', false)
            ->assertSee('srcset="', false);
    }

    public function test_public_pages_expose_consistent_open_graph_metadata(): void
    {
        $this->get('/nairobi/westlands-escorts')
            ->assertOk()
            ->assertSee('<meta property="og:site_name" content="Directory Platform">', false)
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<meta property="og:title" content="Westlands Escorts | Directory Platform">', false)
            ->assertSee('<meta property="og:url" content="http://localhost/nairobi/westlands-escorts">', false)
            ->assertSee('<meta name="twitter:description" content="Browse active and recently added provider profiles in Westlands, Nairobi.">', false);
    }

    public function test_search_is_noindex_and_matches_only_public_profile_text(): void
    {
        $this->get(route('directory.search', ['q' => 'Jane Public']))
            ->assertOk()
            ->assertSee('Jane Public')
            ->assertSee('<meta name="robots" content="noindex,follow">', false)
            ->assertSee('<link rel="canonical" href="http://localhost/search">', false)
            ->assertDontSee($this->profile->date_of_birth->toDateString());

        $this->profile->update(['status' => ProfileStatus::Expired]);
        $this->get(route('directory.search', ['q' => 'Jane Public']))
            ->assertOk()
            ->assertSee('No matching active profiles')
            ->assertDontSee('Call Jane Public');
    }

    public function test_search_filters_use_public_slugs_and_validate_location_hierarchy(): void
    {
        $service = TaxonomyOption::query()->ofType('service')->where('slug', 'massage')->firstOrFail();
        $this->profile->services()->attach($service);

        $this->get(route('directory.search', [
            'city' => 'nairobi',
            'neighbourhood' => 'westlands',
            'gender' => 'woman',
            'services' => ['massage'],
            'availability' => 'both',
        ]))->assertOk()->assertSee('Jane Public');

        $otherCity = Location::query()->create([
            'country_code' => 'KE', 'type' => 'city', 'name' => 'Mombasa', 'slug' => 'mombasa',
            'full_slug' => 'mombasa', 'status' => 'published',
        ]);
        Location::query()->create([
            'parent_id' => $otherCity->id, 'country_code' => 'KE', 'type' => 'neighbourhood',
            'name' => 'Nyali', 'slug' => 'nyali', 'full_slug' => 'mombasa/nyali', 'status' => 'published',
        ]);

        $this->get(route('directory.search', [
            'city' => 'nairobi',
            'neighbourhood' => 'nyali',
        ]))->assertSessionHasErrors('neighbourhood');

        $this->get(route('directory.search', ['sort' => 'newest']))
            ->assertOk()
            ->assertSee('Sorted by newest');
        $this->get(route('directory.search', ['sort' => 'unreviewed']))
            ->assertSessionHasErrors('sort');
    }

    public function test_search_query_is_escaped_when_rendered(): void
    {
        $this->get(route('directory.search', ['q' => '<script>alert(1)</script>']))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_location_alias_redirects_to_canonical_url(): void
    {
        $this->city->aliases()->create(['alias' => 'NBO', 'normalized_alias' => 'nbo']);
        $this->neighbourhood->aliases()->create(['alias' => 'West Lands', 'normalized_alias' => 'west-lands']);

        $this->get('/nbo-escorts')->assertStatus(301)->assertRedirect('/nairobi-escorts');
        $this->get('/nairobi/west-lands-escorts')->assertStatus(301)->assertRedirect('/nairobi/westlands-escorts');
        $this->get('/nbo/west-lands-escorts')->assertStatus(301)->assertRedirect('/nairobi/westlands-escorts');
    }

    public function test_empty_location_page_suggests_nearby_areas_with_inventory(): void
    {
        $this->neighbourhood->update(['active_profile_count' => 1]);
        Location::query()->create([
            'parent_id' => $this->city->id, 'country_code' => 'KE', 'type' => 'neighbourhood',
            'name' => 'Karen', 'slug' => 'karen', 'full_slug' => 'nairobi/karen',
            'status' => 'published', 'active_profile_count' => 0,
        ]);

        $this->get('/nairobi/karen-escorts')
            ->assertOk()
            ->assertSee('Nothing active here yet')
            ->assertSee('Westlands');
    }

    public function test_search_empty_state_suggests_clearing_filters_and_browsing_city(): void
    {
        $this->get(route('directory.search', ['city' => 'nairobi', 'q' => 'zzz-no-match-zzz']))
            ->assertOk()
            ->assertSee('No matching active profiles')
            ->assertSee('Clear all filters')
            ->assertSee('Browse all locations');

        $this->get(route('directory.search', ['city' => 'nairobi', 'neighbourhood' => 'westlands', 'q' => 'zzz-no-match-zzz']))
            ->assertOk()
            ->assertSee('Browse all of Nairobi');
    }

    public function test_guest_requests_are_served_from_cache_after_first_render(): void
    {
        $first = $this->get('/nairobi/westlands-escorts');
        $first->assertOk()->assertHeader('X-Page-Cache', 'miss');

        $second = $this->get('/nairobi/westlands-escorts');
        $second->assertOk()->assertHeader('X-Page-Cache', 'hit');
        $this->assertSame($first->getContent(), $second->getContent());
    }

    public function test_authenticated_requests_bypass_the_page_cache(): void
    {
        $this->get('/nairobi/westlands-escorts')->assertHeader('X-Page-Cache', 'miss');

        $this->actingAs(User::factory()->create())
            ->get('/nairobi/westlands-escorts')
            ->assertHeaderMissing('X-Page-Cache');
    }

    public function test_arbitrary_query_strings_cannot_create_public_page_cache_entries(): void
    {
        $this->get('/nairobi/westlands-escorts?campaign=one')
            ->assertOk()
            ->assertHeaderMissing('X-Page-Cache');
        $this->get('/nairobi/westlands-escorts?campaign=two')
            ->assertOk()
            ->assertHeaderMissing('X-Page-Cache');

        $this->get('/nairobi/westlands-escorts')->assertHeader('X-Page-Cache', 'miss');
        $this->get('/nairobi/westlands-escorts')->assertHeader('X-Page-Cache', 'hit');
    }

    public function test_banning_a_profile_immediately_purges_its_cached_pages(): void
    {
        $this->get(route('directory.profiles.show', $this->profile->slug))->assertOk()->assertSee('Jane Public');
        $this->get('/nairobi/westlands-escorts')->assertOk()->assertSee('Jane Public');

        app(ModerationEnforcementService::class)->ban($this->profile->fresh());

        $this->get(route('directory.profiles.show', $this->profile->slug))->assertNotFound();
        $this->get('/nairobi/westlands-escorts')->assertOk()->assertDontSee('Jane Public');
    }

    public function test_updating_location_content_purges_its_cached_page(): void
    {
        $this->get('/nairobi/westlands-escorts')
            ->assertHeader('X-Page-Cache', 'miss')
            ->assertSee('Original guide to active providers in Westlands.');
        $this->get('/nairobi/westlands-escorts')->assertHeader('X-Page-Cache', 'hit');

        $this->neighbourhood->content->update(['intro_content' => 'A freshly rewritten Westlands introduction.']);

        $this->get('/nairobi/westlands-escorts')
            ->assertHeader('X-Page-Cache', 'miss')
            ->assertSee('A freshly rewritten Westlands introduction.');
    }

    public function test_non_public_profile_returns_not_found(): void
    {
        $this->profile->update(['status' => ProfileStatus::Expired]);

        $this->get(route('directory.profiles.show', $this->profile->slug))->assertNotFound();
    }

    public function test_profile_page_renders_only_eligible_related_profiles(): void
    {
        $related = $this->profile->replicate();
        $related->public_id = null;
        $related->owner_user_id = User::factory()->create()->id;
        $related->display_name = 'Related Jane';
        $related->slug = 'related-jane';
        $related->listing_rank = 20;
        $related->save();
        $related->packageAssignments()->create([
            'package_id' => Package::query()->where('code', 'basic')->value('id'),
            'starts_at' => now(), 'expires_at' => now()->addMonth(), 'status' => 'active',
            'assigned_by' => $related->owner_user_id, 'assignment_source' => 'manual', 'reason' => 'Related profile test.',
        ]);

        $this->get(route('directory.profiles.show', $this->profile->slug))
            ->assertOk()
            ->assertSee('More profiles near Jane Public')
            ->assertSee('Related Jane');
    }

    private function addApprovedImage(): void
    {
        $this->profile->images()->create([
            'storage_directory' => 'profiles/test-image',
            'mime_type' => 'image/webp',
            'file_size' => 1000,
            'exact_hash' => hash('sha256', 'profile-gallery-test'),
            'width' => 800,
            'height' => 1000,
            'aspect_ratio' => 0.8,
            'status' => 'approved',
            'sort_order' => 1,
            'derivatives' => [
                'thumb' => ['file' => 'thumb.webp', 'width' => 320, 'height' => 400],
                'card' => ['file' => 'card.webp', 'width' => 640, 'height' => 800],
                'profile' => ['file' => 'profile.webp', 'width' => 800, 'height' => 1000],
            ],
        ]);
    }
}
