<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\ProfileStatus;
use App\Enums\ProviderType;
use App\Models\Agency;
use App\Models\DirectorySetting;
use App\Models\Location;
use App\Models\Package;
use App\Models\Profile;
use App\Models\Role;
use App\Models\TaxonomyOption;
use App\Models\User;
use App\Services\PolicyAcceptanceService;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffProfileCreationTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;

    private Location $sublocation;

    /** @var array<string, TaxonomyOption> */
    private array $options;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessControlSeeder::class, DirectoryDefaultsSeeder::class]);

        $this->location = Location::query()->create([
            'country_code' => 'KE', 'type' => 'city', 'name' => 'Nairobi', 'slug' => 'nairobi',
            'full_slug' => 'nairobi', 'status' => 'published', 'is_indexable' => true,
        ]);
        $this->sublocation = Location::query()->create([
            'parent_id' => $this->location->id, 'country_code' => 'KE', 'type' => 'neighbourhood',
            'name' => 'Westlands', 'slug' => 'westlands', 'full_slug' => 'nairobi/westlands',
            'status' => 'published', 'is_indexable' => true,
        ]);
        TaxonomyOption::query()->create(['type' => 'ethnicity', 'slug' => 'african', 'label' => 'African', 'sort_order' => 10, 'is_active' => true]);
        foreach (['gender', 'ethnicity', 'build', 'service'] as $type) {
            $this->options[$type] = TaxonomyOption::query()->ofType($type)->firstOrFail();
        }
    }

    public function test_create_and_store_require_the_profiles_create_permission(): void
    {
        $seo = $this->staff('seo');

        $this->actingAs($seo)->get(route('staff.directory.create'))->assertForbidden();
        $this->actingAs($seo)->post(route('staff.directory.store'), $this->listingData())->assertForbidden();
    }

    public function test_csr_can_create_a_listing_for_an_existing_provider(): void
    {
        $csr = $this->staff('csr');
        $provider = User::factory()->create(['account_type' => AccountType::Provider, 'provider_type' => ProviderType::Independent]);

        $response = $this->actingAs($csr)->post(route('staff.directory.store'), $this->listingData([
            'owner_mode' => 'existing_user',
            'existing_user_id' => $provider->id,
        ]));

        $profile = Profile::query()->firstOrFail();
        $response->assertRedirect(route('staff.directory.show', $profile))->assertSessionHasNoErrors();
        $this->assertSame($provider->id, $profile->owner_user_id);
        $this->assertSame(ProfileStatus::Draft, $profile->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'profiles.staff-create', 'target_type' => 'profile', 'target_id' => $profile->id, 'actor_user_id' => $csr->id,
        ]);
    }

    public function test_csr_can_create_a_listing_with_a_brand_new_provider_account(): void
    {
        $csr = $this->staff('csr');

        $this->actingAs($csr)->post(route('staff.directory.store'), $this->listingData([
            'owner_mode' => 'new_user',
            'new_user_name' => 'New Provider',
            'new_user_email' => 'new-provider@example.test',
            'new_user_password' => 'Password!2345',
        ]))->assertSessionHasNoErrors();

        $owner = User::query()->where('email', 'new-provider@example.test')->firstOrFail();
        $this->assertSame(AccountType::Provider, $owner->account_type);
        $this->assertSame(ProviderType::Independent, $owner->provider_type);
        $this->assertNotNull($owner->email_verified_at);

        $profile = Profile::query()->firstOrFail();
        $this->assertSame($owner->id, $profile->owner_user_id);
    }

    public function test_csr_can_attach_a_listing_to_an_existing_agency(): void
    {
        $csr = $this->staff('csr');
        $agencyOwner = User::factory()->create(['account_type' => AccountType::Provider, 'provider_type' => ProviderType::Agency]);
        $agency = Agency::query()->create(['owner_user_id' => $agencyOwner->id, 'name' => 'Elite Models', 'slug' => 'elite-models', 'status' => 'active']);

        $this->actingAs($csr)->post(route('staff.directory.store'), $this->listingData([
            'owner_mode' => 'agency',
            'agency_id' => $agency->id,
        ]))->assertSessionHasNoErrors();

        $profile = Profile::query()->firstOrFail();
        $this->assertNull($profile->owner_user_id);
        $this->assertTrue($agency->profiles()->whereKey($profile->id)->wherePivotNull('unassigned_at')->exists());
    }

    public function test_attaching_to_an_agency_at_its_profile_limit_is_refused(): void
    {
        $csr = $this->staff('csr');
        $agencyOwner = User::factory()->create(['account_type' => AccountType::Provider, 'provider_type' => ProviderType::Agency]);
        $agency = Agency::query()->create(['owner_user_id' => $agencyOwner->id, 'name' => 'Full House', 'slug' => 'full-house', 'status' => 'active']);

        DirectorySetting::query()->updateOrCreate(['key' => 'profiles.agency_limit'], ['value' => '1', 'value_type' => 'integer', 'group' => 'profiles']);
        $existingProfile = Profile::query()->create([
            'display_name' => 'Existing', 'slug' => 'existing-agency-profile',
            'description' => 'An existing agency profile occupying the only roster slot.',
            'primary_location_id' => $this->location->id, 'sublocation_id' => $this->sublocation->id,
            'gender_option_id' => $this->options['gender']->id, 'date_of_birth' => now()->subYears(25),
            'ethnicity_option_id' => $this->options['ethnicity']->id, 'build_option_id' => $this->options['build']->id,
        ]);
        $agency->profiles()->attach($existingProfile, ['assigned_by' => $agencyOwner->id, 'assigned_at' => now()]);

        $this->actingAs($csr)->post(route('staff.directory.store'), $this->listingData([
            'owner_mode' => 'agency',
            'agency_id' => $agency->id,
        ]))->assertSessionHasErrors('agency_id');

        $this->assertSame(1, Profile::query()->count());
    }

    public function test_staff_can_submit_a_staff_created_draft_for_review(): void
    {
        $csr = $this->staff('csr');
        $provider = User::factory()->create(['account_type' => AccountType::Provider, 'provider_type' => ProviderType::Independent]);

        $this->actingAs($csr)->post(route('staff.directory.store'), $this->listingData([
            'owner_mode' => 'existing_user',
            'existing_user_id' => $provider->id,
        ]));
        $profile = Profile::query()->firstOrFail();

        $profile->images()->create([
            'storage_directory' => 'test/image', 'sort_order' => 10, 'status' => 'pending_review',
            'width' => 800, 'height' => 1000, 'aspect_ratio' => 0.8, 'mime_type' => 'image/webp', 'file_size' => 1000,
            'exact_hash' => hash('sha256', 'staff-created-'.$profile->id),
            'derivatives' => ['thumb' => ['file' => 'thumb-320.webp', 'width' => 320, 'height' => 400, 'size' => 100]],
        ]);

        $this->actingAs($csr)->get(route('staff.directory.show', $profile))
            ->assertOk()
            ->assertSee('Complete onboarding')
            ->assertSee('Confirm the provider has agreed to the');

        $outstanding = app(PolicyAcceptanceService::class)->outstanding('profile_submission', $csr, $profile)->pluck('id')->all();

        $this->actingAs($csr)->post(route('onboarding.profiles.submit', $profile), [
            'policy_acceptances' => $outstanding,
        ])->assertRedirect(route('onboarding.index'));

        $this->assertSame(ProfileStatus::PendingReview, $profile->refresh()->status);
        $this->assertDatabaseHas('policy_acceptances', [
            'profile_id' => $profile->id,
            'user_id' => $csr->id,
            'action' => 'profile_submission',
        ]);
    }

    public function test_staff_with_media_upload_permission_can_reach_media_routes_for_a_profile_they_did_not_onboard(): void
    {
        $csr = $this->staff('csr');
        $provider = User::factory()->create(['account_type' => AccountType::Provider, 'provider_type' => ProviderType::Independent]);
        $profile = Profile::query()->create([
            'owner_user_id' => $provider->id,
            'display_name' => 'Owner Created', 'slug' => 'owner-created',
            'description' => 'A profile owned by the provider, not created by this staff member.',
            'primary_location_id' => $this->location->id, 'sublocation_id' => $this->sublocation->id,
            'gender_option_id' => $this->options['gender']->id, 'date_of_birth' => now()->subYears(25),
            'ethnicity_option_id' => $this->options['ethnicity']->id, 'build_option_id' => $this->options['build']->id,
            'status' => ProfileStatus::Draft,
        ]);

        $this->actingAs($csr)->get(route('profiles.media.index', $profile))->assertOk();
    }

    /** @param  array<string, mixed>  $overrides */
    private function listingData(array $overrides = []): array
    {
        return array_replace([
            'owner_mode' => 'existing_user',
            'display_name' => 'Staff Created Jane',
            'description' => 'A complete profile biography with enough useful information for staff review.',
            'phone' => '+254700000002',
            'whatsapp_enabled' => '1',
            'telegram_phone_enabled' => '0',
            'primary_location_id' => $this->location->id,
            'sublocation_id' => $this->sublocation->id,
            'gender_option_id' => $this->options['gender']->id,
            'date_of_birth' => now()->subYears(25)->toDateString(),
            'ethnicity_option_id' => $this->options['ethnicity']->id,
            'build_option_id' => $this->options['build']->id,
            'bust_size_option_id' => TaxonomyOption::query()->ofType('bust_size')->firstOrFail()->id,
            'allows_incall' => '1',
            'allows_outcall' => '1',
            'service_ids' => [$this->options['service']->id],
            'requested_package_id' => Package::query()->where('code', 'vip')->firstOrFail()->id,
        ], $overrides);
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }
}
