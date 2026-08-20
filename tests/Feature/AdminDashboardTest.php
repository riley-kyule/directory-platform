<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use App\Services\DashboardMetricsService;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessControlSeeder::class, DirectoryDefaultsSeeder::class]);
    }

    public function test_dashboard_requires_the_audit_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_the_dashboard(): void
    {
        $this->actingAs($this->staff('admin'))
            ->get(route('admin.dashboard.index'))
            ->assertOk()
            ->assertSee('Active listings')
            ->assertSee('Recent activity')
            ->assertSee('Top search terms');
    }

    public function test_csr_can_view_the_dashboard(): void
    {
        $this->actingAs($this->staff('csr'))
            ->get(route('admin.dashboard.index'))
            ->assertOk();
    }

    public function test_summary_returns_expected_keys(): void
    {
        $metrics = app(DashboardMetricsService::class)->summary();

        $this->assertArrayHasKey('profiles_by_status', $metrics);
        $this->assertArrayHasKey('profiles_active', $metrics);
        $this->assertArrayHasKey('pages_count', $metrics);
        $this->assertArrayHasKey('users_total', $metrics);
        $this->assertArrayHasKey('users_by_role', $metrics);
        $this->assertArrayHasKey('recent_activity', $metrics);
        $this->assertArrayHasKey('search_top_terms', $metrics);
        $this->assertArrayHasKey('moderation_overdue', $metrics);
        $this->assertSame(2, $metrics['pages_count']); // homepage + agencies, no locations seeded here
        $this->assertSame(0, $metrics['profiles_active']);
    }

    public function test_summary_counts_published_indexable_locations(): void
    {
        Location::query()->create([
            'country_code' => 'KE', 'type' => 'city', 'name' => 'Nairobi', 'slug' => 'nairobi',
            'full_slug' => 'nairobi', 'status' => 'published', 'is_indexable' => true,
        ]);
        Location::query()->create([
            'country_code' => 'KE', 'type' => 'city', 'name' => 'Draft City', 'slug' => 'draft-city',
            'full_slug' => 'draft-city', 'status' => 'draft', 'is_indexable' => true,
        ]);

        $this->assertSame(1, app(DashboardMetricsService::class)->summary()['locations_published']);
    }

    public function test_nav_dashboard_link_points_privileged_users_at_the_admin_dashboard(): void
    {
        $this->actingAs($this->staff('admin'))
            ->get(route('admin.settings.index'))
            ->assertSee(route('admin.dashboard.index'), false);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertSee(route('dashboard'), false)
            ->assertDontSee(route('admin.dashboard.index'), false);
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }
}
