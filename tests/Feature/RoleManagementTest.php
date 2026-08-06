<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_roles_page_requires_the_roles_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.roles.index'))
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertSee('Admin')
            ->assertSee('CSR')
            ->assertSee('Add a new role');
    }

    public function test_admin_can_create_a_custom_role_with_a_subset_of_permissions(): void
    {
        $admin = $this->admin();
        $permissionIds = Permission::query()->whereIn('slug', ['reviews.view', 'reviews.moderate'])->pluck('id');

        $this->actingAs($admin)->post(route('admin.roles.store'), [
            'name' => 'Review Moderator',
            'permissions' => $permissionIds->all(),
        ])->assertRedirect(route('admin.roles.index'))->assertSessionHasNoErrors();

        $role = Role::query()->where('slug', 'review-moderator')->firstOrFail();
        $this->assertFalse($role->is_system);
        $this->assertSame(['reviews.moderate', 'reviews.view'], $role->permissions()->pluck('slug')->sort()->values()->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'roles.create', 'target_id' => $role->id]);
    }

    public function test_admin_can_toggle_permissions_on_a_system_role_without_changing_its_name(): void
    {
        $admin = $this->admin();
        $csr = Role::query()->where('slug', 'csr')->firstOrFail();
        $newPermissionIds = Permission::query()->whereIn('slug', ['reviews.view', 'reviews.moderate'])->pluck('id');

        $this->actingAs($admin)->patch(route('admin.roles.update', $csr), [
            'name' => 'Attempted Rename',
            'permissions' => $newPermissionIds->all(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $csr->refresh();
        $this->assertSame('CSR', $csr->name);
        $this->assertTrue($csr->is_system);
        $this->assertSame(['reviews.moderate', 'reviews.view'], $csr->permissions()->pluck('slug')->sort()->values()->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'roles.update', 'target_id' => $csr->id]);
    }

    public function test_deleting_a_system_role_is_refused(): void
    {
        $admin = $this->admin();
        $seo = Role::query()->where('slug', 'seo')->firstOrFail();

        $this->actingAs($admin)->delete(route('admin.roles.destroy', $seo))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('roles', ['id' => $seo->id]);
    }

    public function test_deleting_a_role_with_users_attached_is_refused(): void
    {
        $admin = $this->admin();
        $custom = Role::query()->create(['name' => 'Custom', 'slug' => 'custom', 'is_system' => false]);
        $member = User::factory()->create();
        $member->roles()->attach($custom->id);

        $this->actingAs($admin)->delete(route('admin.roles.destroy', $custom))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('roles', ['id' => $custom->id]);
    }

    public function test_admin_can_delete_an_unused_custom_role(): void
    {
        $admin = $this->admin();
        $custom = Role::query()->create(['name' => 'Unused', 'slug' => 'unused', 'is_system' => false]);

        $this->actingAs($admin)->delete(route('admin.roles.destroy', $custom))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('roles', ['id' => $custom->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'roles.delete']);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'admin')->firstOrFail());

        return $user;
    }
}
