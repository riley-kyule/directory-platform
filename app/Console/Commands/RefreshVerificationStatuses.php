<?php

namespace App\Console\Commands;

use App\Models\Profile;
use App\Services\ProfileVerificationService;
use Illuminate\Console\Command;

class RefreshVerificationStatuses extends Command
{
    protected $signature = 'verification:refresh-statuses';

    protected $description = 'Recalculate profile verification status after check expiry';

    public function handle(ProfileVerificationService $verification): int
    {
        $count = 0;
        Profile::query()
            ->whereHas('verificationChecks')
            ->chunkById(100, function ($profiles) use ($verification, &$count): void {
                foreach ($profiles as $profile) {
                    $before = $profile->verification_status;
                    $after = $verification->sync($profile);
                    $count += (int) ($before !== $after);
                }
            });
        $this->info("Updated {$count} verification status(es).");

        return self::SUCCESS;
    }
}
