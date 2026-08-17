<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountType;
use App\Enums\ProviderType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'account_type' => 'member',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertTrue(User::query()->where('email', 'test@example.com')->firstOrFail()->last_seen_at->isCurrentMinute());
        $this->assertNull(User::query()->where('email', 'test@example.com')->firstOrFail()->email_verified_at);
    }

    public function test_unverified_provider_cannot_access_onboarding(): void
    {
        $provider = User::factory()->unverified()->create([
            'account_type' => AccountType::Provider,
            'provider_type' => ProviderType::Independent,
        ]);

        $this->actingAs($provider)->get(route('onboarding.index'))
            ->assertRedirect(route('verification.notice'));
    }
}
