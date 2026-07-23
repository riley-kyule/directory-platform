<?php

namespace Tests\Feature;

use App\Models\DirectorySetting;
use App\Models\Package;
use App\Models\PackageDurationOption;
use App\Models\Role;
use App\Models\User;
use App\Services\DirectorySettings;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('Packages')
            ->assertSee('Package durations');

        $settings = app(DirectorySettings::class);
        $this->assertSame(15, $settings->integer('profiles.agency_limit'));

        $this->actingAs($admin)->patch(route('admin.settings.update'), $this->validSettings([
            'agency_profile_limit' => 20,
            'new_profile_days' => 21,
            'maximum_file_megabytes' => 40,
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('20', DirectorySetting::query()->findOrFail('profiles.agency_limit')->value);
        $this->assertSame('21', DirectorySetting::query()->findOrFail('listings.new_profile_days')->value);
        $this->assertSame('40960', DirectorySetting::query()->findOrFail('media.maximum_file_kilobytes')->value);
        $this->assertSame(20, $settings->integer('profiles.agency_limit'));
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'settings.update',
            'target_type' => 'directory-configuration',
        ]);
    }

    public function test_admin_can_change_package_presentation_and_image_limit(): void
    {
        $admin = $this->admin();
        $vip = Package::query()->where('code', 'vip')->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.settings.packages.update', $vip), [
            'name' => 'VIP Featured',
            'image_limit' => 18,
            'display_order' => 5,
            'is_active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $vip->refresh();
        $this->assertSame('VIP Featured', $vip->name);
        $this->assertSame(18, $vip->image_limit);
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

    /** @param array<string, int|float> $overrides
     * @return array<string, int|float>
     */
    private function validSettings(array $overrides = []): array
    {
        return array_replace([
            'agency_profile_limit' => 15,
            'new_profile_days' => 14,
            'listing_rotation_hours' => 24,
            'maximum_file_megabytes' => 50,
            'minimum_width' => 600,
            'minimum_height' => 600,
            'maximum_dimension' => 12000,
            'maximum_megapixels' => 40,
            'minimum_aspect_ratio' => 0.4,
            'maximum_aspect_ratio' => 2.5,
            'webp_quality' => 82,
        ], $overrides);
    }
}
