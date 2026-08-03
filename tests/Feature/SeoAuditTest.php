<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\LocationContent;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
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

        $response = $this->actingAs($this->staff('seo'))->get(route('seo.audit.index'))->assertOk();

        $response->assertSee('Kisumu')->assertDontSee('Nairobi')->assertDontSee('Nakuru');
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
