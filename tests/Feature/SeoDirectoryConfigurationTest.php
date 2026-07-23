<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Role;
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
        $this->actingAs(User::factory()->create())
            ->get(route('seo.directory.index'))
            ->assertForbidden();
    }

    public function test_seo_user_can_open_managed_homepage_editor(): void
    {
        $this->actingAs($this->staff('seo'))
            ->get(route('seo.directory.index'))
            ->assertOk()
            ->assertSee('Homepage content')
            ->assertSee('Bottom SEO content');
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
            ->assertRedirect(route('seo.directory.index'))
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

    public function test_protected_top_level_slug_is_rejected(): void
    {
        $this->actingAs($this->staff('seo'))
            ->from(route('seo.locations.create'))
            ->post(route('seo.locations.store'), $this->locationData(['name' => 'Admin']))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('locations', 0);
    }

    public function test_seo_user_can_add_country_specific_ethnicity_option(): void
    {
        $seo = $this->staff('seo');

        $this->actingAs($seo)->post(route('seo.taxonomies.store'), [
            'type' => 'ethnicity',
            'label' => 'African',
            'country_code' => 'ke',
            'sort_order' => 10,
            'is_active' => '1',
        ])->assertRedirect(route('seo.directory.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('taxonomy_options', [
            'type' => 'ethnicity',
            'slug' => 'african',
            'country_code' => 'KE',
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
            'intro_content' => 'Browse active provider profiles across every available package.',
            'bottom_content' => "## Helpful directory guide\n\nUse the filters to discover profiles.",
            'seo_title' => 'Independent Provider Directory',
            'meta_description' => 'Browse active independent provider profiles by location, package and recently activated status.',
            'sections' => $sections,
        ])->assertRedirect(route('seo.directory.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('page_contents', [
            'page_key' => 'homepage',
            'heading' => 'Find trusted independent providers',
            'updated_by' => $seo->id,
        ]);
        $this->get(route('directory.home'))
            ->assertOk()
            ->assertSee('Find trusted independent providers')
            ->assertSee('Exclusive Profiles')
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
            'intro_content' => str_repeat('Updated original introduction for Nairobi visitors. ', 3),
            'bottom_content' => "## Choosing a Nairobi profile\n\nReview each listing before making contact.",
            'seo_title' => 'Independent Nairobi Profiles and Escorts',
            'meta_description' => 'Browse independently managed Nairobi profiles with current package and location information.',
            'canonical_path' => '/nairobi-escorts',
        ])->assertRedirect(route('seo.directory.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('location_contents', [
            'location_id' => $location->id,
            'heading' => 'Independent Nairobi Profiles',
            'reviewed_by' => $seo->id,
        ]);
        $this->get('/nairobi-escorts')
            ->assertOk()
            ->assertSee('Independent Nairobi Profiles')
            ->assertSee('<h2>Choosing a Nairobi profile</h2>', false);
        $this->assertDatabaseHas('audit_logs', ['action' => 'locations.content-update', 'target_id' => $location->id]);
    }

    public function test_draft_location_can_be_completed_and_published_later(): void
    {
        $seo = $this->staff('seo');
        $this->actingAs($seo)->post(route('seo.locations.store'), [
            'country_code' => 'KE',
            'type' => 'city',
            'name' => 'Mombasa',
            'status' => 'draft',
        ])->assertRedirect(route('seo.directory.index'))->assertSessionHasNoErrors();

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
            'bottom_content' => '## About Mombasa listings',
            'seo_title' => 'Mombasa Escorts and Independent Profiles',
            'meta_description' => 'Browse active independent profiles in Mombasa with useful location and directory information.',
            'canonical_path' => '/mombasa-escorts',
        ])->assertRedirect(route('seo.directory.index'))->assertSessionHasNoErrors();

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
            'intro_content' => 'Browse agencies with active and currently available provider profiles.',
            'bottom_content' => "## Working with agencies\n\nReview each agency and its active profiles.",
            'seo_title' => 'Independent Escort Agencies',
            'meta_description' => 'Browse independent escort agencies with active provider profiles and current public listings.',
        ])->assertRedirect(route('seo.directory.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('page_contents', [
            'page_key' => 'agencies',
            'heading' => 'Independent Escort Agencies',
            'updated_by' => $seo->id,
        ]);
        $this->get(route('directory.agencies.index'))
            ->assertOk()
            ->assertSee('Independent Escort Agencies')
            ->assertSee('<h2>Working with agencies</h2>', false);
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
