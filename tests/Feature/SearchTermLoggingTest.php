<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SearchTermLog;
use App\Models\User;
use App\Services\SearchTermLogger;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTermLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_term_is_not_logged_until_it_crosses_the_daily_threshold(): void
    {
        $logger = app(SearchTermLogger::class);

        for ($i = 0; $i < 10; $i++) {
            $logger->record('threshold test term');
        }
        $this->assertDatabaseMissing('search_term_logs', ['term' => 'threshold test term']);

        $logger->record('threshold test term');
        $this->assertDatabaseHas('search_term_logs', [
            'term' => 'threshold test term',
            'search_count' => 11,
        ]);
    }

    public function test_logged_term_count_keeps_growing_the_same_day_without_duplicate_rows(): void
    {
        $logger = app(SearchTermLogger::class);
        for ($i = 0; $i < 15; $i++) {
            $logger->record('Growing Count Term');
        }

        $this->assertDatabaseHas('search_term_logs', [
            'term' => 'growing count term',
            'search_count' => 15,
        ]);
        $this->assertSame(1, SearchTermLog::query()->where('term', 'growing count term')->count());
    }

    public function test_blank_terms_are_never_logged(): void
    {
        app(SearchTermLogger::class)->record('   ');

        $this->assertSame(0, SearchTermLog::query()->count());
    }

    public function test_search_endpoint_records_the_normalized_term(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $this->get(route('directory.search', ['q' => 'Endpoint Test Term']));
        }

        $this->assertDatabaseHas('search_term_logs', ['term' => 'endpoint test term']);
    }

    public function test_search_insights_page_requires_permission(): void
    {
        $this->seed(AccessControlSeeder::class);

        $this->actingAs(User::factory()->create())
            ->get(route('seo.search-insights.index'))
            ->assertForbidden();
    }

    public function test_seo_staff_can_view_search_insights(): void
    {
        $this->seed(AccessControlSeeder::class);
        SearchTermLog::query()->create([
            'search_date' => now()->toDateString(),
            'term' => 'popular viewable term',
            'search_count' => 42,
        ]);

        $seo = User::factory()->create();
        $seo->roles()->attach(Role::query()->where('slug', 'seo')->firstOrFail());

        $this->actingAs($seo)
            ->get(route('seo.search-insights.index'))
            ->assertOk()
            ->assertSee('Search and conversion insights')
            ->assertSee('Contact intent — last 30 days')
            ->assertSee('popular viewable term')
            ->assertSee('42');
    }

    public function test_prune_command_removes_only_rows_past_retention(): void
    {
        SearchTermLog::query()->create(['search_date' => now()->subDays(200)->toDateString(), 'term' => 'old term', 'search_count' => 20]);
        SearchTermLog::query()->create(['search_date' => now()->toDateString(), 'term' => 'fresh term', 'search_count' => 20]);

        $this->artisan('search:prune-term-logs')->assertSuccessful();

        $this->assertDatabaseMissing('search_term_logs', ['term' => 'old term']);
        $this->assertDatabaseHas('search_term_logs', ['term' => 'fresh term']);
    }
}
