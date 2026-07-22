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
