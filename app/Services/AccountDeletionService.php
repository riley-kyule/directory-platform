<?php

namespace App\Services;

use App\Enums\ProfileStatus;
use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

/**
 * Handles the moment a user deletes their own account: their public
 * presence (profile, or an owned agency and its currently-active listings)
 * goes private immediately. The account row itself is only soft-deleted
 * here — see PurgeDeletedAccounts for what happens after the retention
 * window in operations.account_deletion_retention_days.
 */
class AccountDeletionService
{
    public function __construct(private readonly ModerationEnforcementService $moderation) {}

    public function deactivatePublicPresence(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $profile = $user->profile;
            if ($profile && $profile->status === ProfileStatus::Active) {
                $this->moderation->makePrivate($profile);
            }

            $agency = $user->agency;
            $affectedProfileIds = [];
            if ($agency) {
                $affectedProfileIds = $agency->publicProfiles->pluck('id')->all();
                $agency->publicProfiles->each(fn (Profile $agencyProfile) => $this->moderation->makePrivate($agencyProfile));
                $agency->update(['status' => 'inactive']);
            }

            AuditLog::query()->create([
                'actor_user_id' => $user->id,
                'action' => 'accounts.self-delete',
                'target_type' => 'user',
                'target_id' => $user->id,
                'new_state' => [
                    'profile_id' => $profile?->id,
                    'agency_id' => $agency?->id,
                    'agency_profile_ids_deactivated' => $affectedProfileIds,
                ],
                'reason' => 'Account deletion requested by owner.',
                'ip_address' => Request::ip(),
                'user_agent' => str((string) Request::userAgent())->limit(500)->toString(),
            ]);
        });
    }
}
