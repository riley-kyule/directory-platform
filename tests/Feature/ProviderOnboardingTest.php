<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\OnboardingStatus;
use App\Enums\ProfileStatus;
use App\Enums\ProviderType;
use App\Models\Location;
use App\Models\Package;
use App\Models\Profile;
use App\Models\TaxonomyOption;
use App\Models\User;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;

    private Location $sublocation;

    /** @var array<string, TaxonomyOption> */
    private array $options;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DirectoryDefaultsSeeder::class);

        $this->location = Location::query()->create([
            'country_code' => 'KE',
            'type' => 'city',
            'name' => 'Nairobi',
            'slug' => 'nairobi',
            'full_slug' => 'nairobi',
            'status' => 'published',
            'is_indexable' => true,
        ]);
        $this->sublocation = Location::query()->create([
            'parent_id' => $this->location->id,
            'country_code' => 'KE',
            'type' => 'neighbourhood',
            'name' => 'Westlands',
            'slug' => 'westlands',
            'full_slug' => 'nairobi/westlands',
            'status' => 'published',
            'is_indexable' => true,
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
    }

    public function test_members_cannot_open_provider_onboarding(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get(route('onboarding.index'))->assertForbidden();
    }

    public function test_independent_provider_can_submit_one_profile_and_package_request(): void
    {
        $provider = $this->provider(ProviderType::Independent);

        $response = $this->actingAs($provider)->post(route('onboarding.profiles.store'), $this->validProfileData());

        $response->assertRedirect(route('onboarding.index'))->assertSessionHasNoErrors();

        $profile = Profile::query()->firstOrFail();
        $this->assertSame($provider->id, $profile->owner_user_id);
        $this->assertSame(ProfileStatus::PendingReview, $profile->status);
        $this->assertCount(1, $profile->packageRequests);
        $this->assertSame('vip', $profile->packageRequests->first()->requestedPackage->code);
        $this->assertSame(['call', 'sms', 'whatsapp'], $profile->contacts()->orderBy('sort_order')->pluck('type')->all());
        $this->assertSame(OnboardingStatus::Submitted, $provider->refresh()->onboarding_status);

        $this->actingAs($provider)
            ->post(route('onboarding.profiles.store'), $this->validProfileData(['display_name' => 'Second Profile']))
            ->assertForbidden();
    }

    public function test_underage_profile_is_rejected_server_side(): void
    {
        $provider = $this->provider(ProviderType::Independent);

        $this->actingAs($provider)
            ->from(route('onboarding.profiles.create'))
            ->post(route('onboarding.profiles.store'), $this->validProfileData([
                'date_of_birth' => now()->subYears(17)->toDateString(),
            ]))
            ->assertSessionHasErrors('date_of_birth');

        $this->assertDatabaseCount('profiles', 0);
    }

    public function test_agency_registers_before_adding_individually_packaged_profiles(): void
    {
        $provider = $this->provider(ProviderType::Agency);

        $this->actingAs($provider)->post(route('onboarding.agency.store'), [
            'name' => 'City Companions',
            'description' => 'A professionally managed independent directory agency.',
        ])->assertRedirect(route('onboarding.index'));

        $this->assertNotNull($provider->refresh()->agency);

        $this->actingAs($provider)
            ->post(route('onboarding.profiles.store'), $this->validProfileData())
            ->assertRedirect(route('onboarding.index'))
            ->assertSessionHasNoErrors();

        $profile = Profile::query()->firstOrFail();
        $this->assertNull($profile->owner_user_id);
        $this->assertTrue($provider->agency->profiles()->whereKey($profile->id)->exists());
        $this->assertCount(1, $profile->packageRequests);
    }

    private function provider(ProviderType $type): User
    {
        return User::factory()->create([
            'account_type' => AccountType::Provider,
            'provider_type' => $type,
            'onboarding_status' => OnboardingStatus::InProgress,
            'onboarding_started_at' => now(),
        ]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function validProfileData(array $overrides = []): array
    {
        return array_replace([
            'display_name' => 'Jane Test',
            'description' => 'A complete profile biography with enough useful information for staff review.',
            'phone' => '+254700000001',
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
}
