<?php

namespace App\Services;

use App\Models\Profile;
use Illuminate\Support\Facades\DB;

class ProfileConversionTracker
{
    public const CHANNELS = ['call', 'sms', 'whatsapp', 'telegram'];

    public const PLACEMENTS = ['profile_card', 'profile_page', 'mobile_bar'];

    public function record(Profile $profile, string $channel, string $placement): void
    {
        DB::table('profile_conversion_daily')->upsert([
            [
                'event_date' => now()->toDateString(),
                'profile_id' => $profile->id,
                'channel' => $channel,
                'placement' => $placement,
                'contact_count' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['event_date', 'profile_id', 'channel', 'placement'], [
            'contact_count' => DB::raw('contact_count + 1'),
            'updated_at',
        ]);
    }
}
