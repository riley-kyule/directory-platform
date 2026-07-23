<?php

namespace Tests\Feature;

use App\Models\DirectoryRedirect;
use App\Models\Location;
use App\Models\Profile;
use App\Models\Role;
use App\Models\TaxonomyOption;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedirectManagementTest extends TestCase
{
    use RefreshDatabase;

    private Profile $profile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessControlSeeder::class, DirectoryDefaultsSeeder::class]);

        $city = Location::query()->create([
            'country_code' => 'KE', 'type' => 'city', 'name' => 'Nairobi', 'slug' => 'nairobi',
            'full_slug' => 'nairobi', 'status' => 'published',
        ]);
        $neighbourhood = Location::query()->create([
            'parent_id' => $city->id, 'country_code' => 'KE', 'type' => 'neighbourhood',
            'name' => 'Westlands', 'slug' => 'westlands', 'full_slug' => 'nairobi/westlands',
            'status' => 'published',
        ]);
        $ethnicity = TaxonomyOption::query()->create([
            'type' => 'ethnicity', 'slug' => 'african', 'label' => 'African', 'is_active' => true,
        ]);
        $this->profile = Profile::query()->create([
            'owner_user_id' => User::factory()->create()->id,
            'display_name' => 'Jane Redirect', 'slug' => 'jane-original',
            'description' => 'A complete profile used to verify permanent URL history.',
            'primary_location_id' => $city->id, 'sublocation_id' => $neighbourhood->id,
            'gender_option_id' => TaxonomyOption::query()->ofType('gender')->firstOrFail()->id,
            'date_of_birth' => now()->subYears(25), 'ethnicity_option_id' => $ethnicity->id,
            'build_option_id' => TaxonomyOption::query()->ofType('build')->firstOrFail()->id,
        ]);
    }

    public function test_seo_can_create_a_local_redirect_and_it_only_resolves_missing_get_routes(): void
    {
        $seo = $this->userWithRole('seo');

        $this->actingAs($seo)->post(route('seo.redirects.store'), [
            'source_path' => 'LEGACY-PAGE',
            'target_path' => '/agencies',
            'status_code' => 301,
            'reason' => 'Legacy campaign URL consolidation.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->get('/legacy-page')->assertRedirect('/agencies')->assertStatus(301);
        $this->post('/legacy-page')->assertStatus(405);
        $this->assertDatabaseHas('audit_logs', ['action' => 'redirects.create']);
    }

    public function test_gone_rule_returns_410_with_noindex_header(): void
    {
        DirectoryRedirect::query()->create([
            'source_path' => '/removed-listing',
            'status_code' => 410,
            'reason' => 'Permanently removed without an equivalent replacement.',
            'is_active' => true,
        ]);

        $this->get('/removed-listing')
            ->assertStatus(410)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_live_canonical_paths_and_redirect_loops_are_rejected(): void
    {
        $seo = $this->userWithRole('seo');

        $this->actingAs($seo)->post(route('seo.redirects.store'), [
            'source_path' => '/escort/jane-original',
            'target_path' => '/agencies',
            'status_code' => 301,
            'reason' => 'Must not replace a canonical page.',
        ])->assertSessionHasErrors('source_path');

        DirectoryRedirect::query()->create([
            'source_path' => '/path-b', 'target_path' => '/path-a', 'status_code' => 301,
            'reason' => 'Existing redirect.', 'is_active' => true,
        ]);
        $this->actingAs($seo)->post(route('seo.redirects.store'), [
            'source_path' => '/path-a',
            'target_path' => '/path-b',
            'status_code' => 301,
            'reason' => 'This creates a loop.',
        ])->assertSessionHasErrors('target_path');
    }

    public function test_authorized_slug_change_preserves_history_and_flattens_redirect_chains(): void
    {
        $seo = $this->userWithRole('seo');
        DirectoryRedirect::query()->create([
            'source_path' => '/escort/jane-very-old',
            'target_path' => '/escort/jane-original',
            'status_code' => 301,
            'reason' => 'Earlier slug migration.',
            'is_active' => true,
        ]);

        $this->actingAs($seo)->patch(route('seo.profiles.slug.update', $this->profile), [
            'slug' => 'Jane Current',
            'reason' => 'Approved keyword-safe canonical update.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('jane-current', $this->profile->refresh()->slug);
        $this->assertDatabaseHas('profile_slug_histories', [
            'profile_id' => $this->profile->id,
            'old_slug' => 'jane-original',
            'new_slug' => 'jane-current',
            'changed_by' => $seo->id,
        ]);
        $this->assertDatabaseHas('redirects', [
            'source_path' => '/escort/jane-original',
            'target_path' => '/escort/jane-current',
            'status_code' => 301,
        ]);
        $this->assertDatabaseHas('redirects', [
            'source_path' => '/escort/jane-very-old',
            'target_path' => '/escort/jane-current',
        ]);
        $this->get('/escort/jane-original')->assertRedirect('/escort/jane-current')->assertStatus(301);
    }

    public function test_subscribers_cannot_manage_redirects_or_slugs(): void
    {
        $subscriber = User::factory()->create();

        $this->actingAs($subscriber)->get(route('seo.redirects.index'))->assertForbidden();
        $this->actingAs($subscriber)->patch(route('seo.profiles.slug.update', $this->profile), [
            'slug' => 'unauthorized-change',
            'reason' => 'Should not be accepted.',
        ])->assertForbidden();
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->firstOrFail());

        return $user;
    }
}
