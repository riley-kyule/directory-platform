<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\LocationContent;
use App\Models\Role;
use App\Models\User;
use App\Services\SeoAuditService;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_csr_cannot_view_the_seo_audit(): void
    {
        $this->actingAs($this->staff('csr'))->get(route('seo.audit.index'))->assertForbidden();
    }

    public function test_seo_audit_flags_published_locations_with_no_active_profile(): void
    {
        $withInventory = Location::query()->create([
            'country_code' => 'KE', 'type' => 'city', 'name' => 'Nairobi', 'slug' => 'nairobi',
            'full_slug' => 'nairobi', 'status' => 'published', 'active_profile_count' => 3,
        ]);
        $orphan = Location::query()->create([
            'country_code' => 'KE', 'type' => 'city', 'name' => 'Kisumu', 'slug' => 'kisumu',
            'full_slug' => 'kisumu', 'status' => 'published', 'active_profile_count' => 0,
        ]);
        $draftEmpty = Location::query()->create([
            'country_code' => 'KE', 'type' => 'city', 'name' => 'Nakuru', 'slug' => 'nakuru',
            'full_slug' => 'nakuru', 'status' => 'draft', 'active_profile_count' => 0,
        ]);

        $orphans = app(SeoAuditService::class)->orphanLocations();

        $this->assertTrue($orphans->contains($orphan));
        $this->assertFalse($orphans->contains($withInventory));
        $this->assertFalse($orphans->contains($draftEmpty));

        $this->actingAs($this->staff('seo'))->get(route('seo.audit.index'))
            ->assertOk()
            ->assertSee('Kisumu');
    }

    public function test_seo_audit_flags_duplicate_seo_titles_and_meta_descriptions(): void
    {
        $one = $this->publishedLocationWithContent('Nairobi', 'nairobi', 'Escorts in Kenya', 'Browse profiles in this city with useful directory information.');
        $two = $this->publishedLocationWithContent('Mombasa', 'mombasa', 'Escorts in Kenya', 'A completely different meta description for Mombasa.');
        $three = $this->publishedLocationWithContent('Kisumu', 'kisumu', 'Escorts in Kisumu', 'Browse profiles in this city with useful directory information.');

        $response = $this->actingAs($this->staff('seo'))->get(route('seo.audit.index'))->assertOk();

        $response->assertSee('Escorts in Kenya')
            ->assertSee('Nairobi')
            ->assertSee('Mombasa');
        $response->assertSee('Browse profiles in this city')
            ->assertSee('Kisumu');
    }

    public function test_seo_audit_flags_indexability_metadata_depth_and_freshness_problems(): void
    {
        $location = $this->publishedLocationWithContent(
            'Mombasa',
            'mombasa',
            'Too short',
            'This description is also too short for a competitive organic search result snippet.',
        );
        $location->update(['is_indexable' => false]);
        $location->content->update(['last_reviewed_at' => now()->subDays(181)]);

        $result = app(SeoAuditService::class)->locationQualityIssues()
            ->firstWhere(fn (array $issue) => $issue['location']->is($location));

        $this->assertNotNull($result);
        $this->assertSame('critical', $result['severity']);
        $this->assertStringContainsString('Eligible for indexing', implode(' ', $result['issues']));
        $this->assertStringContainsString('SEO title is', implode(' ', $result['issues']));
        $this->assertStringContainsString('Thin location copy', implode(' ', $result['issues']));
        $this->assertStringContainsString('180 days', implode(' ', $result['issues']));
    }

    public function test_healthy_location_does_not_appear_in_quality_issues(): void
    {
        $location = $this->publishedLocationWithContent(
            'Nairobi',
            'nairobi',
            'Trusted Nairobi Directory Profiles and Local Listings',
            'Explore trusted local profiles, detailed listings, useful location guidance and current availability across Nairobi. Explore verified listings today.',
        );
        $location->update(['is_indexable' => true]);
        $location->content->update([
            'intro_content' => str_repeat('Useful original Nairobi directory guidance for visitors. ', 35),
            'last_reviewed_at' => now(),
        ]);

        $this->assertFalse(
            app(SeoAuditService::class)->locationQualityIssues()
                ->contains(fn (array $issue) => $issue['location']->is($location)),
        );
    }

    public function test_audit_dashboard_reports_core_page_content_gaps_with_edit_links(): void
    {
        $this->seed(DirectoryDefaultsSeeder::class);

        $this->actingAs($this->staff('seo'))->get(route('seo.audit.index'))
            ->assertOk()
            ->assertSee('Organic search readiness')
            ->assertSee('Core landing page quality')
            ->assertSee('Thin page copy')
            ->assertSee(route('seo.pages.homepage.edit'), false)
            ->assertSee(route('seo.pages.agencies.edit'), false);
    }

    private function publishedLocationWithContent(string $name, string $slug, string $seoTitle, string $metaDescription): Location
    {
        $location = Location::query()->create([
            'country_code' => 'KE', 'type' => 'city', 'name' => $name, 'slug' => $slug,
            'full_slug' => $slug, 'status' => 'published', 'active_profile_count' => 1,
        ]);
        LocationContent::query()->create([
            'location_id' => $location->id,
            'intro_content' => str_repeat('Original content. ', 10),
            'seo_title' => $seoTitle,
            'meta_description' => $metaDescription,
            'canonical_path' => '/'.$slug.'-escorts',
            'content_status' => 'approved',
        ]);

        return $location;
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }
}
