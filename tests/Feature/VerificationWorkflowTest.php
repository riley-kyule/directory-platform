<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Profile;
use App\Models\Role;
use App\Models\TaxonomyOption;
use App\Models\User;
use App\Models\VerificationCheck;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }
}
