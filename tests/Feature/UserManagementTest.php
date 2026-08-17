<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_user_management_requires_the_roles_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Users & Roles');
    }

    public function test_admin_can_create_a_staff_member_with_roles(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New SEO Staffer',
            'email' => 'seo-staffer@example.test',
            'password' => 'Password!2345',
            'roles' => ['seo'],
        ])->assertRedirect(route('admin.users.index'))->assertSessionHasNoErrors();

        $created = User::query()->where('email', 'seo-staffer@example.test')->firstOrFail();
        $this->assertTrue($created->hasRole('seo'));
        $this->assertNotNull($created->email_verified_at);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'users.create-staff',
            'target_id' => $created->id,
        ]);
    }

    public function test_admin_can_change_an_existing_users_roles(): void
    {
        $admin = $this->userWithRole('admin');
        $csr = $this->userWithRole('csr');

        $this->actingAs($admin)->patch(route('admin.users.roles.update', $csr), [
            'roles' => ['seo'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $csr->refresh();
        $this->assertFalse($csr->hasRole('csr'));
        $this->assertTrue($csr->hasRole('seo'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'users.update-roles',
            'target_id' => $csr->id,
        ]);
    }

    public function test_removing_the_last_admin_role_is_refused(): void
    {
        $onlyAdmin = $this->userWithRole('admin');

        $this->actingAs($onlyAdmin)->patch(route('admin.users.roles.update', $onlyAdmin), [
            'roles' => [],
        ])->assertSessionHasErrors('roles');

        $this->assertTrue($onlyAdmin->fresh()->hasRole('admin'));
    }

    public function test_demoting_the_last_admin_is_refused_even_when_a_second_admin_exists_for_removal(): void
    {
        $admin = $this->userWithRole('admin');
        $secondAdmin = $this->userWithRole('admin');

        // Demoting the second admin is fine while one remains.
        $this->actingAs($admin)->patch(route('admin.users.roles.update', $secondAdmin), [
            'roles' => ['csr'],
        ])->assertSessionHasNoErrors();
        $this->assertFalse($secondAdmin->fresh()->hasRole('admin'));

        // Now only $admin is left — demoting them must be refused.
        $this->actingAs($admin)->patch(route('admin.users.roles.update', $admin), [
            'roles' => [],
        ])->assertSessionHasErrors('roles');
        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_admin_can_delete_another_user(): void
    {
        $admin = $this->userWithRole('admin');
        $csr = $this->userWithRole('csr');
        $email = $csr->email;

        $this->actingAs($admin)->delete(route('admin.users.destroy', $csr))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSoftDeleted($csr);
        $this->assertNotSame($email, $csr->fresh()->email);
        User::factory()->create(['email' => $email]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'users.delete',
            'target_id' => $csr->id,
        ]);
    }

    public function test_admin_cannot_delete_their_own_account_from_this_panel(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))
            ->assertSessionHasErrors('user');

        $this->assertNull($admin->fresh()->deleted_at);
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->firstOrFail());

        return $user;
    }
}
