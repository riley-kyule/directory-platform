<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_admin_has_every_permission_by_default(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('slug', 'admin')->firstOrFail());

        $this->assertTrue($admin->hasPermission('profiles.activate'));
        $this->assertTrue($admin->hasPermission('roles.manage'));
    }

    public function test_csr_can_activate_profiles_but_cannot_manage_roles(): void
    {
        $csr = User::factory()->create();
        $csr->roles()->attach(Role::query()->where('slug', 'csr')->firstOrFail());

        $this->assertTrue($csr->hasPermission('profiles.activate'));
        $this->assertTrue($csr->hasPermission('packages.assign'));
        $this->assertFalse($csr->hasPermission('roles.manage'));
    }

    public function test_seo_cannot_view_private_profiles(): void
    {
        $seo = User::factory()->create();
        $seo->roles()->attach(Role::query()->where('slug', 'seo')->firstOrFail());

        $this->assertTrue($seo->hasPermission('seo.locations'));
        $this->assertFalse($seo->hasPermission('profiles.view-private'));
    }
}
