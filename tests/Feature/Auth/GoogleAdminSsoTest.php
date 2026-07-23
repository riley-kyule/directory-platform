<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

class GoogleAdminSsoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        config()->set('services.google.client_id', 'test-client');
        config()->set('services.google.client_secret', 'test-secret');
        config()->set('services.google.redirect', 'https://directory.test/auth/google/callback');
        config()->set('services.google.admin_allowed_domains', []);
    }

    public function test_login_screen_offers_google_admin_sign_in_when_configured(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Continue with Google as Admin');

        Socialite::fake('google');
        $this->get(route('auth.google.redirect'))
            ->assertRedirect('https://socialite.fake/google/authorize');
    }

    public function test_existing_admin_can_sign_in_and_link_verified_google_identity(): void
    {
        $admin = $this->admin('admin@example.com');
        Socialite::fake('google', GoogleUser::fake([
            'id' => 'google-subject-123',
            'email' => 'ADMIN@example.com',
            'email_verified' => true,
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($admin);
        $admin->refresh();
        $this->assertSame('google-subject-123', $admin->google_subject);
        $this->assertNotNull($admin->google_sso_linked_at);
        $this->assertNotNull($admin->google_sso_last_login_at);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'security.google-sso-login',
            'reason' => 'verified-existing-admin',
        ]);
    }

    public function test_google_sign_in_never_creates_users_or_promotes_subscribers(): void
    {
        Socialite::fake('google', GoogleUser::fake([
            'id' => 'unknown-google-subject',
            'email' => 'unknown@example.com',
            'email_verified' => true,
        ]));
        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google_sso');
        $this->assertDatabaseMissing('users', ['email' => 'unknown@example.com']);

        $subscriber = User::factory()->create(['email' => 'subscriber@example.com']);
        Socialite::fake('google', GoogleUser::fake([
            'id' => 'subscriber-google-subject',
            'email' => $subscriber->email,
            'email_verified' => true,
        ]));
        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google_sso');
        $this->assertGuest();
        $this->assertNull($subscriber->refresh()->google_subject);
    }

    public function test_unverified_disallowed_or_mismatched_google_identity_is_rejected(): void
    {
        $admin = $this->admin('admin@example.com');
        Socialite::fake('google', GoogleUser::fake([
            'id' => 'unverified-subject',
            'email' => $admin->email,
            'email_verified' => false,
        ]));
        $this->get(route('auth.google.callback'))->assertRedirect(route('login'));
        $this->assertGuest();

        config()->set('services.google.admin_allowed_domains', ['company.test']);
        Socialite::fake('google', GoogleUser::fake([
            'id' => 'wrong-domain-subject',
            'email' => $admin->email,
            'email_verified' => true,
        ]));
        $this->get(route('auth.google.callback'))->assertRedirect(route('login'));
        $this->assertGuest();

        config()->set('services.google.admin_allowed_domains', []);
        $admin->forceFill(['google_subject' => 'original-subject'])->save();
        Socialite::fake('google', GoogleUser::fake([
            'id' => 'different-subject',
            'email' => $admin->email,
            'email_verified' => true,
        ]));
        $this->get(route('auth.google.callback'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame('original-subject', $admin->refresh()->google_subject);
    }

    public function test_google_routes_are_hidden_when_credentials_are_missing(): void
    {
        config()->set('services.google.client_id');

        $this->get(route('login'))->assertDontSee('Continue with Google as Admin');
        $this->get(route('auth.google.redirect'))->assertNotFound();
        $this->get(route('auth.google.callback'))->assertNotFound();
    }

    private function admin(string $email): User
    {
        $admin = User::factory()->create(['email' => $email]);
        $admin->roles()->attach(Role::query()->where('slug', 'admin')->firstOrFail());

        return $admin;
    }
}
