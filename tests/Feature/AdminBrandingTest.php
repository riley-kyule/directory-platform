<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\DirectorySettings;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        Storage::fake('branding');
    }

    public function test_updating_branding_requires_the_settings_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.settings.branding.update'), [
                'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            ])
            ->assertForbidden();
    }

    public function test_admin_can_upload_a_logo(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.settings.branding.update'), [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $path = app(DirectorySettings::class)->string('site.logo_path');
        $this->assertNotSame('', $path);
        Storage::disk('branding')->assertExists($path);
        $this->assertNotNull(app(DirectorySettings::class)->logoUrl());

        $this->actingAs($admin)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Current logo', false);
    }

    public function test_admin_can_upload_a_favicon(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.settings.branding.update'), [
            'favicon' => UploadedFile::fake()->image('favicon.png', 32, 32),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $path = app(DirectorySettings::class)->string('site.favicon_path');
        Storage::disk('branding')->assertExists($path);
        $this->assertNotNull(app(DirectorySettings::class)->faviconUrl());
    }

    public function test_uploading_a_new_logo_deletes_the_previous_file(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.settings.branding.update'), [
            'logo' => UploadedFile::fake()->image('first.png', 200, 200),
        ]);
        $firstPath = app(DirectorySettings::class)->string('site.logo_path');

        $this->actingAs($admin)->post(route('admin.settings.branding.update'), [
            'logo' => UploadedFile::fake()->image('second.png', 200, 200),
        ]);
        $secondPath = app(DirectorySettings::class)->string('site.logo_path');

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('branding')->assertMissing($firstPath);
        Storage::disk('branding')->assertExists($secondPath);
    }

    public function test_removing_the_logo_clears_the_setting_and_deletes_the_file(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.settings.branding.update'), [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ]);
        $path = app(DirectorySettings::class)->string('site.logo_path');

        $this->actingAs($admin)->post(route('admin.settings.branding.update'), [
            'remove_logo' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('', app(DirectorySettings::class)->string('site.logo_path'));
        $this->assertNull(app(DirectorySettings::class)->logoUrl());
        Storage::disk('branding')->assertMissing($path);
    }

    public function test_oversized_logo_is_rejected(): void
    {
        $this->actingAs($this->admin())->post(route('admin.settings.branding.update'), [
            'logo' => UploadedFile::fake()->create('huge.png', 3000, 'image/png'),
        ])->assertSessionHasErrors('logo');

        $this->assertSame('', app(DirectorySettings::class)->string('site.logo_path'));
    }

    public function test_submitting_neither_a_file_nor_a_removal_is_rejected(): void
    {
        $this->actingAs($this->admin())->post(route('admin.settings.branding.update'), [])
            ->assertSessionHasErrors('logo');
    }

    public function test_application_logo_component_renders_uploaded_logo(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.settings.branding.update'), [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ]);

        $this->actingAs($admin)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('<img src="', false);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'admin')->firstOrFail());

        return $user;
    }
}
