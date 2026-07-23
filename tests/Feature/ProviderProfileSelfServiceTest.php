<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\OnboardingStatus;
use App\Enums\PackageRequestStatus;
use App\Enums\ProfileStatus;
use App\Enums\ProviderType;
use App\Models\Agency;
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

class ProviderProfileSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Profile $profile;

    private Location $city;

    private Location $area;

    /** @var array<string, TaxonomyOption> */
    private array $options;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessControlSeeder::class, DirectoryDefaultsSeeder::class]);
        Queue::fake();

        $this->city = Location::query()->create([
            'country_code' => 'KE', 'type' => 'city', 'name' => 'Nairobi', 'slug' => 'nairobi',
            'full_slug' => 'nairobi', 'status' => 'published',
        ]);
        $this->area = Location::query()->create([
            'parent_id' => $this->city->id, 'country_code' => 'KE', 'type' => 'neighbourhood',
            'name' => 'Westlands', 'slug' => 'westlands', 'full_slug' => 'nairobi/westlands',
            'status' => 'published',
        ]);
        TaxonomyOption::query()->create([
            'type' => 'ethnicity',
            'slug' => 'african',
            'label' => 'African',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        foreach (['gender', 'ethnicity', 'build', 'service'] as $type) {
            $this->options[$type] = TaxonomyOption::query()->ofType($type)->firstOrFail();
        }

        $this->owner = User::factory()->create([
            'account_type' => AccountType::Provider,
            'provider_type' => ProviderType::Independent,
            'onboarding_status' => OnboardingStatus::Completed,
        ]);
        $this->profile = Profile::query()->create([
            'owner_user_id' => $this->owner->id,
            'display_name' => 'Owner Jane',
            'slug' => 'owner-jane',
            'description' => 'A complete provider profile with enough information for owner self service.',
            'primary_location_id' => $this->city->id,
            'sublocation_id' => $this->area->id,
            'gender_option_id' => $this->options['gender']->id,
            'date_of_birth' => now()->subYears(25),
            'ethnicity_option_id' => $this->options['ethnicity']->id,
            'build_option_id' => $this->options['build']->id,
            'bust_size_option_id' => TaxonomyOption::query()->ofType('bust_size')->firstOrFail()->id,
            'allows_incall' => true,
            'status' => ProfileStatus::Active,
            'published_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);
        $this->profile->contacts()->createMany([
            ['type' => 'call', 'normalized_value' => '+254700000001', 'display_value' => '+254700000001', 'sort_order' => 10],
            ['type' => 'sms', 'normalized_value' => '+254700000001', 'display_value' => '+254700000001', 'sort_order' => 20],
        ]);
        $this->profile->services()->sync([$this->options['service']->id]);
    }

    public function test_owner_can_view_and_edit_an_active_profile(): void
    {
        $this->actingAs($this->owner)
            ->get(route('provider.profiles.show', $this->profile))
            ->assertOk()
            ->assertSee('Edit profile')
            ->assertSee('Manage media');

        $this->actingAs($this->owner)
            ->patch(route('provider.profiles.update', $this->profile), $this->validUpdate([
                'display_name' => 'Updated Jane',
                'whatsapp_enabled' => '1',
            ]))
            ->assertRedirect(route('provider.profiles.show', $this->profile))
            ->assertSessionHasNoErrors();

        $this->assertSame('Updated Jane', $this->profile->refresh()->display_name);
        $this->assertSame('owner-jane', $this->profile->slug);
        $this->assertTrue($this->profile->contacts()->where('type', 'whatsapp')->exists());
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $this->owner->id,
            'action' => 'profiles.owner-update',
            'target_id' => $this->profile->id,
        ]);
    }

    public function test_non_owner_cannot_view_or_edit_profile_management(): void
    {
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('provider.profiles.show', $this->profile))->assertForbidden();
        $this->actingAs($other)
            ->patch(route('provider.profiles.update', $this->profile), $this->validUpdate())
            ->assertForbidden();
    }

    public function test_expired_owner_cannot_edit_but_can_request_renewal(): void
    {
        $this->profile->update([
            'status' => ProfileStatus::Expired,
            'expires_at' => now()->subDay(),
        ]);
        $package = Package::query()->where('code', 'premium')->firstOrFail();

        $this->actingAs($this->owner)
            ->get(route('provider.profiles.show', $this->profile))
            ->assertOk()
            ->assertDontSee('Edit profile')
            ->assertSee('Request renewal');
        $this->actingAs($this->owner)
            ->get(route('provider.profiles.edit', $this->profile))
            ->assertStatus(409);
        $this->actingAs($this->owner)
            ->patch(route('provider.profiles.update', $this->profile), $this->validUpdate())
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->post(route('provider.profiles.renewal.store', $this->profile), [
                'requested_package_id' => $package->id,
            ])
            ->assertRedirect(route('provider.profiles.show', $this->profile))
            ->assertSessionHasNoErrors();

        $this->assertSame(ProfileStatus::PendingReview, $this->profile->refresh()->status);
        $this->assertDatabaseHas('profile_package_requests', [
            'profile_id' => $this->profile->id,
            'requested_package_id' => $package->id,
            'status' => PackageRequestStatus::Pending->value,
            'requested_by' => $this->owner->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'profiles.renewal-request',
            'target_id' => $this->profile->id,
        ]);
    }

    public function test_pending_or_banned_profile_cannot_create_a_renewal_request(): void
    {
        $package = Package::query()->where('code', 'basic')->firstOrFail();

        foreach ([ProfileStatus::PendingReview, ProfileStatus::Banned] as $status) {
            $this->profile->update(['status' => $status]);
            $this->actingAs($this->owner)
                ->post(route('provider.profiles.renewal.store', $this->profile), [
                    'requested_package_id' => $package->id,
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('profile_package_requests', 0);
    }

    public function test_renewal_request_enters_the_existing_staff_activation_queue(): void
    {
        $this->profile->update([
            'status' => ProfileStatus::Expired,
            'expires_at' => now()->subDay(),
        ]);
        $this->profile->images()->create([
            'storage_directory' => 'test/renewal',
            'sort_order' => 10,
            'status' => 'pending_review',
            'width' => 800,
            'height' => 1000,
            'aspect_ratio' => 0.8,
            'mime_type' => 'image/webp',
            'file_size' => 1000,
            'exact_hash' => hash('sha256', 'renewal-image'),
            'derivatives' => ['thumb' => ['file' => 'thumb.webp', 'width' => 320, 'height' => 400, 'size' => 100]],
        ]);
        $package = Package::query()->where('code', 'premium')->firstOrFail();

        $this->actingAs($this->owner)->post(route('provider.profiles.renewal.store', $this->profile), [
            'requested_package_id' => $package->id,
        ])->assertSessionHasNoErrors();

        $packageRequest = $this->profile->packageRequests()->latest()->firstOrFail();
        $csr = User::factory()->create();
        $csr->roles()->attach(Role::query()->where('slug', 'csr')->firstOrFail());

        $this->actingAs($csr)
            ->get(route('staff.profiles.index'))
            ->assertOk()
            ->assertSee('Owner Jane');
        $this->actingAs($csr)->patch(route('staff.profiles.update', $packageRequest), [
            'decision' => 'approve',
            'assigned_package_id' => $package->id,
            'duration_option_id' => PackageDurationOption::query()->where('duration_days', 30)->value('id'),
            'reason' => 'Renewal package and profile approved after staff review.',
        ])->assertRedirect(route('staff.profiles.index'))->assertSessionHasNoErrors();

        $this->assertSame(ProfileStatus::Active, $this->profile->refresh()->status);
        $this->assertSame(PackageRequestStatus::Approved, $packageRequest->refresh()->status);
        $this->assertDatabaseHas('profile_package_assignments', [
            'profile_id' => $this->profile->id,
            'package_id' => $package->id,
            'status' => 'active',
            'request_id' => $packageRequest->id,
        ]);
    }

    public function test_agency_owner_can_manage_an_assigned_profile(): void
    {
        $agencyOwner = User::factory()->create([
            'account_type' => AccountType::Provider,
            'provider_type' => ProviderType::Agency,
        ]);
        $agency = Agency::query()->create([
            'owner_user_id' => $agencyOwner->id,
            'name' => 'Agency One',
            'slug' => 'agency-one',
            'status' => 'active',
        ]);
        $this->profile->update(['owner_user_id' => null]);
        $agency->profiles()->attach($this->profile, [
            'assigned_by' => $agencyOwner->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($agencyOwner)
            ->get(route('provider.profiles.edit', $this->profile))
            ->assertOk()
            ->assertSee('Edit profile');
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validUpdate(array $overrides = []): array
    {
        return array_replace([
            'display_name' => 'Owner Jane',
            'description' => 'An updated provider biography with enough information to pass validation safely.',
            'phone' => '+254700000001',
            'whatsapp_enabled' => '0',
            'telegram_phone_enabled' => '0',
            'primary_location_id' => $this->city->id,
            'sublocation_id' => $this->area->id,
            'gender_option_id' => $this->options['gender']->id,
            'ethnicity_option_id' => $this->options['ethnicity']->id,
            'build_option_id' => $this->options['build']->id,
            'bust_size_option_id' => TaxonomyOption::query()->ofType('bust_size')->firstOrFail()->id,
            'allows_incall' => '1',
            'allows_outcall' => '0',
            'service_ids' => [$this->options['service']->id],
        ], $overrides);
    }
}
