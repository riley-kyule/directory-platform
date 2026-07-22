<?php

namespace Tests\Feature;

use App\Enums\ProfileStatus;
use App\Models\Package;
use App\Models\TaxonomyOption;
use App\Models\User;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DirectorySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_directory_tables_exist(): void
    {
        foreach ([
            'locations', 'agencies', 'profiles', 'packages', 'profile_package_requests',
            'profile_package_assignments', 'profile_contact_methods', 'profile_rates',
            'profile_services', 'profile_images', 'policy_acceptances', 'audit_logs',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected {$table} to exist.");
        }
    }

    public function test_default_packages_have_the_agreed_image_limits(): void
    {
        $this->seed(DirectoryDefaultsSeeder::class);

        $this->assertSame(15, Package::query()->where('code', 'vip')->value('image_limit'));
        $this->assertSame(10, Package::query()->where('code', 'premium')->value('image_limit'));
        $this->assertSame(5, Package::query()->where('code', 'basic')->value('image_limit'));
    }

    public function test_required_managed_taxonomies_are_seeded(): void
    {
        $this->seed(DirectoryDefaultsSeeder::class);

        $this->assertSame(9, TaxonomyOption::query()->ofType('service')->count());
        $this->assertTrue(
            TaxonomyOption::query()->ofType('gender')->where('slug', 'woman')->firstOrFail()->settings['requires_bust_size'],
        );
        $this->assertTrue(TaxonomyOption::query()->ofType('build')->enabled()->exists());
    }

    public function test_only_active_profile_status_is_public(): void
    {
        $this->assertTrue(ProfileStatus::Active->isPublic());
        $this->assertFalse(ProfileStatus::Expired->isPublic());
        $this->assertFalse(ProfileStatus::Banned->isPublic());
    }

    public function test_independent_user_relationship_is_singular(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->profile);
    }
}
