<?php

namespace Tests\Feature;

use App\Jobs\RunSelfDeploy;
use App\Models\Role;
use App\Models\User;
use App\Services\SelfDeployService;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SelfDeployTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessControlSeeder::class, DirectoryDefaultsSeeder::class]);
        config([
            'deployment.enabled' => true,
            'deployment.repo_url' => 'https://github.com/example/example.git',
            'deployment.branch' => 'main',
            'deployment.app_root' => '/home/example/example.com',
        ]);
    }

    public function test_deployment_section_is_hidden_when_self_deploy_is_disabled(): void
    {
        config(['deployment.enabled' => false]);

        $this->actingAs($this->admin())
            ->get(route('admin.settings.updates.index'))
            ->assertOk()
            ->assertDontSee('Check for updates');
    }

    public function test_checking_for_updates_requires_the_settings_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.settings.deployment.check'))
            ->assertForbidden();
    }

    public function test_admin_can_check_for_updates_and_see_deploy_now_when_behind(): void
    {
        $this->mock(SelfDeployService::class, function ($mock): void {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('currentCommit')->andReturn(str_repeat('a', 40));
            $mock->shouldReceive('remoteCommit')->andReturn(str_repeat('b', 40));
        });

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.settings.deployment.check'))
            ->assertRedirect()
            ->assertSessionHas('deployment_check');

        $this->actingAs($admin)->get(route('admin.settings.updates.index'))
            ->assertOk()
            ->assertSee('Deploy now')
            ->assertSee('An update is available');
    }

    public function test_check_for_updates_reports_up_to_date_when_commits_match(): void
    {
        $sha = str_repeat('c', 40);
        $this->mock(SelfDeployService::class, function ($mock) use ($sha): void {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('currentCommit')->andReturn($sha);
            $mock->shouldReceive('remoteCommit')->andReturn($sha);
        });

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.settings.deployment.check'));

        $this->actingAs($admin)->get(route('admin.settings.updates.index'))
            ->assertOk()
            ->assertDontSee('Deploy now')
            ->assertSee('Already up to date');
    }

    public function test_check_for_updates_reports_an_error_when_the_remote_is_unreachable(): void
    {
        $this->mock(SelfDeployService::class, function ($mock): void {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('remoteCommit')->andReturn(null);
        });

        $this->actingAs($this->admin())->post(route('admin.settings.deployment.check'))
            ->assertSessionHasErrors('deployment');
    }

    public function test_admin_can_trigger_a_deploy(): void
    {
        Queue::fake();
        $this->mock(SelfDeployService::class, function ($mock): void {
            $mock->shouldReceive('enabled')->andReturn(true);
        });

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.settings.deployment.deploy'))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('deployments', [
            'status' => 'queued',
            'triggered_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'deployment.trigger',
        ]);
        Queue::assertPushed(RunSelfDeploy::class);
    }

    public function test_triggering_a_deploy_requires_the_settings_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.settings.deployment.deploy'))
            ->assertForbidden();
    }

    public function test_deploy_action_is_unavailable_when_self_deploy_is_disabled(): void
    {
        config(['deployment.enabled' => false]);

        $this->actingAs($this->admin())
            ->post(route('admin.settings.deployment.deploy'))
            ->assertNotFound();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'admin')->firstOrFail());

        return $user;
    }
}
