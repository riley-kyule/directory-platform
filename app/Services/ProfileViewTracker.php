<?php

namespace App\Services;

use App\Models\Profile;
use Illuminate\Support\Facades\DB;

class ProfileViewTracker
{
    public function record(Profile $profile): void
    {
        DB::table('profile_view_daily')->upsert([
            [
                'event_date' => now()->toDateString(),
                'profile_id' => $profile->id,
                'view_count' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['event_date', 'profile_id'], [
            'view_count' => DB::raw('view_count + 1'),
            'updated_at',
        ]);
    }
}
