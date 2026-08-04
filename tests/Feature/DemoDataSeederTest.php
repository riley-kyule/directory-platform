<?php

namespace Tests\Feature;

use App\Enums\ProfileStatus;
use App\Models\Agency;
use App\Models\Location;
use App\Models\ModerationAppeal;
use App\Models\Profile;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_profiles_across_every_status_and_package(): void
    {
        $this->seed(DemoDataSeeder::class);

        foreach (ProfileStatus::cases() as $status) {
            $this->assertTrue(
                Profile::query()->where('status', $status)->exists(),
                "Expected at least one profile with status {$status->value}.",
            );
        }

        $this->assertTrue(Agency::query()->where('slug', 'sunrise-companions')->exists());
        $this->assertTrue(Agency::query()->where('slug', 'velvet-collective')->exists());
    }

    public function test_it_seeds_a_deliberately_empty_and_a_deep_location(): void
    {
        $this->seed(DemoDataSeeder::class);

        $kisumu = Location::query()->where('name', 'Kisumu')->firstOrFail();
        $this->assertSame(0, $kisumu->active_profile_count);

        $cbd = Location::query()->where('name', 'CBD')->firstOrFail();
        $this->assertGreaterThanOrEqual(6, $cbd->active_profile_count);
    }

    public function test_it_seeds_a_deactivated_profile_with_a_pending_appeal(): void
    {
        $this->seed(DemoDataSeeder::class);

        $deactivated = Profile::query()->where('status', ProfileStatus::Deactivated)->firstOrFail();
        $this->assertTrue(
            ModerationAppeal::query()->where('profile_id', $deactivated->id)->where('status', 'pending')->exists(),
        );
    }

    public function test_it_is_safe_to_run_more_than_once(): void
    {
        $this->seed(DemoDataSeeder::class);
        $firstRunDemoUserCount = User::query()->where('email', 'like', 'demo-%')->count();

        $this->seed(DemoDataSeeder::class);
        $secondRunDemoUserCount = User::query()->where('email', 'like', 'demo-%')->count();

        $this->assertSame($firstRunDemoUserCount, $secondRunDemoUserCount);
    }

    public function test_it_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(\RuntimeException::class);

        // Calling run() directly (rather than through the db:seed console
        // command) bypasses Laravel's own production confirmation prompt, so
        // this exercises the seeder's own guard in isolation.
        $this->app->make(DemoDataSeeder::class)->run();
    }
}
