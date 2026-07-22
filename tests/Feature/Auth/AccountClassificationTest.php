<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountType;
use App\Enums\OnboardingStatus;
use App\Enums\ProviderType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_register_without_a_provider_type(): void
    {
        $response = $this->post('/register', [
            'name' => 'Member One',
            'email' => 'member@example.test',
            'account_type' => 'member',
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
        ]);

        $response->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'member@example.test')->firstOrFail();
        $this->assertSame(AccountType::Member, $user->account_type);
        $this->assertNull($user->provider_type);
        $this->assertSame(OnboardingStatus::Registered, $user->onboarding_status);
        $this->assertTrue($user->hasRole('subscriber'));
    }

    public function test_a_provider_must_choose_independent_or_agency(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Provider One',
            'email' => 'provider@example.test',
            'account_type' => 'provider',
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('provider_type');
        $this->assertDatabaseMissing('users', ['email' => 'provider@example.test']);
    }

    public function test_an_independent_provider_registration_is_persisted(): void
    {
        $this->post('/register', [
            'name' => 'Provider One',
            'email' => 'provider@example.test',
            'account_type' => 'provider',
            'provider_type' => 'independent',
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
        ])->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'provider@example.test')->firstOrFail();
        $this->assertSame(AccountType::Provider, $user->account_type);
        $this->assertSame(ProviderType::Independent, $user->provider_type);
        $this->assertSame(OnboardingStatus::InProgress, $user->onboarding_status);
        $this->assertNotNull($user->onboarding_started_at);
    }

    public function test_a_member_cannot_submit_a_provider_type(): void
    {
        $this->from('/register')->post('/register', [
            'name' => 'Invalid Member',
            'email' => 'invalid@example.test',
            'account_type' => 'member',
            'provider_type' => 'agency',
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
        ])->assertSessionHasErrors('provider_type');
    }
}
