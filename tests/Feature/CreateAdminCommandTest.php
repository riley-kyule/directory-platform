<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_verified_admin_when_the_role_exists(): void
    {
        $this->seed(AccessControlSeeder::class);

        $this->artisan('admin:create', [
            '--email' => 'owner@example.com',
            '--name' => 'Site Owner',
            '--password' => 'a-strong-password-123',
        ])->assertSuccessful();

        $user = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue(Hash::check('a-strong-password-123', $user->password));
    }

    public function test_it_fails_without_the_admin_role_seeded(): void
    {
        $this->artisan('admin:create', [
            '--email' => 'owner@example.com',
            '--name' => 'Site Owner',
            '--password' => 'a-strong-password-123',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'owner@example.com']);
    }

    public function test_it_rejects_a_duplicate_email(): void
    {
        $this->seed(AccessControlSeeder::class);
        User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('admin:create', [
            '--email' => 'owner@example.com',
            '--name' => 'Site Owner',
            '--password' => 'a-strong-password-123',
        ])->assertFailed();

        $this->assertSame(1, User::query()->where('email', 'owner@example.com')->count());
    }

    public function test_it_rejects_a_weak_password(): void
    {
        $this->seed(AccessControlSeeder::class);

        $this->artisan('admin:create', [
            '--email' => 'owner@example.com',
            '--name' => 'Site Owner',
            '--password' => '123',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'owner@example.com']);
    }
}
