<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\OnboardingStatus;
use App\Enums\ProfileStatus;
use App\Enums\ProviderType;
use App\Models\Location;
use App\Models\Package;
use App\Models\PolicyVersion;
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

    public function test_independent_provider_can_save_one_draft_and_package_request(): void
    {
        $provider = $this->provider(ProviderType::Independent);

        $response = $this->actingAs($provider)->post(route('onboarding.profiles.store'), $this->validProfileData());

        $response->assertRedirect(route('onboarding.index'))->assertSessionHasNoErrors();

        $profile = Profile::query()->firstOrFail();
        $this->assertSame($provider->id, $profile->owner_user_id);
        $this->assertSame(ProfileStatus::Draft, $profile->status);
        $this->assertCount(1, $profile->packageRequests);
        $this->assertSame('vip', $profile->packageRequests->first()->requestedPackage->code);
        $this->assertSame(['call', 'sms', 'whatsapp'], $profile->contacts()->orderBy('sort_order')->pluck('type')->all());
        $this->assertSame(OnboardingStatus::InProgress, $provider->refresh()->onboarding_status);

        $this->actingAs($provider)
            ->post(route('onboarding.profiles.store'), $this->validProfileData(['display_name' => 'Second Profile']))
            ->assertForbidden();
    }

    public function test_profile_owner_can_explicitly_submit_a_completed_draft(): void
    {
        $provider = $this->provider(ProviderType::Independent);
        $this->actingAs($provider)->post(route('onboarding.profiles.store'), $this->validProfileData());
        $profile = Profile::query()->firstOrFail();
        $this->processedImage($profile);

        $this->actingAs($provider)
            ->post(route('onboarding.profiles.submit', $profile))
            ->assertRedirect(route('onboarding.index'));

        $this->assertSame(ProfileStatus::PendingReview, $profile->refresh()->status);
        $this->assertSame(OnboardingStatus::Submitted, $provider->refresh()->onboarding_status);
    }

    public function test_profile_submission_records_required_provider_policy_acceptance(): void
    {
        $provider = $this->provider(ProviderType::Independent);
        $this->actingAs($provider)->post(route('onboarding.profiles.store'), $this->validProfileData());
        $profile = Profile::query()->firstOrFail();
        $this->processedImage($profile);
        $content = str_repeat('Provider publishing and conduct standards apply to every submitted profile. ', 3);
        $policy = PolicyVersion::query()->create([
            'policy_type' => 'provider',
            'version' => '2026-07',
            'title' => 'Provider Policy',
            'content' => $content,
            'content_hash' => hash('sha256', $content),
            'published_at' => now(),
            'requires_reacceptance' => true,
        ]);

        $this->actingAs($provider)
            ->post(route('onboarding.profiles.submit', $profile))
            ->assertSessionHasErrors('policy_acceptances');
        $this->assertSame(ProfileStatus::Draft, $profile->refresh()->status);

        $this->actingAs($provider)
            ->post(route('onboarding.profiles.submit', $profile), [
                'policy_acceptances' => [$policy->id],
            ])
            ->assertRedirect(route('onboarding.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('policy_acceptances', [
            'policy_version_id' => $policy->id,
            'user_id' => $provider->id,
            'profile_id' => $profile->id,
            'action' => 'profile_submission',
        ]);
    }

    public function test_another_provider_cannot_submit_someone_elses_draft(): void
    {
        $provider = $this->provider(ProviderType::Independent);
        $this->actingAs($provider)->post(route('onboarding.profiles.store'), $this->validProfileData());

        $this->actingAs($this->provider(ProviderType::Independent))
            ->post(route('onboarding.profiles.submit', Profile::query()->firstOrFail()))
            ->assertForbidden();
    }

    public function test_draft_without_processed_media_cannot_be_submitted(): void
    {
        $provider = $this->provider(ProviderType::Independent);
        $this->actingAs($provider)->post(route('onboarding.profiles.store'), $this->validProfileData());

        $this->actingAs($provider)
            ->from(route('onboarding.index'))
            ->post(route('onboarding.profiles.submit', Profile::query()->firstOrFail()))
            ->assertRedirect(route('onboarding.index'))
            ->assertSessionHasErrors('media');
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

    public function test_profile_phone_requires_international_e164_format(): void
    {
        $provider = $this->provider(ProviderType::Independent);

        $this->actingAs($provider)->post(route('onboarding.profiles.store'), $this->validProfileData([
            'phone' => '0700000001',
        ]))->assertSessionHasErrors('phone');

        $this->assertDatabaseCount('profiles', 0);
    }

    public function test_provider_can_choose_a_micro_location_within_the_selected_sub_location(): void
    {
        $micro = Location::query()->create([
            'parent_id' => $this->sublocation->id,
            'country_code' => 'KE',
            'type' => 'landmark',
            'name' => 'Sarit Centre',
            'slug' => 'sarit-centre',
            'full_slug' => 'nairobi/westlands/sarit-centre',
            'status' => 'published',
        ]);
        $provider = $this->provider(ProviderType::Independent);

        $this->actingAs($provider)->post(route('onboarding.profiles.store'), $this->validProfileData([
            'micro_location_id' => $micro->id,
        ]))->assertRedirect(route('onboarding.index'))->assertSessionHasNoErrors();

        $this->assertSame($micro->id, Profile::query()->firstOrFail()->micro_location_id);
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

    private function processedImage(Profile $profile): void
    {
        $profile->images()->create([
            'storage_directory' => 'test/image',
            'sort_order' => 10,
            'status' => 'pending_review',
            'width' => 800,
            'height' => 1000,
            'aspect_ratio' => 0.8,
            'mime_type' => 'image/webp',
            'file_size' => 1000,
            'exact_hash' => hash('sha256', 'test-image-'.$profile->id),
            'derivatives' => ['thumb' => ['file' => 'thumb-320.webp', 'width' => 320, 'height' => 400, 'size' => 100]],
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
