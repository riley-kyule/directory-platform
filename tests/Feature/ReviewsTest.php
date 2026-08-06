<?php

namespace Tests\Feature;

use App\Enums\ProfileStatus;
use App\Models\Location;
use App\Models\Package;
use App\Models\Profile;
use App\Models\Review;
use App\Models\Role;
use App\Models\TaxonomyOption;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReviewsTest extends TestCase
{
    use RefreshDatabase;

    private Profile $profile;

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
        $owner = User::factory()->create();
        $this->profile = Profile::query()->create([
            'owner_user_id' => $owner->id,
            'display_name' => 'Reviewed Jane', 'slug' => 'reviewed-jane',
            'description' => 'A complete active profile used for the reviews workflow.',
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
            'assigned_by' => $owner->id, 'assignment_source' => 'manual', 'reason' => 'Initial activation.',
        ]);
    }

    public function test_review_form_page_renders_and_is_not_publicly_cached(): void
    {
        // The profile show page IS whole-response cached for guests (cache.public
        // middleware) — a CSRF-protected form must never live on that page, since a
        // cached response would embed a stale token from whoever's request first
        // populated the cache. The review form deliberately lives on its own,
        // uncached page instead, mirroring the existing report form.
        $response = $this->get(route('directory.profiles.reviews.create', $this->profile));
        $response->assertOk()->assertSee('Leave a review');
        $this->assertFalse($response->headers->has('X-Page-Cache'));
    }

    public function test_guest_can_submit_a_review_and_it_starts_pending_and_hidden(): void
    {
        $this->post(route('directory.profiles.reviews.store', $this->profile), [
            'rating' => 5,
            'body' => 'A genuinely helpful and professional experience from start to finish.',
            'reviewer_name' => 'Happy Client',
            'email' => 'Client@Example.com',
        ])->assertRedirect(route('directory.profiles.show', $this->profile->slug));

        $review = Review::query()->firstOrFail();
        $this->assertSame('pending', $review->status);
        $this->assertSame('client@example.com', $review->reviewer_email);
        $this->assertNotSame('client@example.com', $review->getRawOriginal('reviewer_email'));
        $this->assertNotNull($review->source_fingerprint);

        $this->get(route('directory.profiles.show', $this->profile->slug))
            ->assertOk()
            ->assertDontSee('Happy Client')
            ->assertSee('No reviews yet');
    }

    public function test_review_submission_rejects_honeypot_and_short_body(): void
    {
        $this->post(route('directory.profiles.reviews.store', $this->profile), [
            'rating' => 5,
            'body' => 'Too short.',
            'email' => 'person@example.com',
            'website' => 'spam.example',
        ])->assertSessionHasErrors(['body', 'website']);

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_reviews_queue_requires_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('staff.reviews.index'))
            ->assertForbidden();
    }

    public function test_csr_can_approve_a_review_and_it_then_appears_publicly(): void
    {
        $review = $this->pendingReview();
        $csr = $this->staff('csr');

        $this->actingAs($csr)->get(route('staff.reviews.index'))
            ->assertOk()
            ->assertSee('Great service.');

        $this->actingAs($csr)->patch(route('staff.reviews.update', $review), [
            'action' => 'approve',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $review->refresh();
        $this->assertSame('published', $review->status);
        $this->assertSame($csr->id, $review->moderated_by);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'reviews.approve', 'target_type' => 'review', 'target_id' => $review->id,
        ]);

        $this->get(route('directory.profiles.show', $this->profile->slug))
            ->assertOk()
            ->assertSee('Great service.')
            ->assertSee('5.0');
    }

    public function test_csr_can_reject_a_review_and_it_never_appears_publicly(): void
    {
        $review = $this->pendingReview();
        $csr = $this->staff('csr');

        $this->actingAs($csr)->patch(route('staff.reviews.update', $review), [
            'action' => 'reject',
            'reason' => 'Fails to describe a genuine, verifiable experience.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $review->refresh();
        $this->assertSame('rejected', $review->status);
        $this->assertNotNull($review->moderation_reason);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reviews.reject', 'target_id' => $review->id]);

        $this->get(route('directory.profiles.show', $this->profile->slug))
            ->assertOk()
            ->assertDontSee('Great service.');
    }

    public function test_rejecting_a_review_requires_a_reason(): void
    {
        $review = $this->pendingReview();

        $this->actingAs($this->staff('csr'))->patch(route('staff.reviews.update', $review), [
            'action' => 'reject',
        ])->assertSessionHasErrors('reason');

        $this->assertSame('pending', $review->refresh()->status);
    }

    private function pendingReview(): Review
    {
        return Review::query()->create([
            'profile_id' => $this->profile->id,
            'reviewer_name' => 'Happy Client',
            'reviewer_email' => 'client@example.com',
            'reviewer_email_hash' => hash('sha256', 'client@example.com'),
            'rating' => 5,
            'body' => 'Great service. Would recommend to anyone looking for a reliable, professional experience.',
            'status' => 'pending',
        ]);
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }
}
