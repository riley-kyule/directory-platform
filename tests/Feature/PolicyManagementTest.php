<?php

namespace Tests\Feature;

use App\Models\PolicyVersion;
use App\Models\Role;
use App\Models\User;
use App\Services\PolicyAcceptanceService;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PolicyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_policy_management_requires_the_policy_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.policies.index'))
            ->assertForbidden();

        $seo = $this->userWithRole('seo');
        $this->actingAs($seo)
            ->get(route('admin.policies.index'))
            ->assertOk()
            ->assertSee('Policy management');
    }

    public function test_authorized_staff_can_save_then_publish_an_immutable_version(): void
    {
        $seo = $this->userWithRole('seo');
        $content = $this->content('Providers must comply with these publishing conditions.');

        $this->actingAs($seo)->put(route('admin.policies.save', 'provider'), [
            'version' => '2026-07',
            'title' => 'Provider publishing policy',
            'summary' => 'Rules for providers publishing listings.',
            'content' => $content,
            'requires_reacceptance' => '1',
            'action' => 'save_draft',
        ])->assertRedirect(route('admin.policies.index'))->assertSessionHasNoErrors();

        $draft = PolicyVersion::query()->firstOrFail();
        $this->assertNull($draft->published_at);

        $this->actingAs($seo)->put(route('admin.policies.save', 'provider'), [
            'version' => '2026-07',
            'title' => 'Provider publishing policy',
            'summary' => 'Rules for providers publishing listings.',
            'content' => $content,
            'requires_reacceptance' => '1',
            'action' => 'publish',
        ])->assertRedirect(route('admin.policies.index'))->assertSessionHasNoErrors();

        $published = $draft->refresh();
        $this->assertNotNull($published->published_at);
        $this->assertSame(hash('sha256', trim($content)), $published->content_hash);
        $this->assertSame($seo->id, $published->published_by);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $seo->id,
            'action' => 'policies.publish',
            'target_id' => $published->id,
        ]);

        $this->actingAs($seo)->put(route('admin.policies.save', 'provider'), [
            'version' => '2026-08',
            'title' => 'Updated provider policy',
            'content' => $this->content('This is a separate policy version.'),
            'action' => 'save_draft',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('policy_versions', 2);
        $this->assertSame('Provider publishing policy', $published->refresh()->title);
    }

    public function test_public_policy_page_only_serves_the_latest_published_version(): void
    {
        $published = $this->publish('privacy', '2026-07', true, [
            'title' => 'Privacy and data policy',
            'content' => $this->content("## Data handling\n\nWe protect submitted account information. <script>alert('unsafe')</script>"),
        ]);
        PolicyVersion::query()->create([
            'policy_type' => 'privacy',
            'version' => '2026-08-draft',
            'title' => 'Unpublished privacy draft',
            'content' => $this->content('This text must not be public.'),
            'content_hash' => hash('sha256', 'draft'),
        ]);

        $this->get(route('policies.privacy'))
            ->assertOk()
            ->assertSee($published->title)
            ->assertSee('Data handling')
            ->assertDontSee('Unpublished privacy draft')
            ->assertDontSee('<script>', false);
    }

    public function test_registration_requires_and_records_current_terms_and_privacy(): void
    {
        $terms = $this->publish('terms');
        $privacy = $this->publish('privacy');
        $this->assertSame(
            ['privacy', 'terms'],
            app(PolicyAcceptanceService::class)->latestPublished()->keys()->sort()->values()->all(),
        );
        $this->assertCount(2, app(PolicyAcceptanceService::class)->outstanding('registration'));

        $payload = [
            'name' => 'Policy User',
            'email' => 'policy@example.com',
            'account_type' => 'member',
            'password' => 'password',
            'password_confirmation' => 'password',
        ];

        $this->post(route('register'), $payload)
            ->assertSessionHasErrors('policy_acceptances');
        $this->assertDatabaseMissing('users', ['email' => 'policy@example.com']);

        $this->post(route('register'), $payload + [
            'policy_acceptances' => [$terms->id, $privacy->id],
        ])->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'policy@example.com')->firstOrFail();
        $this->assertDatabaseCount('policy_acceptances', 2);
        $this->assertDatabaseHas('policy_acceptances', [
            'user_id' => $user->id,
            'policy_version_id' => $terms->id,
            'action' => 'registration',
        ]);
    }

    public function test_only_material_policy_updates_require_reacceptance(): void
    {
        $user = User::factory()->create();
        $first = $this->publish('provider', '2026-07');
        $service = app(PolicyAcceptanceService::class);
        $service->record($user, 'profile_submission', collect([$first]), Request::create('/test', 'POST'));

        $this->travel(1)->minute();
        $nonMaterial = $this->publish('provider', '2026-08', false);
        $this->assertTrue($service->outstanding('profile_submission', $user)->isEmpty());

        $this->travel(1)->minute();
        $material = $this->publish('provider', '2026-09', true);
        $this->assertSame($material->id, $service->latestPublished()->get('provider')?->id);
        $this->assertTrue($material->refresh()->requires_reacceptance);
        $this->assertSame([$material->id], $service->outstanding('profile_submission', $user)->modelKeys());
        $this->assertNotSame($nonMaterial->id, $material->id);
    }

    /** @param array<string, mixed> $overrides */
    private function publish(
        string $type,
        string $version = '2026-07',
        bool $requiresReacceptance = false,
        array $overrides = [],
    ): PolicyVersion {
        $content = $overrides['content'] ?? $this->content("The current {$type} policy applies to this directory.");

        return PolicyVersion::query()->create(array_replace([
            'policy_type' => $type,
            'version' => $version,
            'title' => PolicyVersion::TYPES[$type],
            'summary' => "Current {$type} policy.",
            'content' => $content,
            'content_hash' => hash('sha256', $content),
            'requires_reacceptance' => $requiresReacceptance,
            'published_at' => now(),
        ], $overrides));
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->firstOrFail());

        return $user;
    }

    private function content(string $opening): string
    {
        return $opening."\n\n".str_repeat('This policy explains the standards, responsibilities, and directory enforcement process. ', 3);
    }
}
