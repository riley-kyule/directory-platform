<?php

namespace App\Services;

use App\Enums\ProfileStatus;
use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

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

    public function deleteAccount(
        User $user,
        User $actor,
        string $action = 'accounts.self-delete',
        string $reason = 'Account deletion requested by owner.',
    ): void {
        DB::transaction(function () use ($user, $actor, $action, $reason): void {
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
                'actor_user_id' => $actor->id,
                'action' => $action,
                'target_type' => 'user',
                'target_id' => $user->id,
                'previous_state' => $action === 'users.delete' ? ['email' => $user->email] : null,
                'new_state' => [
                    'profile_id' => $profile?->id,
                    'agency_id' => $agency?->id,
                    'agency_profile_ids_deactivated' => $affectedProfileIds,
                ],
                'reason' => $reason,
                'ip_address' => Request::ip(),
                'user_agent' => str((string) Request::userAgent())->limit(500)->toString(),
            ]);

            // Soft deletes still participate in unique indexes. Release both
            // login identifiers now so the owner can register the email again
            // and a replacement account can link the same Google identity.
            $user->forceFill([
                'email' => 'deleted-'.($user->public_id ?: Str::uuid()).'@deleted.invalid',
                'google_subject' => null,
                'google_sso_linked_at' => null,
                'google_sso_last_login_at' => null,
                'remember_token' => null,
            ])->save();

            $user->delete();
        });
    }
}
