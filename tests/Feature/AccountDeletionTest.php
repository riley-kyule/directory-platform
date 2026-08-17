<?php

namespace Tests\Feature;

use App\Enums\ProfileStatus;
use App\Models\Agency;
use App\Models\Location;
use App\Models\Package;
use App\Models\PolicyAcceptance;
use App\Models\PolicyVersion;
use App\Models\Profile;
use App\Models\ProfileImage;
use App\Models\ProfileReport;
use App\Models\Role;
use App\Models\TaxonomyOption;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private Location $city;

    private Location $neighbourhood;

    private TaxonomyOption $ethnicity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessControlSeeder::class, DirectoryDefaultsSeeder::class]);

        $this->city = Location::query()->create([
            'country_code' => 'KE', 'type' => 'city', 'name' => 'Nairobi', 'slug' => 'nairobi',
            'full_slug' => 'nairobi', 'status' => 'published',
        ]);
        $this->neighbourhood = Location::query()->create([
            'parent_id' => $this->city->id, 'country_code' => 'KE', 'type' => 'neighbourhood',
            'name' => 'Westlands', 'slug' => 'westlands', 'full_slug' => 'nairobi/westlands',
            'status' => 'published',
        ]);
        $this->ethnicity = TaxonomyOption::query()->create([
            'type' => 'ethnicity', 'slug' => 'african', 'label' => 'African', 'is_active' => true,
        ]);
    }

    public function test_deleting_an_independent_providers_account_immediately_deactivates_their_profile(): void
    {
        $owner = User::factory()->create();
        $profile = $this->activeProfile($owner->id, 'Independent Jane', 'independent-jane');

        $this->actingAs($owner)->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertSame(ProfileStatus::Deactivated, $profile->refresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $owner->id,
            'action' => 'accounts.self-delete',
            'target_id' => $owner->id,
        ]);
        $this->assertNotNull($owner->fresh()->deleted_at);
        $this->get(route('directory.profiles.show', $profile->slug))->assertNotFound();
    }

    public function test_deleting_an_agency_owners_account_deactivates_the_agency_and_its_active_profiles(): void
    {
        $owner = User::factory()->create();
        $agency = Agency::query()->create([
            'owner_user_id' => $owner->id, 'name' => 'Sunrise Agency', 'slug' => 'sunrise-agency', 'status' => 'active',
        ]);
        $agencyProfile = $this->activeProfile(null, 'Agency Jane', 'agency-jane');
        $agency->profiles()->attach($agencyProfile, ['assigned_by' => $owner->id, 'assigned_at' => now()]);

        $this->actingAs($owner)->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertSame('inactive', $agency->refresh()->status);
        $this->assertSame(ProfileStatus::Deactivated, $agencyProfile->refresh()->status);
        $this->get(route('directory.agencies.show', $agency->slug))->assertNotFound();
    }

    public function test_deleting_a_member_account_with_no_profile_or_agency_works_cleanly(): void
    {
        $member = User::factory()->create([
            'email' => 'reusable@example.test',
            'google_subject' => 'reusable-google-subject',
        ]);

        $this->actingAs($member)->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertNotNull($member->fresh()->deleted_at);
        $this->assertNotSame('reusable@example.test', $member->fresh()->email);
        $this->assertNull($member->fresh()->google_subject);

        User::factory()->create([
            'email' => 'reusable@example.test',
            'google_subject' => 'reusable-google-subject',
        ]);

        $this->assertDatabaseCount('users', 2);
    }

    public function test_purge_command_leaves_recently_deleted_accounts_alone(): void
    {
        $owner = User::factory()->create();
        $profile = $this->activeProfile($owner->id, 'Recent Jane', 'recent-jane');
        $this->actingAs($owner)->delete(route('profile.destroy'), ['password' => 'password']);

        $this->artisan('accounts:purge-deleted')->assertSuccessful();

        $this->assertDatabaseHas('profiles', ['id' => $profile->id]);
        $this->assertNull($owner->fresh()->anonymized_at);
    }

    public function test_purge_command_hard_deletes_profile_media_and_anonymizes_the_owner(): void
    {
        Storage::fake('quarantine');
        Storage::fake('profile_media');

        $owner = User::factory()->create(['email' => 'jane@example.test', 'name' => 'Jane Real Name']);
        $profile = $this->activeProfile($owner->id, 'Media Jane', 'media-jane');
        Storage::disk('profile_media')->put($profile->public_id.'/full-1280.webp', 'fake-image-bytes');
        $image = ProfileImage::query()->create([
            'profile_id' => $profile->id, 'public_id' => (string) Str::uuid(),
            'storage_directory' => $profile->public_id, 'sort_order' => 10, 'status' => 'approved',
            'width' => 800, 'height' => 1000, 'aspect_ratio' => 0.8, 'mime_type' => 'image/webp', 'file_size' => 100,
            'exact_hash' => hash('sha256', 'x'),
        ]);

        $this->actingAs($owner)->delete(route('profile.destroy'), ['password' => 'password']);
        $owner->forceFill(['deleted_at' => now()->subDays(31)])->save();

        $this->artisan('accounts:purge-deleted')->assertSuccessful();

        $this->assertDatabaseMissing('profiles', ['id' => $profile->id]);
        $this->assertDatabaseMissing('profile_images', ['id' => $image->id]);
        Storage::disk('profile_media')->assertMissing($profile->public_id.'/full-1280.webp');

        $owner->refresh();
        $this->assertNotNull($owner->anonymized_at);
        $this->assertNotSame('jane@example.test', $owner->email);
        $this->assertNotSame('Jane Real Name', $owner->name);
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }

    public function test_purge_command_handles_a_profile_with_moderation_history_and_an_appeal(): void
    {
        $owner = User::factory()->create();
        $profile = $this->activeProfile($owner->id, 'Appealed Jane', 'appealed-jane');
        $csr = User::factory()->create();
        $csr->roles()->attach(Role::query()->where('slug', 'csr')->firstOrFail());

        $report = ProfileReport::query()->create([
            'profile_id' => $profile->id, 'category' => 'impersonation', 'details' => str_repeat('Concern details. ', 5),
            'reporter_email' => 'reporter@example.test', 'status' => 'pending', 'priority' => 'normal',
        ]);
        $this->actingAs($csr)->patch(route('staff.moderation.update', $report), [
            'action' => 'make_private',
            'reason' => 'Urgent temporary takedown while evidence is reviewed.',
        ])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('provider.profiles.appeals.store', $profile), [
            'reason' => 'I can provide valid supporting evidence for a fresh review.',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('moderation_actions', 1);
        $this->assertDatabaseCount('moderation_appeals', 1);

        $this->actingAs($owner)->delete(route('profile.destroy'), ['password' => 'password']);
        $owner->forceFill(['deleted_at' => now()->subDays(31)])->save();

        $this->artisan('accounts:purge-deleted')->assertSuccessful();

        $this->assertDatabaseMissing('profiles', ['id' => $profile->id]);
        $this->assertDatabaseCount('moderation_actions', 0);
        $this->assertDatabaseCount('moderation_appeals', 0);
        $this->assertNotNull($owner->fresh()->anonymized_at);
    }

    public function test_purge_command_deletes_an_owned_agency_without_touching_member_profile_data(): void
    {
        $owner = User::factory()->create();
        $agency = Agency::query()->create([
            'owner_user_id' => $owner->id, 'name' => 'Purge Agency', 'slug' => 'purge-agency', 'status' => 'active',
        ]);
        $memberProfile = $this->activeProfile(null, 'Member Jane', 'member-jane');
        $agency->profiles()->attach($memberProfile, ['assigned_by' => $owner->id, 'assigned_at' => now()]);

        $this->actingAs($owner)->delete(route('profile.destroy'), ['password' => 'password']);
        $owner->forceFill(['deleted_at' => now()->subDays(31)])->save();

        $this->artisan('accounts:purge-deleted')->assertSuccessful();

        $this->assertDatabaseMissing('agencies', ['id' => $agency->id]);
        $this->assertDatabaseHas('profiles', ['id' => $memberProfile->id]);
        $this->assertNotNull($owner->fresh()->anonymized_at);
    }

    public function test_anonymized_user_keeps_policy_acceptance_evidence_intact(): void
    {
        $member = User::factory()->create();
        $policy = PolicyVersion::query()->create([
            'policy_type' => 'registration', 'version' => 1, 'title' => 'Terms', 'content' => 'Terms content.',
            'content_hash' => hash('sha256', 'terms'), 'published_at' => now(), 'requires_reacceptance' => false,
        ]);
        $acceptance = PolicyAcceptance::query()->create([
            'policy_version_id' => $policy->id, 'user_id' => $member->id, 'action' => 'registration',
            'accepted_at' => now(),
        ]);

        $this->actingAs($member)->delete(route('profile.destroy'), ['password' => 'password']);
        $member->forceFill(['deleted_at' => now()->subDays(31)])->save();

        $this->artisan('accounts:purge-deleted')->assertSuccessful();

        $this->assertDatabaseHas('policy_acceptances', ['id' => $acceptance->id, 'user_id' => $member->id]);
        $this->assertDatabaseHas('users', ['id' => $member->id]);
        $this->assertNotNull($member->fresh()->anonymized_at);
    }

    private function activeProfile(?int $ownerId, string $name, string $slug): Profile
    {
        $profile = Profile::query()->create([
            'owner_user_id' => $ownerId,
            'display_name' => $name, 'slug' => $slug,
            'description' => 'A complete active profile used for account-deletion tests.',
            'primary_location_id' => $this->city->id, 'sublocation_id' => $this->neighbourhood->id,
            'gender_option_id' => TaxonomyOption::query()->ofType('gender')->firstOrFail()->id,
            'date_of_birth' => now()->subYears(25), 'ethnicity_option_id' => $this->ethnicity->id,
            'build_option_id' => TaxonomyOption::query()->ofType('build')->firstOrFail()->id,
            'allows_incall' => true, 'status' => ProfileStatus::Active,
            'verification_status' => 'verified',
            'published_at' => now(), 'last_activated_at' => now(), 'expires_at' => now()->addMonth(),
        ]);
        $profile->packageAssignments()->create([
            'package_id' => Package::query()->where('code', 'vip')->value('id'),
            'starts_at' => now(), 'expires_at' => now()->addMonth(), 'status' => 'active',
            'assigned_by' => $ownerId ?? User::factory()->create()->id, 'assignment_source' => 'manual', 'reason' => 'Test assignment.',
        ]);

        return $profile;
    }
}
