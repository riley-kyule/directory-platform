<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DirectorySetting;
use App\Models\MailSetting;
use App\Models\Package;
use App\Models\PackageDurationOption;
use App\Models\Role;
use App\Models\User;
use App\Services\DirectorySettings;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminDirectorySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessControlSeeder::class, DirectoryDefaultsSeeder::class]);
    }

    public function test_only_admin_can_open_and_update_directory_settings(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.settings.index'))
            ->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Directory operation')
            ->assertSee('Require authenticator MFA');

        $settings = app(DirectorySettings::class);
        $this->assertSame(15, $settings->integer('profiles.agency_limit'));

        $this->actingAs($admin)->patch(route('admin.settings.update'), $this->validSettings([
            'agency_profile_limit' => 20,
            'new_profile_days' => 21,
            'micro_location_min_profiles' => 8,
            'maximum_file_megabytes' => 40,
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('20', DirectorySetting::query()->findOrFail('profiles.agency_limit')->value);
        $this->assertSame('21', DirectorySetting::query()->findOrFail('listings.new_profile_days')->value);
        $this->assertSame('8', DirectorySetting::query()->findOrFail('locations.micro_min_profiles')->value);
        $this->assertSame('40960', DirectorySetting::query()->findOrFail('media.maximum_file_kilobytes')->value);
        $this->assertSame(20, $settings->integer('profiles.agency_limit'));
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'settings.update',
            'target_type' => 'directory-configuration',
        ]);
    }

    public function test_admin_can_enable_optional_privileged_mfa(): void
    {
        $this->assertFalse(app(DirectorySettings::class)->boolean('security.privileged_mfa_enforced'));

        $this->actingAs($this->admin())->patch(route('admin.settings.update'), $this->validSettings([
            'privileged_mfa_enforced' => true,
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('1', DirectorySetting::query()->findOrFail('security.privileged_mfa_enforced')->value);
        $this->assertTrue(app(DirectorySettings::class)->boolean('security.privileged_mfa_enforced'));
    }

    public function test_admin_can_configure_encrypted_smtp_delivery_without_environment_changes(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.settings.mail.edit'))
            ->assertOk()->assertSee('Mail delivery')->assertSee('Server mail');

        $this->actingAs($admin)->patch(route('admin.settings.mail.update'), [
            'mailer' => 'smtp',
            'from_address' => 'no-reply@directory.test',
            'from_name' => 'Directory Mail',
            'sendmail_path' => '/usr/sbin/sendmail -bs -i',
            'smtp_scheme' => 'smtps',
            'smtp_host' => 'mail.directory.test',
            'smtp_port' => 465,
            'smtp_username' => 'no-reply@directory.test',
            'smtp_password' => 'secret-mail-password',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $settings = MailSetting::query()->firstOrFail();
        $this->assertSame('secret-mail-password', $settings->smtp_password);
        $this->assertNotSame('secret-mail-password', DB::table('mail_settings')->value('smtp_password'));
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('mail.directory.test', config('mail.mailers.smtp.host'));
        $audit = AuditLog::query()->where('action', 'settings.mail-update')->firstOrFail();
        $this->assertStringNotContainsString('secret-mail-password', json_encode([$audit->previous_state, $audit->new_state]));
    }

    public function test_non_admin_cannot_manage_mail_delivery(): void
    {
        $subscriber = User::factory()->create();

        $this->actingAs($subscriber)->get(route('admin.settings.mail.edit'))->assertForbidden();
        $this->actingAs($subscriber)->patch(route('admin.settings.mail.update'), [])->assertForbidden();
        $this->actingAs($subscriber)->post(route('admin.settings.mail.test'), ['recipient' => 'test@example.com'])->assertForbidden();
    }

    public function test_admin_can_send_a_test_email_from_saved_configuration(): void
    {
        Mail::shouldReceive('raw')->once();

        $this->actingAs($this->admin())->post(route('admin.settings.mail.test'), [
            'recipient' => 'admin@example.com',
        ])->assertRedirect()->assertSessionHas('status', 'Test email sent to admin@example.com.');
    }

    public function test_admin_can_publish_search_engine_verification_tags(): void
    {
        $admin = $this->admin();

        $this->get(route('directory.home'))
            ->assertOk()
            ->assertDontSee('google-site-verification');

        $this->actingAs($admin)->patch(route('admin.settings.update'), $this->validSettings([
            'google_site_verification' => 'google_token-123',
            'bing_site_verification' => 'ABCDEF_456',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->app['auth']->logout();
        $this->get(route('directory.home'))
            ->assertOk()
            ->assertSee('<meta name="google-site-verification" content="google_token-123">', false)
            ->assertSee('<meta name="msvalidate.01" content="ABCDEF_456">', false);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'settings.update',
        ]);
    }

    public function test_search_engine_verification_tokens_reject_markup(): void
    {
        $this->actingAs($this->admin())->patch(route('admin.settings.update'), $this->validSettings([
            'google_site_verification' => '"><script>alert(1)</script>',
        ]))->assertRedirect()->assertSessionHasErrors('google_site_verification');

        $this->assertDatabaseMissing('directory_settings', ['key' => 'seo.google_site_verification']);
    }

    public function test_packages_page_shows_packages_and_durations(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.settings.packages.index'))
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->get(route('admin.settings.packages.index'))
            ->assertOk()
            ->assertSee('Packages')
            ->assertSee('Package durations');
    }

    public function test_admin_can_change_package_presentation_and_image_limit(): void
    {
        $admin = $this->admin();
        $vip = Package::query()->where('code', 'vip')->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.settings.packages.update', $vip), [
            'name' => 'VIP Featured',
            'image_limit' => 18,
            'video_limit' => 6,
            'display_order' => 5,
            'is_active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $vip->refresh();
        $this->assertSame('VIP Featured', $vip->name);
        $this->assertSame(18, $vip->image_limit);
        $this->assertSame(6, $vip->video_limit);
        $this->assertSame(5, $vip->display_order);
        $this->assertDatabaseHas('audit_logs', ['action' => 'packages.update', 'target_id' => $vip->id]);
    }

    public function test_admin_cannot_disable_the_last_active_package(): void
    {
        $admin = $this->admin();
        Package::query()->where('code', '!=', 'basic')->update(['is_active' => false]);
        $basic = Package::query()->where('code', 'basic')->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.settings.packages.update', $basic), [
            'name' => $basic->name,
            'image_limit' => $basic->image_limit,
            'video_limit' => $basic->video_limit,
            'display_order' => $basic->display_order,
            'is_active' => '0',
        ])->assertRedirect()->assertSessionHasErrors('package');

        $this->assertTrue($basic->refresh()->is_active);
    }

    public function test_admin_can_add_and_update_duration_options(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.settings.durations.store'), [
            'label' => '45 days',
            'duration_days' => 45,
            'display_order' => 35,
            'is_active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $duration = PackageDurationOption::query()->where('duration_days', 45)->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.settings.durations.update', $duration), [
            'label' => 'Six weeks plus',
            'duration_days' => 46,
            'display_order' => 36,
            'is_active' => '0',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $duration->refresh();
        $this->assertSame('Six weeks plus', $duration->label);
        $this->assertSame(46, $duration->duration_days);
        $this->assertFalse($duration->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'package-durations.create', 'target_id' => $duration->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'package-durations.update', 'target_id' => $duration->id]);
    }

    public function test_settings_validation_prevents_impossible_media_dimensions(): void
    {
        $this->actingAs($this->admin())->patch(route('admin.settings.update'), $this->validSettings([
            'minimum_width' => 1500,
            'maximum_dimension' => 1000,
        ]))->assertRedirect()->assertSessionHasErrors('maximum_dimension');

        $this->assertSame('600', DirectorySetting::query()->findOrFail('media.minimum_width')->value);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('slug', 'admin')->firstOrFail());

        return $admin;
    }

    /** @param array<string, bool|int|float> $overrides
     * @return array<string, bool|int|float>
     */
    private function validSettings(array $overrides = []): array
    {
        return array_replace([
            'privileged_mfa_enforced' => false,
            'agency_profile_limit' => 15,
            'new_profile_days' => 14,
            'listing_rotation_hours' => 24,
            'micro_location_min_profiles' => 6,
            'maximum_file_megabytes' => 50,
            'minimum_width' => 600,
            'minimum_height' => 600,
            'maximum_dimension' => 12000,
            'maximum_megapixels' => 40,
            'minimum_aspect_ratio' => 0.4,
            'maximum_aspect_ratio' => 2.5,
            'webp_quality' => 82,
            'processing_memory_limit_mb' => 512,
            'video_max_megabytes' => 200,
            'video_max_duration_seconds' => 120,
        ], $overrides);
    }
}
