<?php

namespace Tests\Feature;

use App\Enums\ProfileStatus;
use App\Models\Location;
use App\Models\Profile;
use App\Models\Role;
use App\Models\TaxonomyOption;
use App\Models\User;
use App\Models\VerificationCheck;
use App\Notifications\ProfileVerificationExpiredNotification;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VerificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Profile $profile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessControlSeeder::class, DirectoryDefaultsSeeder::class]);
        $city = Location::query()->create([
            'country_code' => 'KE', 'type' => 'city', 'name' => 'Nairobi', 'slug' => 'nairobi',
            'full_slug' => 'nairobi', 'status' => 'published',
        ]);
        $neighbourhood = Location::query()->create([
            'parent_id' => $city->id, 'country_code' => 'KE', 'type' => 'neighbourhood',
            'name' => 'Westlands', 'slug' => 'westlands', 'full_slug' => 'nairobi/westlands',
            'status' => 'published',
        ]);
        $ethnicity = TaxonomyOption::query()->create([
            'type' => 'ethnicity', 'slug' => 'african', 'label' => 'African', 'is_active' => true,
        ]);
        $this->profile = Profile::query()->create([
            'owner_user_id' => User::factory()->create()->id,
            'display_name' => 'Verify Jane', 'slug' => 'verify-jane',
            'description' => 'A complete profile for internal verification evidence.',
            'primary_location_id' => $city->id, 'sublocation_id' => $neighbourhood->id,
            'gender_option_id' => TaxonomyOption::query()->ofType('gender')->firstOrFail()->id,
            'date_of_birth' => now()->subYears(25), 'ethnicity_option_id' => $ethnicity->id,
            'build_option_id' => TaxonomyOption::query()->ofType('build')->firstOrFail()->id,
        ]);
    }

    public function test_verification_evidence_is_restricted_from_seo_and_subscribers(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('staff.verification.index'))
            ->assertForbidden();
        $this->actingAs($this->staff('seo'))
            ->get(route('staff.verification.index'))
            ->assertForbidden();
        $this->actingAs($this->staff('csr'))
            ->get(route('staff.verification.index', ['profile' => $this->profile->id]))
            ->assertOk()
            ->assertSee('Verification evidence')
            ->assertSee('Verify Jane');
    }

    public function test_csr_can_record_encrypted_immutable_checks_and_complete_requirements(): void
    {
        $csr = $this->staff('csr');

        foreach (['adult_age', 'identity', 'publishing_rights'] as $type) {
            $this->actingAs($csr)->post(route('staff.verification.store'), [
                'profile_id' => $this->profile->id,
                'check_type' => $type,
                'status' => 'verified',
                'evidence_reference' => 'VAULT-'.$type,
                'notes' => 'Evidence was reviewed directly against the submitted profile information.',
                'expires_at' => now()->addYear()->toDateString(),
            ])->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->assertSame('verified', $this->profile->refresh()->verification_status);
        $this->assertDatabaseCount('verification_checks', 3);
        $check = VerificationCheck::query()->where('check_type', 'identity')->firstOrFail();
        $this->assertSame('VAULT-identity', $check->evidence_reference);
        $this->assertNotSame('VAULT-identity', $check->getRawOriginal('evidence_reference'));
        $this->assertStringNotContainsString('Evidence was reviewed', $check->getRawOriginal('notes'));
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $csr->id,
            'action' => 'verification.record',
            'target_id' => $this->profile->id,
        ]);

        $this->actingAs($csr)->post(route('staff.verification.store'), [
            'profile_id' => $this->profile->id,
            'check_type' => 'identity',
            'status' => 'rejected',
            'evidence_reference' => 'VAULT-identity-review-2',
            'notes' => 'The latest identity review produced a material mismatch requiring correction.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('rejected', $this->profile->refresh()->verification_status);
        $this->assertDatabaseCount('verification_checks', 4);
    }

    public function test_agency_authorization_cannot_be_recorded_for_independent_profile(): void
    {
        $this->actingAs($this->staff('csr'))->post(route('staff.verification.store'), [
            'profile_id' => $this->profile->id,
            'check_type' => 'agency_authorization',
            'status' => 'verified',
            'evidence_reference' => 'AGENCY-REF',
            'notes' => 'This should not be accepted for an independent provider profile.',
        ])->assertSessionHasErrors('check_type');

        $this->assertDatabaseCount('verification_checks', 0);
    }

    public function test_admin_or_csr_can_mark_all_missing_checks_verified_by_explicit_override(): void
    {
        $csr = $this->staff('csr');

        $this->actingAs($csr)->post(route('staff.verification.override'), [
            'profile_id' => $this->profile->id,
            'reason' => 'CSR has approved an exceptional manual verification override for this profile.',
            'confirm_override' => '1',
        ])->assertRedirect(route('staff.verification.index', ['profile' => $this->profile->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame('verified', $this->profile->refresh()->verification_status);
        $this->assertSame(3, $this->profile->verificationChecks()->where('is_override', true)->count());
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $csr->id,
            'action' => 'verification.override',
            'target_id' => $this->profile->id,
        ]);
    }

    public function test_subscriber_cannot_use_verification_override(): void
    {
        $this->actingAs(User::factory()->create())->post(route('staff.verification.override'), [
            'profile_id' => $this->profile->id,
            'reason' => 'A subscriber must never be allowed to perform this override operation.',
            'confirm_override' => '1',
        ])->assertForbidden();

        $this->assertSame('unverified', $this->profile->refresh()->verification_status);
    }

    public function test_expired_verification_makes_an_active_profile_private_and_notifies_owner(): void
    {
        Notification::fake();
        $owner = $this->profile->owner;
        foreach (['adult_age', 'identity', 'publishing_rights'] as $type) {
            $this->profile->verificationChecks()->create([
                'check_type' => $type,
                'status' => 'verified',
                'evidence_reference' => 'EXPIRING-'.$type,
                'notes' => 'Time-limited verification fixture for expiration enforcement.',
                'checked_at' => now()->subYear(),
                'expires_at' => now()->subDay(),
            ]);
        }
        $this->profile->update([
            'verification_status' => 'verified',
            'status' => ProfileStatus::Active,
        ]);

        $this->artisan('verification:refresh-statuses')
            ->expectsOutput('Updated 1 verification status(es).')
            ->assertSuccessful();

        $this->assertSame(ProfileStatus::Deactivated, $this->profile->refresh()->status);
        $this->assertSame('unverified', $this->profile->verification_status);
        Notification::assertSentTo($owner, ProfileVerificationExpiredNotification::class);
    }

    public function test_verification_audit_reports_noncompliant_active_profiles_and_clears_after_override(): void
    {
        $this->profile->update(['status' => ProfileStatus::Active]);

        $this->artisan('profiles:audit-verification')
            ->expectsOutputToContain('1 active profile(s) are excluded from public discovery')
            ->assertFailed();

        $this->actingAs($this->staff('admin'))->post(route('staff.verification.override'), [
            'profile_id' => $this->profile->id,
            'reason' => 'Administrator accepts responsibility for this audit remediation override.',
            'confirm_override' => '1',
        ])->assertSessionHasNoErrors();

        $this->artisan('profiles:audit-verification')
            ->expectsOutput('All active profiles satisfy current verification requirements.')
            ->assertSuccessful();
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }
}
