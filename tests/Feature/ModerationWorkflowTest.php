<?php

namespace Tests\Feature;

use App\Enums\ProfileStatus;
use App\Jobs\PublishProfileImages;
use App\Models\Location;
use App\Models\Package;
use App\Models\Profile;
use App\Models\ProfileReport;
use App\Models\Role;
use App\Models\TaxonomyOption;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ModerationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Profile $profile;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessControlSeeder::class, DirectoryDefaultsSeeder::class]);
        Queue::fake();

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
        $this->owner = User::factory()->create();
        $this->profile = Profile::query()->create([
            'owner_user_id' => $this->owner->id,
            'display_name' => 'Reported Jane', 'slug' => 'reported-jane',
            'description' => 'A complete active profile used for the moderation workflow.',
            'primary_location_id' => $city->id, 'sublocation_id' => $neighbourhood->id,
            'gender_option_id' => TaxonomyOption::query()->ofType('gender')->firstOrFail()->id,
            'date_of_birth' => now()->subYears(25), 'ethnicity_option_id' => $ethnicity->id,
            'build_option_id' => TaxonomyOption::query()->ofType('build')->firstOrFail()->id,
            'allows_incall' => true, 'status' => ProfileStatus::Active,
            'published_at' => now(), 'last_activated_at' => now(), 'expires_at' => now()->addMonth(),
        ]);
        $this->profile->packageAssignments()->create([
            'package_id' => Package::query()->where('code', 'vip')->value('id'),
            'starts_at' => now(), 'expires_at' => now()->addMonth(), 'status' => 'active',
            'assigned_by' => $this->owner->id, 'assignment_source' => 'manual', 'reason' => 'Initial activation.',
        ]);
    }

    public function test_public_can_submit_confidential_urgent_report(): void
    {
        $this->get(route('directory.profiles.report.create', $this->profile))
            ->assertOk()
            ->assertSee('Report a concern')
            ->assertSee('noindex,nofollow');

        $this->post(route('directory.profiles.report.store', $this->profile), [
            'category' => 'suspected_minor',
            'details' => 'The profile contains information that raises an urgent age assurance concern.',
            'email' => 'Reporter@Example.com',
        ])->assertRedirect(route('directory.profiles.show', $this->profile->slug));

        $report = ProfileReport::query()->firstOrFail();
        $this->assertSame('urgent', $report->priority);
        $this->assertSame('reporter@example.com', $report->reporter_email);
        $this->assertNotSame('reporter@example.com', $report->getRawOriginal('reporter_email'));
        $this->assertNotNull($report->source_fingerprint);
    }

    public function test_only_admin_and_csr_can_access_report_evidence(): void
    {
        $report = $this->report();

        $this->actingAs($this->staff('seo'))->get(route('staff.moderation.show', $report))->assertForbidden();
        $this->actingAs($this->staff('csr'))->get(route('staff.moderation.show', $report))
            ->assertOk()
            ->assertSee('Confidential details')
            ->assertSee($report->reporter_email);
    }

    public function test_csr_can_take_down_profile_and_owner_can_appeal(): void
    {
        $report = $this->report();
        $csr = $this->staff('csr');

        $this->actingAs($csr)->patch(route('staff.moderation.update', $report), [
            'action' => 'make_private',
            'reason' => 'Urgent temporary takedown while age evidence is reviewed.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(ProfileStatus::Deactivated, $this->profile->refresh()->status);
        $this->assertDatabaseHas('profile_package_assignments', [
            'profile_id' => $this->profile->id, 'status' => 'moderation_hold',
        ]);
        $this->assertTrue($this->profile->hasActiveModerationRestriction());

        $this->actingAs($this->owner)->get(route('provider.profiles.show', $this->profile))
            ->assertOk()
            ->assertSee('Moderation restriction')
            ->assertDontSee('Request renewal');

        $this->actingAs($this->owner)->post(route('provider.profiles.appeals.store', $this->profile), [
            'reason' => 'I can provide valid supporting evidence and request a fresh review of this decision.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $appeal = $this->profile->moderationAppeals()->firstOrFail();
        $this->actingAs($csr)->patch(route('staff.moderation.appeals.review', $appeal), [
            'decision' => 'approve',
            'resolution' => 'Supporting evidence was reviewed and the restriction can be removed.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('approved', $appeal->refresh()->status);
        $this->assertSame(ProfileStatus::Active, $this->profile->refresh()->status);
        $this->assertFalse($this->profile->hasActiveModerationRestriction());
        $this->assertDatabaseHas('profile_package_assignments', [
            'profile_id' => $this->profile->id, 'status' => 'active',
        ]);
        Queue::assertPushed(PublishProfileImages::class);
    }

    public function test_report_validation_requires_meaningful_details_and_rejects_honeypot(): void
    {
        $this->post(route('directory.profiles.report.store', $this->profile), [
            'category' => 'fraud',
            'details' => 'Too short.',
            'email' => 'person@example.com',
            'website' => 'spam.example',
        ])->assertSessionHasErrors(['details', 'website']);

        $this->assertDatabaseCount('reports', 0);
    }

    private function report(): ProfileReport
    {
        return ProfileReport::query()->create([
            'profile_id' => $this->profile->id,
            'reporter_email' => 'reporter@example.com',
            'reporter_email_hash' => hash('sha256', 'reporter@example.com'),
            'category' => 'impersonation',
            'details' => 'The listing appears to use another person’s identity and media without permission.',
            'priority' => 'normal',
            'status' => 'new',
        ]);
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }
}
