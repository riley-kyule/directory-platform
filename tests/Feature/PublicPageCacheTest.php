<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\ProviderType;
use App\Models\Agency;
use App\Models\Location;
use App\Models\Profile;
use App\Models\TaxonomyOption;
use App\Models\User;
use App\Services\PublicPageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPageCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgetting_a_profile_on_an_agency_roster_busts_that_agencys_cached_pages(): void
    {
        $location = Location::query()->create([
            'country_code' => 'KE', 'type' => 'city', 'name' => 'Nairobi', 'slug' => 'nairobi',
            'full_slug' => 'nairobi', 'status' => 'published', 'is_indexable' => true,
        ]);
        $sublocation = Location::query()->create([
            'parent_id' => $location->id, 'country_code' => 'KE', 'type' => 'neighbourhood',
            'name' => 'Westlands', 'slug' => 'westlands', 'full_slug' => 'nairobi/westlands',
            'status' => 'published', 'is_indexable' => true,
        ]);
        TaxonomyOption::query()->create(['type' => 'ethnicity', 'slug' => 'african', 'label' => 'African', 'sort_order' => 10, 'is_active' => true]);
        $gender = TaxonomyOption::query()->create(['type' => 'gender', 'slug' => 'female', 'label' => 'Female', 'sort_order' => 10, 'is_active' => true]);
        $build = TaxonomyOption::query()->create(['type' => 'build', 'slug' => 'slim', 'label' => 'Slim', 'sort_order' => 10, 'is_active' => true]);
        $ethnicity = TaxonomyOption::query()->where('type', 'ethnicity')->firstOrFail();

        $agencyOwner = User::factory()->create(['account_type' => AccountType::Provider, 'provider_type' => ProviderType::Agency]);
        $agency = Agency::query()->create(['owner_user_id' => $agencyOwner->id, 'name' => 'Elite Models', 'slug' => 'elite-models', 'status' => 'active']);

        $profile = Profile::query()->create([
            'display_name' => 'Roster Member', 'slug' => 'roster-member',
            'description' => 'A profile attached to an agency roster to exercise cache invalidation.',
            'primary_location_id' => $location->id, 'sublocation_id' => $sublocation->id,
            'gender_option_id' => $gender->id, 'date_of_birth' => now()->subYears(25),
            'ethnicity_option_id' => $ethnicity->id, 'build_option_id' => $build->id,
        ]);
        $agency->profiles()->attach($profile, ['assigned_by' => $agencyOwner->id, 'assigned_at' => now()]);

        $pageCache = app(PublicPageCache::class);
        $renders = 0;
        $render = function () use (&$renders) {
            $renders++;

            return ['status' => 200, 'content' => 'x', 'content_type' => 'text/html', 'location' => null];
        };

        $pageCache->remember(route('directory.agencies.show', $agency->slug), $render);
        $this->assertSame(1, $renders);

        $pageCache->forgetForProfile($profile->fresh());

        $pageCache->remember(route('directory.agencies.show', $agency->slug), $render);
        $this->assertSame(2, $renders, 'forgetForProfile should have busted the roster agency\'s cached page.');
    }
}
