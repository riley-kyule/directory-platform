<?php

namespace Tests\Feature;

use App\Models\DirectorySetting;
use App\Models\Location;
use App\Models\Profile;
use App\Models\ProfileDetail;
use App\Models\Role;
use App\Models\TaxonomyOption;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoDirectoryConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_subscriber_cannot_access_directory_configuration(): void
    {
        $subscriber = User::factory()->create();

        $this->actingAs($subscriber)->get(route('seo.locations.index'))->assertForbidden();
        $this->actingAs($subscriber)->get(route('seo.taxonomies.index'))->assertForbidden();
        $this->actingAs($subscriber)->get(route('seo.pages.homepage.edit'))->assertForbidden();
        $this->actingAs($subscriber)->get(route('seo.pages.agencies.edit'))->assertForbidden();
    }

    public function test_seo_user_can_open_managed_homepage_editor(): void
    {
        $response = $this->actingAs($this->staff('seo'))
            ->get(route('seo.pages.homepage.edit'))
            ->assertOk()
            ->assertSee('Homepage content')
            ->assertSee('Bottom SEO content');

        $this->assertSame(2, substr_count($response->getContent(), 'data-html-editor'));
    }

    public function test_every_managed_bottom_seo_field_uses_the_html_editor(): void
    {
        $seo = $this->staff('seo');
        $this->actingAs($seo)->post(route('seo.locations.store'), $this->locationData());
        $location = Location::query()->firstOrFail();

        foreach ([
            route('seo.pages.homepage.edit'),
            route('seo.pages.agencies.edit'),
            route('seo.locations.create'),
            route('seo.locations.content.edit', $location),
        ] as $url) {
            $response = $this->actingAs($seo)->get($url)->assertOk();
            $this->assertSame(2, substr_count($response->getContent(), 'data-html-editor'), $url);
            $response->assertSee('name="bottom_content" data-html-editor', false);
        }
    }

    public function test_seo_user_can_edit_profile_meta_template_and_ordered_public_menu(): void
    {
        $seo = $this->staff('seo');

        $this->actingAs($seo)->patch(route('seo.site-presentation.update'), [
            'profile_meta_template' => '{profile_title} is a {gender} in {locality}, {city}. {pronoun} welcomes enquiries.',
            'navigation_items' => [
                ['label' => 'Agencies first', 'url' => '/agencies'],
                ['label' => 'Find a profile', 'url' => '/search'],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(
            [['label' => 'Agencies first', 'url' => '/agencies'], ['label' => 'Find a profile', 'url' => '/search']],
            json_decode(DirectorySetting::query()->findOrFail('navigation.primary_items')->value, true),
        );
        $this->assertDatabaseHas('audit_logs', ['action' => 'seo.site-presentation-update']);
        $this->get(route('directory.home'))->assertSeeInOrder(['Agencies first', 'Find a profile']);
    }

    public function test_subscriber_cannot_edit_profile_meta_or_menu(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('seo.site-presentation.edit'))
            ->assertForbidden();
    }

    public function test_locations_can_be_searched_by_name_or_path(): void
    {
        $seo = $this->staff('seo');
        $this->actingAs($seo)->post(route('seo.locations.store'), $this->locationData());
        $this->actingAs($seo)->post(route('seo.locations.store'), $this->locationData([
            'name' => 'Mombasa', 'seo_title' => 'Mombasa Escorts and Independent Profiles',
        ]));
        session()->forget('status');

        $this->actingAs($seo)->get(route('seo.locations.index', ['q' => 'Nairobi']))
            ->assertOk()
            ->assertSee('Search by location name, URL path or country code')
            ->assertSee('Nairobi')
            ->assertDontSee('Mombasa');
    }

    public function test_seo_user_can_open_locations_and_taxonomies_pages(): void
    {
        $seo = $this->staff('seo');
        $this->actingAs($seo)->post(route('seo.locations.store'), $this->locationData());

        $this->actingAs($seo)->get(route('seo.locations.index'))
            ->assertOk()
            ->assertSee('Nairobi');

        $this->actingAs($seo)->post(route('seo.taxonomies.store'), [
            'type' => 'ethnicity',
            'label' => 'African',
            'country_code' => 'ke',
            'sort_order' => 10,
            'is_active' => '1',
        ]);

        $this->actingAs($seo)->get(route('seo.taxonomies.index'))
            ->assertOk()
            ->assertSee('African');

        $this->actingAs($seo)->get(route('seo.pages.agencies.edit'))
            ->assertOk()
            ->assertSee('Agency directory content');
    }

    public function test_seo_user_can_publish_location_only_with_complete_seo_data(): void
    {
        $seo = $this->staff('seo');

        $this->actingAs($seo)->post(route('seo.locations.store'), [
            'country_code' => 'ke',
            'type' => 'city',
            'name' => 'Nairobi',
            'status' => 'published',
        ])->assertSessionHasErrors(['intro_content', 'seo_title', 'meta_description']);

        $this->actingAs($seo)->post(route('seo.locations.store'), $this->locationData())
            ->assertRedirect(route('seo.locations.index'))
            ->assertSessionHasNoErrors();

        $location = Location::query()->firstOrFail();
        $this->assertSame('KE', $location->country_code);
        $this->assertSame('nairobi', $location->full_slug);
        $this->assertFalse($location->is_indexable);
        $this->assertDatabaseHas('location_contents', [
            'location_id' => $location->id,
            'canonical_path' => '/nairobi-escorts',
            'content_status' => 'approved',
            'reviewed_by' => $seo->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'locations.create', 'target_id' => $location->id]);
    }

    public function test_nested_location_receives_canonical_hierarchical_path(): void
    {
        $seo = $this->staff('seo');
        $this->actingAs($seo)->post(route('seo.locations.store'), $this->locationData());
        $parent = Location::query()->firstOrFail();

        $this->actingAs($seo)->post(route('seo.locations.store'), $this->locationData([
            'parent_id' => $parent->id,
            'type' => 'neighbourhood',
            'name' => 'Westlands',
            'seo_title' => 'Westlands Escorts and Independent Profiles',
        ]))->assertSessionHasNoErrors();

        $child = Location::query()->where('name', 'Westlands')->firstOrFail();
        $this->assertSame('nairobi/westlands', $child->full_slug);
        $this->assertDatabaseHas('location_contents', [
            'location_id' => $child->id,
            'canonical_path' => '/nairobi/westlands-escorts',
        ]);
    }

    public function test_micro_location_receives_three_level_canonical_path(): void
    {
        $seo = $this->staff('seo');
        $this->actingAs($seo)->post(route('seo.locations.store'), $this->locationData());
        $city = Location::query()->where('name', 'Nairobi')->firstOrFail();
        $this->actingAs($seo)->post(route('seo.locations.store'), $this->locationData([
            'parent_id' => $city->id,
            'type' => 'neighbourhood',
            'name' => 'Westlands',
            'seo_title' => 'Westlands Escorts and Independent Profiles',
        ]));
        $neighbourhood = Location::query()->where('name', 'Westlands')->firstOrFail();

        $this->actingAs($seo)->post(route('seo.locations.store'), $this->locationData([
            'parent_id' => $neighbourhood->id,
            'type' => 'landmark',
            'name' => 'Sarit Centre',
            'seo_title' => 'Sarit Centre Escorts and Independent Profiles',
        ]))->assertRedirect(route('seo.locations.index'))->assertSessionHasNoErrors();

        $micro = Location::query()->where('name', 'Sarit Centre')->firstOrFail();
        $this->assertSame('nairobi/westlands/sarit-centre', $micro->full_slug);
        $this->assertFalse($micro->is_indexable);
        $this->assertDatabaseHas('location_contents', [
            'location_id' => $micro->id,
            'canonical_path' => '/nairobi/westlands/sarit-centre-escorts',
        ]);
    }

    public function test_protected_top_level_slug_is_rejected(): void
    {
        $this->actingAs($this->staff('seo'))
            ->from(route('seo.locations.create'))
            ->post(route('seo.locations.store'), $this->locationData(['name' => 'Admin']))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('locations', 0);
    }

    public function test_seo_user_can_add_global_ethnicity_option_without_an_unused_country_scope(): void
    {
        $seo = $this->staff('seo');

        $this->actingAs($seo)->post(route('seo.taxonomies.store'), [
            'type' => 'ethnicity',
            'label' => 'African',
            'sort_order' => 10,
            'is_active' => '1',
        ])->assertRedirect(route('seo.taxonomies.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('taxonomy_options', [
            'type' => 'ethnicity',
            'slug' => 'african',
            'country_code' => null,
            'is_active' => true,
        ]);
    }

    public function test_seo_user_can_edit_all_homepage_copy_without_code_changes(): void
    {
        $seo = $this->staff('seo');
        $sections = [
            'vip' => ['heading' => 'Exclusive Profiles', 'description' => 'Our most visible profiles.'],
            'premium' => ['heading' => 'Featured Profiles', 'description' => 'Profiles with enhanced visibility.'],
            'basic' => ['heading' => 'All Profiles', 'description' => 'Browse all standard profiles.'],
            'new' => ['heading' => 'Just Joined', 'description' => 'Recently activated profiles.'],
        ];

        $this->actingAs($seo)->patch(route('seo.pages.homepage.update'), [
            'heading' => 'Find trusted independent providers',
            'intro_content' => '<p>Browse <strong>active</strong> provider profiles across every available package.</p>',
            'bottom_content' => '<h2>Helpful directory guide</h2><p>Use the filters to discover profiles.</p>',
            'seo_title' => 'Independent Provider Directory',
            'meta_description' => 'Browse active independent provider profiles by location, package and recently activated status.',
            'sections' => $sections,
        ])->assertRedirect(route('seo.pages.homepage.edit'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('page_contents', [
            'page_key' => 'homepage',
            'heading' => 'Find trusted independent providers',
            'updated_by' => $seo->id,
        ]);
        $this->get(route('directory.home'))
            ->assertOk()
            ->assertSee('Find trusted independent providers')
            ->assertSee('Exclusive Profiles')
            ->assertSee('<strong>active</strong>', false)
            ->assertSee('<h2>Helpful directory guide</h2>', false);
        $this->assertDatabaseHas('audit_logs', ['action' => 'pages.content-update']);
    }

    public function test_seo_user_can_edit_location_top_and_bottom_content(): void
    {
        $seo = $this->staff('seo');
        $this->actingAs($seo)->post(route('seo.locations.store'), $this->locationData());
        $location = Location::query()->firstOrFail();

        $this->actingAs($seo)->patch(route('seo.locations.content.update', $location), [
            'status' => 'published',
            'heading' => 'Independent Nairobi Profiles',
            'intro_content' => str_repeat('<p>Updated <strong>original</strong> introduction for Nairobi visitors.</p>', 3),
            'bottom_content' => '<h2>Choosing a Nairobi profile</h2><p>Review each listing before making contact.</p>',
            'seo_title' => 'Independent Nairobi Profiles and Escorts',
            'meta_description' => 'Browse independently managed Nairobi profiles with current package and location information.',
            'canonical_path' => '/nairobi-escorts',
        ])->assertRedirect(route('seo.locations.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('location_contents', [
            'location_id' => $location->id,
            'heading' => 'Independent Nairobi Profiles',
            'reviewed_by' => $seo->id,
        ]);
        $this->get('/nairobi-escorts')
            ->assertOk()
            ->assertSee('Independent Nairobi Profiles')
            ->assertSee('<strong>original</strong>', false)
            ->assertSee('<h2>Choosing a Nairobi profile</h2>', false);
        $this->assertDatabaseHas('audit_logs', ['action' => 'locations.content-update', 'target_id' => $location->id]);
    }

    public function test_seo_user_can_manage_location_aliases(): void
    {
        $seo = $this->staff('seo');
        $this->actingAs($seo)->post(route('seo.locations.store'), $this->locationData());
        $location = Location::query()->firstOrFail();

        $this->actingAs($seo)->patch(route('seo.locations.content.update', $location), [
            'status' => 'published',
            'heading' => 'Nairobi Escorts',
            'intro_content' => str_repeat('Original Nairobi directory information for visitors. ', 3),
            'bottom_content' => null,
            'seo_title' => 'Nairobi Escorts and Independent Profiles',
            'meta_description' => 'Browse active independent profiles in Nairobi with useful directory information.',
            'canonical_path' => '/nairobi-escorts',
            'aliases' => "NBO\nNairobi CBD\n",
        ])->assertRedirect(route('seo.locations.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('location_aliases', ['location_id' => $location->id, 'normalized_alias' => 'nbo']);
        $this->assertDatabaseHas('location_aliases', ['location_id' => $location->id, 'normalized_alias' => 'nairobi-cbd']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'locations.content-update', 'target_id' => $location->id]);
        $this->get('/nbo-escorts')->assertRedirect('/nairobi-escorts');

        $this->actingAs($seo)->patch(route('seo.locations.content.update', $location), [
            'status' => 'published',
            'heading' => 'Nairobi Escorts',
            'intro_content' => str_repeat('Original Nairobi directory information for visitors. ', 3),
            'bottom_content' => null,
            'seo_title' => 'Nairobi Escorts and Independent Profiles',
            'meta_description' => 'Browse active independent profiles in Nairobi with useful directory information.',
            'canonical_path' => '/nairobi-escorts',
            'aliases' => 'Nairobi CBD',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('location_aliases', ['location_id' => $location->id, 'normalized_alias' => 'nbo']);
        $this->assertDatabaseHas('location_aliases', ['location_id' => $location->id, 'normalized_alias' => 'nairobi-cbd']);
    }

    public function test_draft_location_can_be_completed_and_published_later(): void
    {
        $seo = $this->staff('seo');
        $this->actingAs($seo)->post(route('seo.locations.store'), [
            'country_code' => 'KE',
            'type' => 'city',
            'name' => 'Mombasa',
            'status' => 'draft',
        ])->assertRedirect(route('seo.locations.index'))->assertSessionHasNoErrors();

        $location = Location::query()->where('slug', 'mombasa')->firstOrFail();
        $this->assertSame('draft', $location->status);
        $this->assertDatabaseHas('location_contents', [
            'location_id' => $location->id,
            'content_status' => 'draft',
            'canonical_path' => '/mombasa-escorts',
        ]);
        $this->actingAs($seo)->get(route('seo.locations.content.edit', $location))
            ->assertOk()
            ->assertSee('Publication status');

        $this->actingAs($seo)->patch(route('seo.locations.content.update', $location), [
            'status' => 'published',
            'heading' => 'Mombasa Escorts',
            'intro_content' => str_repeat('Original Mombasa directory information for visitors. ', 3),
            'bottom_content' => '<h2>About Mombasa listings</h2>',
            'seo_title' => 'Mombasa Escorts and Independent Profiles',
            'meta_description' => 'Browse active independent profiles in Mombasa with useful location and directory information.',
            'canonical_path' => '/mombasa-escorts',
        ])->assertRedirect(route('seo.locations.index'))->assertSessionHasNoErrors();

        $this->assertSame('published', $location->refresh()->status);
        $this->assertDatabaseHas('location_contents', [
            'location_id' => $location->id,
            'content_status' => 'approved',
            'reviewed_by' => $seo->id,
        ]);
        $this->get('/mombasa-escorts')->assertOk()->assertSee('Mombasa Escorts');
    }

    public function test_subscriber_cannot_update_managed_page_content(): void
    {
        $this->actingAs(User::factory()->create())
            ->patch(route('seo.pages.homepage.update'), [])
            ->assertForbidden();
    }

    public function test_seo_user_can_edit_agency_directory_content(): void
    {
        $seo = $this->staff('seo');

        $this->actingAs($seo)->patch(route('seo.pages.agencies.update'), [
            'heading' => 'Independent Escort Agencies',
            'intro_content' => '<p>Browse agencies with <strong>active</strong> and currently available provider profiles.</p>',
            'bottom_content' => '<h2>Working with agencies</h2><p>Review each agency and its active profiles.</p>',
            'seo_title' => 'Independent Escort Agencies',
            'meta_description' => 'Browse independent escort agencies with active provider profiles and current public listings.',
        ])->assertRedirect(route('seo.pages.agencies.edit'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('page_contents', [
            'page_key' => 'agencies',
            'heading' => 'Independent Escort Agencies',
            'updated_by' => $seo->id,
        ]);
        $this->get(route('directory.agencies.index'))
            ->assertOk()
            ->assertSee('Independent Escort Agencies')
            ->assertSee('<strong>active</strong>', false)
            ->assertSee('<h2>Working with agencies</h2>', false);
    }

    public function test_seo_user_can_edit_a_taxonomy_option(): void
    {
        $seo = $this->staff('seo');
        $this->actingAs($seo)->post(route('seo.taxonomies.store'), [
            'type' => 'ethnicity', 'label' => 'African', 'country_code' => 'ke', 'sort_order' => 10, 'is_active' => '1',
        ]);
        $option = TaxonomyOption::query()->where('slug', 'african')->firstOrFail();

        $this->actingAs($seo)->patch(route('seo.taxonomies.update', $option), [
            'label' => 'East African', 'sort_order' => 5, 'is_active' => '0',
        ])->assertRedirect(route('seo.taxonomies.index'))->assertSessionHasNoErrors();

        $option->refresh();
        $this->assertSame('East African', $option->label);
        $this->assertSame('east-african', $option->slug);
        $this->assertSame(5, $option->sort_order);
        $this->assertFalse($option->is_active);
        // Type is immutable and new taxonomy options are global.
        $this->assertSame('ethnicity', $option->type);
        $this->assertNull($option->country_code);
        $this->assertDatabaseHas('audit_logs', ['action' => 'taxonomies.update', 'target_id' => $option->id]);
    }

    public function test_deleting_an_unused_taxonomy_option_succeeds(): void
    {
        $seo = $this->staff('seo');
        $option = TaxonomyOption::query()->create(['type' => 'language', 'slug' => 'swahili', 'label' => 'Swahili', 'is_active' => true]);

        $this->actingAs($seo)->delete(route('seo.taxonomies.delete', $option))
            ->assertRedirect(route('seo.taxonomies.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('taxonomy_options', ['id' => $option->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'taxonomies.delete']);
    }

    public function test_deleting_a_restrict_type_taxonomy_option_in_use_is_refused(): void
    {
        $seo = $this->staff('seo');
        $genderInUse = TaxonomyOption::query()->create(['type' => 'gender', 'slug' => 'woman', 'label' => 'Woman', 'is_active' => true]);
        $this->createMinimalProfile($genderInUse);

        $this->actingAs($seo)->delete(route('seo.taxonomies.delete', $genderInUse))
            ->assertSessionHasErrors('taxonomy');

        $this->assertDatabaseHas('taxonomy_options', ['id' => $genderInUse->id]);
    }

    public function test_deleting_a_null_on_delete_type_taxonomy_option_in_use_is_also_refused(): void
    {
        $seo = $this->staff('seo');
        $hairColor = TaxonomyOption::query()->create(['type' => 'hair_color', 'slug' => 'black', 'label' => 'Black', 'is_active' => true]);
        $gender = TaxonomyOption::query()->create(['type' => 'gender', 'slug' => 'man', 'label' => 'Man', 'is_active' => true]);
        $profile = $this->createMinimalProfile($gender);
        ProfileDetail::query()->create(['profile_id' => $profile->id, 'hair_color_option_id' => $hairColor->id]);

        $this->actingAs($seo)->delete(route('seo.taxonomies.delete', $hairColor))
            ->assertSessionHasErrors('taxonomy');

        $this->assertDatabaseHas('taxonomy_options', ['id' => $hairColor->id]);
        $this->assertDatabaseHas('profile_details', ['profile_id' => $profile->id, 'hair_color_option_id' => $hairColor->id]);
    }

    private function createMinimalProfile(TaxonomyOption $gender): Profile
    {
        $city = Location::query()->create([
            'country_code' => 'KE', 'type' => 'city', 'name' => 'Nairobi', 'slug' => 'nairobi',
            'full_slug' => 'nairobi', 'status' => 'published',
        ]);
        $neighbourhood = Location::query()->create([
            'parent_id' => $city->id, 'country_code' => 'KE', 'type' => 'neighbourhood',
            'name' => 'Westlands', 'slug' => 'westlands', 'full_slug' => 'nairobi/westlands',
            'status' => 'published',
        ]);
        $ethnicity = TaxonomyOption::query()->create(['type' => 'ethnicity', 'slug' => 'african', 'label' => 'African', 'is_active' => true]);
        $build = TaxonomyOption::query()->create(['type' => 'build', 'slug' => 'average', 'label' => 'Average', 'is_active' => true]);

        return Profile::query()->create([
            'display_name' => 'Test Profile', 'slug' => 'test-profile-'.uniqid(),
            'description' => 'A minimal profile used to exercise taxonomy usage checks.',
            'primary_location_id' => $city->id, 'sublocation_id' => $neighbourhood->id,
            'gender_option_id' => $gender->id, 'date_of_birth' => now()->subYears(25),
            'ethnicity_option_id' => $ethnicity->id, 'build_option_id' => $build->id,
        ]);
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }

    /** @param  array<string, mixed>  $overrides */
    private function locationData(array $overrides = []): array
    {
        return array_replace([
            'country_code' => 'KE',
            'type' => 'city',
            'name' => 'Nairobi',
            'status' => 'published',
            'intro_content' => str_repeat('Original and helpful location information for visitors and providers. ', 3),
            'seo_title' => 'Nairobi Escorts and Independent Profiles',
            'meta_description' => 'Browse active independent profiles in Nairobi with clear location details and helpful directory information.',
        ], $overrides);
    }
}
