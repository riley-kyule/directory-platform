<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\OnboardingStatus;
use App\Enums\PackageRequestStatus;
use App\Enums\ProfileStatus;
use App\Enums\ProviderType;
use App\Models\Location;
use App\Models\Package;
use App\Models\PackageDurationOption;
use App\Models\Profile;
use App\Models\ProfilePackageRequest;
use App\Models\Role;
use App\Models\TaxonomyOption;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffProfileReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $provider;

    private Profile $profile;

    private ProfilePackageRequest $packageRequest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessControlSeeder::class, DirectoryDefaultsSeeder::class]);

        $location = Location::query()->create([
            'country_code' => 'KE', 'type' => 'city', 'name' => 'Nairobi',
            'slug' => 'nairobi', 'full_slug' => 'nairobi', 'status' => 'published',
        ]);
        $sublocation = Location::query()->create([
            'parent_id' => $location->id, 'country_code' => 'KE', 'type' => 'neighbourhood',
            'name' => 'Westlands', 'slug' => 'westlands', 'full_slug' => 'nairobi/westlands',
            'status' => 'published',
        ]);
        $ethnicity = TaxonomyOption::query()->create([
            'type' => 'ethnicity', 'slug' => 'african', 'label' => 'African', 'is_active' => true,
        ]);

        $this->provider = User::factory()->create([
            'account_type' => AccountType::Provider,
            'provider_type' => ProviderType::Independent,
            'onboarding_status' => OnboardingStatus::Submitted,
        ]);
        $this->profile = Profile::query()->create([
            'owner_user_id' => $this->provider->id,
            'display_name' => 'Jane Review',
            'slug' => 'jane-review',
            'description' => 'A complete provider biography ready for staff review and activation.',
            'primary_location_id' => $location->id,
            'sublocation_id' => $sublocation->id,
            'gender_option_id' => TaxonomyOption::query()->ofType('gender')->firstOrFail()->id,
            'date_of_birth' => now()->subYears(25),
            'ethnicity_option_id' => $ethnicity->id,
            'build_option_id' => TaxonomyOption::query()->ofType('build')->firstOrFail()->id,
            'bust_size_option_id' => TaxonomyOption::query()->ofType('bust_size')->firstOrFail()->id,
            'allows_incall' => true,
            'allows_outcall' => true,
            'status' => ProfileStatus::PendingReview,
        ]);
        $this->packageRequest = $this->profile->packageRequests()->create([
            'requested_package_id' => Package::query()->where('code', 'vip')->firstOrFail()->id,
            'status' => PackageRequestStatus::Pending,
            'requested_by' => $this->provider->id,
            'requested_at' => now(),
        ]);
    }

    public function test_subscriber_cannot_access_staff_review_queue(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('staff.profiles.index'))
            ->assertForbidden();
    }

    public function test_csr_can_activate_profile_with_selected_package_duration(): void
    {
        $csr = $this->staff('csr');
        $package = Package::query()->where('code', 'vip')->firstOrFail();
        $duration = PackageDurationOption::query()->where('duration_days', 30)->firstOrFail();

        $this->actingAs($csr)->patch(route('staff.profiles.update', $this->packageRequest), [
            'decision' => 'approve',
            'assigned_package_id' => $package->id,
            'duration_option_id' => $duration->id,
            'reason' => 'Profile and package have been reviewed and approved.',
        ])->assertRedirect(route('staff.profiles.index'))->assertSessionHasNoErrors();

        $this->assertSame(ProfileStatus::Active, $this->profile->refresh()->status);
        $this->assertSame(PackageRequestStatus::Approved, $this->packageRequest->refresh()->status);
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $this->profile->expires_at->timestamp, 2);
        $this->assertDatabaseHas('profile_package_assignments', [
            'profile_id' => $this->profile->id,
            'package_id' => $package->id,
            'status' => 'active',
            'assigned_by' => $csr->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'profiles.activate', 'target_id' => $this->profile->id]);
        $this->assertSame(OnboardingStatus::Completed, $this->provider->refresh()->onboarding_status);
    }

    public function test_staff_package_change_is_retained_in_request_history(): void
    {
        $csr = $this->staff('csr');
        $basic = Package::query()->where('code', 'basic')->firstOrFail();

        $this->actingAs($csr)->patch(route('staff.profiles.update', $this->packageRequest), [
            'decision' => 'approve',
            'assigned_package_id' => $basic->id,
            'duration_option_id' => PackageDurationOption::query()->where('duration_days', 14)->value('id'),
            'reason' => 'Basic package assigned after reviewing the request.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(PackageRequestStatus::Changed, $this->packageRequest->refresh()->status);
        $this->assertSame($basic->id, $this->packageRequest->assigned_package_id);
    }

    public function test_csr_can_reject_profile_and_keep_it_private(): void
    {
        $csr = $this->staff('csr');

        $this->actingAs($csr)->patch(route('staff.profiles.update', $this->packageRequest), [
            'decision' => 'reject',
            'reason' => 'Required listing information could not be confirmed.',
        ])->assertRedirect(route('staff.profiles.index'))->assertSessionHasNoErrors();

        $this->assertSame(ProfileStatus::Rejected, $this->profile->refresh()->status);
        $this->assertSame(PackageRequestStatus::Rejected, $this->packageRequest->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'profiles.reject', 'target_id' => $this->profile->id]);
    }

    public function test_expiration_command_makes_elapsed_profiles_private(): void
    {
        $csr = $this->staff('csr');
        $duration = PackageDurationOption::query()->where('duration_days', 7)->firstOrFail();

        $this->actingAs($csr)->patch(route('staff.profiles.update', $this->packageRequest), [
            'decision' => 'approve',
            'assigned_package_id' => $this->packageRequest->requested_package_id,
            'duration_option_id' => $duration->id,
            'reason' => 'Approved for the selected seven day package period.',
        ]);

        $this->travel(8)->days();
        $this->artisan('profiles:expire')->expectsOutput('Expired 1 profile(s).')->assertSuccessful();

        $this->assertSame(ProfileStatus::Expired, $this->profile->refresh()->status);
        $this->assertDatabaseHas('profile_package_assignments', ['profile_id' => $this->profile->id, 'status' => 'expired']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'profiles.expire', 'target_id' => $this->profile->id]);
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }
}
