<?php

namespace Tests\Feature;

use App\Enums\ProfileStatus;
use App\Jobs\PublishProfileImages;
use App\Models\Location;
use App\Models\Package;
use App\Models\PackageDurationOption;
use App\Models\Profile;
use App\Models\Role;
use App\Models\TaxonomyOption;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
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
            'name' => 'Westlands', 'slug' => 'westlands', 'full_slug' => 'nairobi/westlands', 'status' => 'published',
        ]);
        $ethnicity = TaxonomyOption::query()->create([
            'type' => 'ethnicity', 'slug' => 'african', 'label' => 'African', 'is_active' => true,
        ]);
        $this->profile = Profile::query()->create([
            'owner_user_id' => User::factory()->create()->id,
            'display_name' => 'Managed Jane', 'slug' => 'managed-jane',
            'description' => 'A complete active profile for staff lifecycle management.',
            'primary_location_id' => $city->id, 'sublocation_id' => $neighbourhood->id,
            'gender_option_id' => TaxonomyOption::query()->ofType('gender')->firstOrFail()->id,
            'date_of_birth' => now()->subYears(25), 'ethnicity_option_id' => $ethnicity->id,
            'build_option_id' => TaxonomyOption::query()->ofType('build')->firstOrFail()->id,
            'allows_incall' => true, 'status' => ProfileStatus::Active,
            'published_at' => now(), 'last_activated_at' => now(),
            'expires_at' => now()->addMonth(), 'listing_rank' => 100,
        ]);
        $this->profile->packageAssignments()->create([
            'package_id' => Package::query()->where('code', 'vip')->value('id'),
            'starts_at' => now(), 'expires_at' => now()->addMonth(), 'status' => 'active',
            'assigned_by' => $this->profile->owner_user_id, 'assignment_source' => 'manual', 'reason' => 'Initial package.',
        ]);
        $this->profile->images()->create([
            'storage_directory' => 'review/managed-jane', 'sort_order' => 10, 'status' => 'pending_review',
            'width' => 800, 'height' => 1000, 'aspect_ratio' => 0.8, 'mime_type' => 'image/webp',
            'file_size' => 1000, 'exact_hash' => hash('sha256', 'managed-jane'),
            'derivatives' => ['card' => ['file' => 'card-640.webp', 'width' => 640, 'height' => 800, 'size' => 100]],
        ]);
        Queue::fake();
    }

    public function test_only_admin_and_csr_can_access_private_listing_workspace(): void
    {
        $this->actingAs(User::factory()->create())->get(route('staff.directory.index'))->assertForbidden();
        $this->actingAs($this->staff('seo'))->get(route('staff.directory.index'))->assertForbidden();
        $csr = $this->staff('csr');
        $this->actingAs($csr)->get(route('staff.directory.index'))
            ->assertOk()
            ->assertSeeInOrder(['VIP Escorts', 'Premium Escorts', 'Basic Escorts', 'New Escorts', 'Private Escorts'])
            ->assertSee('Managed Jane');
        $this->actingAs($csr)->get(route('staff.directory.show', $this->profile))
            ->assertOk()
            ->assertSee('Make profile private')
            ->assertSee('Package history');
    }

    public function test_csr_can_deactivate_an_active_profile_with_audit_history(): void
    {
        $this->actingAs($this->staff('csr'))->patch(route('staff.directory.update', $this->profile), [
            'action' => 'deactivate', 'reason' => 'Provider requested a temporary listing pause.',
        ])->assertRedirect(route('staff.directory.show', $this->profile))->assertSessionHasNoErrors();

        $this->assertSame(ProfileStatus::Deactivated, $this->profile->refresh()->status);
        $this->assertDatabaseHas('profile_package_assignments', ['profile_id' => $this->profile->id, 'status' => 'deactivated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'profiles.deactivate', 'target_id' => $this->profile->id]);
    }

    public function test_csr_can_remove_a_package_and_make_the_profile_private(): void
    {
        $this->actingAs($this->staff('csr'))->patch(route('staff.directory.update', $this->profile), [
            'action' => 'remove_package', 'reason' => 'The assigned package was withdrawn by staff.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(ProfileStatus::Deactivated, $this->profile->refresh()->status);
        $this->assertDatabaseHas('profile_package_assignments', ['profile_id' => $this->profile->id, 'status' => 'removed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'profiles.remove_package', 'target_id' => $this->profile->id]);
    }

    public function test_csr_can_ban_an_active_profile(): void
    {
        $this->actingAs($this->staff('csr'))->patch(route('staff.directory.update', $this->profile), [
            'action' => 'ban', 'reason' => 'Confirmed serious policy breach requiring removal.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(ProfileStatus::Banned, $this->profile->refresh()->status);
        $this->assertDatabaseHas('profile_package_assignments', ['profile_id' => $this->profile->id, 'status' => 'banned']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'profiles.ban', 'target_id' => $this->profile->id]);
    }

    public function test_csr_can_manually_renew_an_expired_profile_with_package_and_duration(): void
    {
        $this->profile->update(['status' => ProfileStatus::Expired, 'expires_at' => now()->subDay()]);
        $this->profile->packageAssignments()->update(['status' => 'expired', 'expires_at' => now()->subDay()]);
        $basic = Package::query()->where('code', 'basic')->firstOrFail();
        $duration = PackageDurationOption::query()->where('duration_days', 30)->firstOrFail();

        $this->actingAs($this->staff('csr'))->patch(route('staff.directory.update', $this->profile), [
            'action' => 'renew', 'package_id' => $basic->id, 'duration_option_id' => $duration->id,
            'reason' => 'Manual renewal confirmed for a thirty day Basic package.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(ProfileStatus::Active, $this->profile->refresh()->status);
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $this->profile->expires_at->timestamp, 2);
        $this->assertDatabaseHas('profile_package_assignments', [
            'profile_id' => $this->profile->id, 'package_id' => $basic->id,
            'status' => 'active', 'assignment_source' => 'manual_renewal',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'profiles.renew', 'target_id' => $this->profile->id]);
        Queue::assertPushed(PublishProfileImages::class, fn ($job) => $job->profileId === $this->profile->id);
    }

    public function test_banned_profile_cannot_be_renewed(): void
    {
        $this->profile->update(['status' => ProfileStatus::Banned]);

        $this->actingAs($this->staff('csr'))->patch(route('staff.directory.update', $this->profile), [
            'action' => 'renew',
            'package_id' => Package::query()->where('code', 'basic')->value('id'),
            'duration_option_id' => PackageDurationOption::query()->where('duration_days', 30)->value('id'),
            'reason' => 'Attempting to renew a banned profile should fail.',
        ])->assertStatus(409);

        $this->assertSame(ProfileStatus::Banned, $this->profile->refresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'profiles.renew', 'target_id' => $this->profile->id]);
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }
}
