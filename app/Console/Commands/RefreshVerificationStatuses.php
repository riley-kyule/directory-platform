<?php

namespace App\Console\Commands;

use App\Enums\ProfileStatus;
use App\Models\AuditLog;
use App\Models\Profile;
use App\Notifications\ProfileVerificationExpiredNotification;
use App\Services\ModerationEnforcementService;
use App\Services\ProfileVerificationService;
use Illuminate\Console\Command;

class RefreshVerificationStatuses extends Command
{
    protected $signature = 'verification:refresh-statuses';

    protected $description = 'Recalculate profile verification status after check expiry';

    public function handle(ProfileVerificationService $verification, ModerationEnforcementService $moderation): int
    {
        $count = 0;
        Profile::query()
            ->whereHas('verificationChecks')
            ->chunkById(100, function ($profiles) use ($verification, $moderation, &$count): void {
                foreach ($profiles as $profile) {
                    $before = $profile->verification_status;
                    $after = $verification->sync($profile);
                    if ($before === 'verified' && $after !== 'verified' && $profile->status === ProfileStatus::Active) {
                        $moderation->makePrivate($profile);
                        AuditLog::query()->create([
                            'action' => 'verification.expired-deactivation',
                            'target_type' => 'profile',
                            'target_id' => $profile->id,
                            'previous_state' => [
                                'profile_status' => ProfileStatus::Active->value,
                                'verification_status' => $before,
                            ],
                            'new_state' => [
                                'profile_status' => ProfileStatus::Deactivated->value,
                                'verification_status' => $after,
                            ],
                            'reason' => 'A required verification check is no longer current.',
                        ]);
                        $owner = $profile->owner ?? $profile->currentAgency()->first()?->owner;
                        $owner?->notify(new ProfileVerificationExpiredNotification($profile->id, $profile->display_name));
                    }
                    $count += (int) ($before !== $after);
                }
            });
        $this->info("Updated {$count} verification status(es).");

        return self::SUCCESS;
    }
}
