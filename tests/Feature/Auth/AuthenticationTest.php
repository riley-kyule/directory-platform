<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertTrue($user->refresh()->last_seen_at->isCurrentMinute());
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_authenticated_navigation_uses_a_javascript_free_logout_form(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk()
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('<button type="submit"', false)
            ->assertDontSee('onclick="event.preventDefault(); this.closest(\'form\').submit();"', false);
    }

    public function test_authenticated_activity_refreshes_last_seen_at_without_writing_on_every_request(): void
    {
        $user = User::factory()->create(['last_seen_at' => now()->subMinutes(10)]);

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $refreshedAt = $user->refresh()->last_seen_at;
        $this->assertTrue($refreshedAt->isCurrentMinute());

        $this->travel(2)->minutes();
        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->assertTrue($user->refresh()->last_seen_at->equalTo($refreshedAt));
    }
}
