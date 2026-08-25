<?php

namespace Tests\Feature;

use App\Jobs\RotateProfileListingOrderJob;
use App\Services\ListingRotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ListingRotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rotation_is_due_when_nothing_has_run_yet(): void
    {
        $this->assertTrue(app(ListingRotationService::class)->isDue());
    }

    public function test_rotation_is_not_due_again_immediately_after_running(): void
    {
        $rotation = app(ListingRotationService::class);
        $rotation->rotate();

        $this->assertFalse($rotation->isDue());
    }

    public function test_rotation_is_due_once_the_configured_interval_has_elapsed(): void
    {
        $rotation = app(ListingRotationService::class);
        $rotation->rotate();

        $this->travel(25)->hours();

        $this->assertTrue($rotation->isDue());
    }

    public function test_an_unusable_cached_timestamp_is_treated_as_due_instead_of_throwing(): void
    {
        // Mirrors what production hit: a cached "last rotation" value that
        // can't be read back as a real Carbon instance (there it was an
        // __PHP_Incomplete_Class). This must fail open, not throw, since it
        // now runs on every public page load.
        Cache::put('directory-listings:last-rotation', 'not-a-carbon-instance', now()->addDay());

        $this->assertTrue(app(ListingRotationService::class)->isDue());
    }

    public function test_trigger_if_due_dispatches_the_rotation_job(): void
    {
        Queue::fake();

        app(ListingRotationService::class)->triggerIfDue();

        Queue::assertPushed(RotateProfileListingOrderJob::class);
    }

    public function test_trigger_if_due_does_not_dispatch_again_while_a_claim_is_outstanding(): void
    {
        Queue::fake();
        $rotation = app(ListingRotationService::class);

        $rotation->triggerIfDue();
        $rotation->triggerIfDue();

        Queue::assertPushed(RotateProfileListingOrderJob::class, 1);
    }

    public function test_trigger_if_due_does_nothing_once_rotation_already_ran(): void
    {
        Queue::fake();
        $rotation = app(ListingRotationService::class);
        $rotation->rotate();

        $rotation->triggerIfDue();

        Queue::assertNotPushed(RotateProfileListingOrderJob::class);
    }

    public function test_a_public_page_load_triggers_the_rotation_job_when_due(): void
    {
        Queue::fake();

        $this->get(route('directory.home'));

        Queue::assertPushed(RotateProfileListingOrderJob::class);
    }

    public function test_a_public_page_load_does_not_dispatch_when_not_due(): void
    {
        app(ListingRotationService::class)->rotate();
        Queue::fake();

        $this->get(route('directory.home'));

        Queue::assertNotPushed(RotateProfileListingOrderJob::class);
    }
}
