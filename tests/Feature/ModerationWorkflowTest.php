<?php

namespace Tests\Feature;

use App\Enums\ProfileStatus;
use App\Jobs\PublishProfileImages;
use App\Models\Location;
use App\Models\ModerationAction;
use App\Models\ModerationAppeal;
use App\Models\Package;
use App\Models\Profile;
use App\Models\ProfileReport;
use App\Models\Role;
use App\Models\SystemHeartbeat;
use App\Models\TaxonomyOption;
use App\Models\User;
use App\Notifications\OverdueModerationDigestNotification;
use App\Notifications\UrgentProfileReportNotification;
use App\Services\ModerationMetricsService;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
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
            'verification_status' => 'verified',
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
        Notification::fake();
        $admin = $this->staff('admin');
        $csr = $this->staff('csr');
        $inactiveCsr = $this->staff('csr');
        $inactiveCsr->update(['status' => 'suspended']);

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
        Notification::assertSentTo([$admin, $csr], UrgentProfileReportNotification::class);
        Notification::assertNotSentTo($inactiveCsr, UrgentProfileReportNotification::class);
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

    public function test_closed_report_contact_data_is_redacted_after_retention_window(): void
    {
        config()->set('operations.report_pii_retention_days', 30);
        $report = $this->report();
        $report->update([
            'reporter_user_id' => $this->owner->id,
            'status' => 'resolved',
            'resolved_at' => now()->subDays(31),
            'source_fingerprint' => hash('sha256', 'report-source'),
        ]);

        $this->assertSame(0, Artisan::call('privacy:prune-public-submission-pii'));

        $report->refresh();
        $this->assertNull($report->reporter_email);
        $this->assertNull($report->reporter_email_hash);
        $this->assertNull($report->source_fingerprint);
        $this->assertNull($report->reporter_user_id);
        $this->assertSame('resolved', $report->status);

        $this->actingAs($this->staff('csr'))->get(route('staff.moderation.show', $report))
            ->assertOk()
            ->assertSee('Redacted after retention period');
    }

    public function test_csr_can_perform_an_emergency_takedown_without_an_existing_report(): void
    {
        $csr = $this->staff('csr');

        $this->actingAs($csr)->get(route('staff.directory.index'))
            ->assertOk()
            ->assertSee('Emergency takedown')
            ->assertSee(route('staff.directory.emergency-takedown', $this->profile), false);

        $this->actingAs($csr)->patch(route('staff.directory.emergency-takedown', $this->profile), [
            'reason' => 'Suspected underage content, taking down immediately pending review.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(ProfileStatus::Banned, $this->profile->refresh()->status);
        $this->assertDatabaseHas('moderation_actions', [
            'profile_id' => $this->profile->id,
            'report_id' => null,
            'action' => 'emergency_takedown',
            'actor_user_id' => $csr->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'moderation.emergency-takedown',
            'target_type' => 'profile',
            'target_id' => $this->profile->id,
        ]);
        $this->get(route('directory.profiles.show', $this->profile->slug))->assertNotFound();
    }

    public function test_seo_cannot_perform_an_emergency_takedown(): void
    {
        $this->actingAs($this->staff('seo'))->patch(route('staff.directory.emergency-takedown', $this->profile), [
            'reason' => 'Attempting an unauthorized takedown.',
        ])->assertForbidden();

        $this->assertSame(ProfileStatus::Active, $this->profile->refresh()->status);
    }

    public function test_emergency_takedown_requires_a_reason(): void
    {
        $this->actingAs($this->staff('csr'))->patch(route('staff.directory.emergency-takedown', $this->profile), [
            'reason' => 'shrt',
        ])->assertSessionHasErrors('reason');

        $this->assertSame(ProfileStatus::Active, $this->profile->refresh()->status);
    }

    public function test_emergency_takedown_rejects_an_already_banned_profile(): void
    {
        $csr = $this->staff('csr');
        $this->profile->update(['status' => ProfileStatus::Banned]);

        $this->actingAs($csr)->patch(route('staff.directory.emergency-takedown', $this->profile), [
            'reason' => 'Trying to take down an already-banned profile.',
        ])->assertStatus(409);
    }

    public function test_seo_cannot_view_moderation_metrics(): void
    {
        $this->actingAs($this->staff('seo'))->get(route('staff.moderation.metrics'))->assertForbidden();
    }

    public function test_moderation_metrics_summarizes_reports_actions_and_appeals(): void
    {
        $csr = $this->staff('csr');
        $urgentReport = $this->report();
        $urgentReport->update(['priority' => 'urgent', 'category' => 'suspected_minor']);
        $resolvedReport = ProfileReport::query()->create([
            'profile_id' => $this->profile->id,
            'reporter_email' => 'other@example.com',
            'reporter_email_hash' => hash('sha256', 'other@example.com'),
            'category' => 'fraud',
            'details' => 'A separate concern used to exercise the resolved-report metrics path.',
            'priority' => 'normal',
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        $this->actingAs($csr)->patch(route('staff.directory.emergency-takedown', $this->profile), [
            'reason' => 'Exercising the metrics action counter.',
        ]);

        $this->actingAs($csr)->get(route('staff.moderation.metrics'))
            ->assertOk()
            ->assertSee('Suspected minor')
            ->assertSee('Fraud or scam');

        $metrics = app(ModerationMetricsService::class)->summary();
        $this->assertSame(1, $metrics['open_urgent_reports']);
        $this->assertSame(1, $metrics['reports_by_status']['resolved']);
        $this->assertSame(1, $metrics['actions_last_30_days']['emergency_takedown']);
        $this->assertNotNull($metrics['average_resolution_hours']);
        $this->assertSame($resolvedReport->status, 'resolved');
    }

    public function test_sla_metrics_and_queue_filter_identify_only_overdue_open_cases(): void
    {
        config()->set('operations.moderation_urgent_sla_minutes', 60);
        config()->set('operations.moderation_normal_sla_hours', 24);
        $urgent = $this->report();
        $urgent->update(['priority' => 'urgent', 'created_at' => now()->subMinutes(61)]);
        $normal = $this->report();
        $normal->update(['created_at' => now()->subHours(25)]);
        $recent = $this->report();

        $metrics = app(ModerationMetricsService::class)->slaSummary();
        $this->assertSame(1, $metrics['overdue_urgent_reports']);
        $this->assertSame(1, $metrics['overdue_normal_reports']);
        $this->assertSame(3, $metrics['unassigned_open_reports']);
        $this->assertGreaterThanOrEqual(25, $metrics['oldest_open_hours']);

        $csr = $this->staff('csr');
        $this->actingAs($csr)->get(route('staff.moderation.index', ['sla' => 'overdue']))
            ->assertOk()
            ->assertSee('Moderation response targets have been exceeded')
            ->assertSee($urgent->public_id)
            ->assertSee($normal->public_id)
            ->assertDontSee($recent->public_id)
            ->assertSee('Overdue by');
        $this->actingAs($csr)->get(route('admin.dashboard.index'))
            ->assertOk()
            ->assertSee('2 overdue moderation cases')
            ->assertSee(route('staff.moderation.index', ['sla' => 'overdue']), false);
    }

    public function test_overdue_cases_are_escalated_once_to_active_admin_and_csr_staff(): void
    {
        Notification::fake();
        config()->set('operations.moderation_urgent_sla_minutes', 60);
        config()->set('operations.moderation_appeal_sla_hours', 72);
        $admin = $this->staff('admin');
        $csr = $this->staff('csr');
        $inactive = $this->staff('csr');
        $inactive->update(['status' => 'suspended']);
        $report = $this->report();
        $report->update(['priority' => 'urgent', 'created_at' => now()->subMinutes(61)]);
        $appeal = $this->overdueAppeal();

        $this->artisan('moderation:escalate-overdue')->assertSuccessful();

        Notification::assertSentTo([$admin, $csr], OverdueModerationDigestNotification::class, function ($notification): bool {
            return $notification->urgentReports === 1 && $notification->appeals === 1;
        });
        Notification::assertNotSentTo($inactive, OverdueModerationDigestNotification::class);
        $this->assertNotNull($report->refresh()->sla_escalated_at);
        $this->assertNotNull($appeal->refresh()->sla_escalated_at);

        $this->artisan('moderation:escalate-overdue')->assertSuccessful();
        Notification::assertSentToTimes($admin, OverdueModerationDigestNotification::class, 1);
        Notification::assertSentToTimes($csr, OverdueModerationDigestNotification::class, 1);
    }

    public function test_escalation_fails_safely_and_retries_when_no_staff_recipient_exists(): void
    {
        config()->set('operations.moderation_urgent_sla_minutes', 60);
        $report = $this->report();
        $report->update(['priority' => 'urgent', 'created_at' => now()->subMinutes(61)]);

        $this->artisan('moderation:escalate-overdue')->assertFailed();

        $this->assertNull($report->refresh()->sla_escalated_at);
        $this->assertDatabaseHas('system_heartbeats', ['name' => 'moderation_escalation']);
        $this->assertSame(0, SystemHeartbeat::query()->findOrFail('moderation_escalation')->metadata['recipients']);
    }

    private function overdueAppeal(): ModerationAppeal
    {
        $action = ModerationAction::query()->create([
            'profile_id' => $this->profile->id,
            'action' => 'make_private',
            'previous_profile_status' => ProfileStatus::Active->value,
            'new_profile_status' => ProfileStatus::Deactivated->value,
            'reason' => 'Test restriction used to create an overdue moderation appeal.',
        ]);

        $appeal = ModerationAppeal::query()->create([
            'profile_id' => $this->profile->id,
            'moderation_action_id' => $action->id,
            'appellant_user_id' => $this->owner->id,
            'reason' => 'An overdue appeal used to exercise staff escalation and reporting.',
            'status' => 'pending',
        ]);
        $appeal->update(['created_at' => now()->subHours(73)]);

        return $appeal;
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
