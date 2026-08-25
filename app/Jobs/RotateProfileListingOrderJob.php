<?php

namespace App\Jobs;

use App\Services\ListingRotationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RotateProfileListingOrderJob implements ShouldQueue
{
    use Queueable;

    public function handle(ListingRotationService $rotation): void
    {
        $rotation->rotate();
    }
}
