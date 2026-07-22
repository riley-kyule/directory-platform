<?php

namespace App\Console\Commands;

use App\Enums\ProfileStatus;
use App\Models\AuditLog;
use App\Models\Profile;
use App\Services\LocationInventoryService;
use App\Services\ProfileImageVisibility;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireProfiles extends Command
{
    protected $signature = 'profiles:expire';

    protected $description = 'Make profiles private when their package assignment expires';

    public function handle(LocationInventoryService $locationInventory, ProfileImageVisibility $imageVisibility): int
    {
        $expired = 0;

        Profile::query()
            ->where('status', ProfileStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->select('id')
            ->chunkById(100, function ($profiles) use (&$expired, $locationInventory, $imageVisibility): void {
                foreach ($profiles as $candidate) {
                    DB::transaction(function () use ($candidate, &$expired, $locationInventory, $imageVisibility): void {
                        $profile = Profile::query()->lockForUpdate()->find($candidate->id);
                        if (! $profile || $profile->status !== ProfileStatus::Active || $profile->expires_at?->isFuture()) {
                            return;
                        }

                        $profile->packageAssignments()->where('status', 'active')->update(['status' => 'expired']);
                        $imageVisibility->unpublish($profile);
                        $profile->update(['status' => ProfileStatus::Expired]);
                        $locationInventory->syncForProfile($profile);
                        AuditLog::query()->create([
                            'action' => 'profiles.expire',
                            'target_type' => 'profile',
                            'target_id' => $profile->id,
                            'previous_state' => ['profile_status' => ProfileStatus::Active->value],
                            'new_state' => ['profile_status' => ProfileStatus::Expired->value],
                            'reason' => 'Package assignment expired.',
                        ]);
                        $expired++;
                    });
                }
            });

        $this->info("Expired {$expired} profile(s).");

        return self::SUCCESS;
    }
}
