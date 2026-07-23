<?php

namespace Tests\Feature;

use App\Models\DirectorySetting;
use App\Models\Role;
use App\Models\User;
use App\Services\TotpService;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivilegedMfaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        DirectorySetting::query()
            ->whereKey('security.privileged_mfa_enforced')
            ->firstOrFail()
            ->update(['value' => '1']);
    }

    public function test_subscribers_are_not_forced_through_staff_mfa(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_privileged_user_must_configure_mfa_and_secret_is_encrypted(): void
    {
        $admin = $this->staff('admin');
        $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('mfa.setup'));

        $setup = $this->get(route('mfa.setup'))->assertOk()->assertSee('Secure your staff account');
        $secret = session('mfa_setup_secret');
        $code = app(TotpService::class)->currentCode($secret);

        $response = $this->post(route('mfa.confirm'), ['code' => $code])
            ->assertOk()
            ->assertSee('Save your recovery codes');

        $admin->refresh();
        $this->assertNotNull($admin->two_factor_confirmed_at);
        $this->assertSame($secret, $admin->two_factor_secret);
        $this->assertNotSame($secret, $admin->getRawOriginal('two_factor_secret'));
        $this->assertCount(8, $admin->two_factor_recovery_codes);
        $this->assertCount(8, $response->viewData('recoveryCodes'));
        $this->get(route('dashboard'))->assertOk();
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'security.mfa-enabled',
        ]);
    }

    public function test_new_privileged_session_requires_code_and_recovery_code_is_single_use(): void
    {
        $admin = $this->staff('admin');
        $setup = $this->actingAs($admin)->get(route('mfa.setup'));
        $secret = session('mfa_setup_secret');
        $recoveryView = $this->post(route('mfa.confirm'), [
            'code' => app(TotpService::class)->currentCode($secret),
        ]);
        $recoveryCode = $recoveryView->viewData('recoveryCodes')[0];

        $this->withSession(['mfa_passed_at' => now()->subDay()->timestamp])
            ->get(route('dashboard'))
            ->assertRedirect(route('mfa.challenge'));

        $this->post(route('mfa.verify'), ['credential' => $recoveryCode])
            ->assertRedirect(route('dashboard'));
        $this->assertCount(7, $admin->refresh()->two_factor_recovery_codes);

        $this->withSession(['mfa_passed_at' => now()->subDay()->timestamp])
            ->post(route('mfa.verify'), ['credential' => $recoveryCode])
            ->assertSessionHasErrors('credential');
    }

    public function test_non_privileged_user_cannot_open_mfa_setup(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('mfa.setup'))
            ->assertForbidden();
    }

    public function test_totp_matches_rfc_counter_vector_at_six_digits(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $this->assertSame('287082', app(TotpService::class)->currentCode($secret, 59));
        $this->assertTrue(app(TotpService::class)->verify($secret, '287082', 59));
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }
}
