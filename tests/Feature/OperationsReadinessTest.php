<?php

namespace Tests\Feature;

use App\Models\BackupRecord;
use App\Models\Role;
use App\Models\SystemHeartbeat;
use App\Models\User;
use App\Services\SystemHealthService;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperationsReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_readiness_is_unavailable_until_scheduler_heartbeat_is_current(): void
    {
        $this->get(route('health.ready'))
            ->assertStatus(503)
            ->assertExactJson(['status' => 'unavailable']);

        Artisan::call('system:heartbeat', ['name' => 'scheduler']);

        $this->get(route('health.ready'))
            ->assertOk()
            ->assertExactJson(['status' => 'ready']);
        $this->assertNotNull(SystemHeartbeat::query()->find('scheduler'));
    }

    public function test_detailed_health_dashboard_is_admin_only(): void
    {
        Artisan::call('system:heartbeat', ['name' => 'scheduler']);
        $this->actingAs(User::factory()->create())
            ->get(route('admin.system-health'))
            ->assertForbidden();
        $this->actingAs($this->staff('csr'))
            ->get(route('admin.system-health'))
            ->assertForbidden();
        $this->actingAs($this->staff('admin'))
            ->get(route('admin.system-health'))
            ->assertOk()
            ->assertSee('Operational checks')
            ->assertSee('Scheduler');
    }

    public function test_health_reports_queue_delay_failed_jobs_and_backup_freshness_without_making_warnings_unready(): void
    {
        Storage::fake('local');
        config()->set('operations.queue_age_warning_minutes', 1);
        SystemHeartbeat::query()->create(['name' => 'scheduler', 'last_seen_at' => now()]);
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subMinutes(5)->timestamp,
            'created_at' => now()->subMinutes(5)->timestamp,
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => str()->uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Test failure',
            'failed_at' => now(),
        ]);
        Storage::disk('local')->put('backups/test.sql.gz', 'verified archive');
        BackupRecord::query()->create([
            'disk' => 'local',
            'path' => 'backups/test.sql.gz',
            'size_bytes' => 16,
            'checksum_sha256' => hash('sha256', 'verified archive'),
            'status' => 'completed',
            'completed_at' => now(),
            'verified_at' => now(),
        ]);

        $checks = app(SystemHealthService::class)->checks();
        $this->assertSame('warning', $checks['queue']['status']);
        $this->assertSame('warning', $checks['failed_jobs']['status']);
        $this->assertSame('ok', $checks['backup']['status']);
        $this->assertTrue(app(SystemHealthService::class)->isReady());
    }

    public function test_launch_check_fails_closed_when_required_launch_evidence_is_missing(): void
    {
        $exit = Artisan::call('system:launch-check');

        $this->assertSame(1, $exit);
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }
}
