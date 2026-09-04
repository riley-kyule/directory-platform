<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Models\ModerationAppeal;
use App\Models\Profile;
use App\Models\ProfileImage;
use App\Models\ProfileVideo;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Finishes what account deletion starts: once a soft-deleted account is past
 * operations.account_deletion_retention_days, permanently delete the data
 * that's genuinely theirs (an owned profile and its media, an owned agency
 * shell) and anonymize the user row itself rather than deleting it.
 *
 * The row survives, scrubbed, because several tables intentionally use
 * restrictOnDelete on user references — policy_acceptances.user_id and the
 * requested_by/assigned_by columns on package requests/assignments — which
 * is the schema protecting compliance evidence from ever silently
 * disappearing. Fighting that with a hard delete would mean either deleting
 * evidence the schema was clearly built to keep, or the delete failing
 * outright. Anonymizing respects both: the PII is gone, the audit trail
 * that references this user id stays intact.
 */
class PurgeDeletedAccounts extends Command
{
    protected $signature = 'accounts:purge-deleted';

    protected $description = 'Permanently delete owned profile/agency data and anonymize accounts past the deletion retention window';

    public function handle(): int
    {
        $cutoff = now()->subDays(config('operations.account_deletion_retention_days'));
        $purged = 0;
        $skipped = 0;

        User::onlyTrashed()
            ->whereNull('anonymized_at')
            ->where('deleted_at', '<=', $cutoff)
            ->each(function (User $user) use (&$purged, &$skipped): void {
                try {
                    DB::transaction(function () use ($user): void {
                        $this->purgeOwnedProfile($user);
                        $this->purgeOwnedAgency($user);
                        $this->anonymize($user);
                    });
                    $purged++;
                } catch (Throwable $exception) {
                    // Most likely an agency still owns member profiles some other
                    // way we haven't accounted for, or a new restrictOnDelete this
                    // command doesn't know about yet. Leave the account soft-deleted
                    // rather than partially purge it — surface it for a human instead
                    // of silently losing data or corrupting referential integrity.
                    $this->warn("Skipped user #{$user->id}: {$exception->getMessage()}");
                    $skipped++;
                }
            });

        $this->info("Purged {$purged} account(s). Skipped {$skipped} for manual review.");

        return self::SUCCESS;
    }

    private function purgeOwnedProfile(User $user): void
    {
        $profile = Profile::withTrashed()->where('owner_user_id', $user->id)->first();
        if (! $profile) {
            return;
        }

        ProfileImage::withTrashed()->where('profile_id', $profile->id)->get()->each(function (ProfileImage $image) use ($profile): void {
            Storage::disk('quarantine')->delete($profile->public_id.'/'.$image->public_id.'.upload');
            if ($image->storage_directory) {
                Storage::disk('media_review')->deleteDirectory($image->storage_directory);
                Storage::disk('profile_media')->deleteDirectory($image->storage_directory);
            }
        });

        ProfileVideo::withTrashed()->where('profile_id', $profile->id)->get()->each(function (ProfileVideo $video) use ($profile): void {
            Storage::disk('quarantine')->delete('videos/'.$profile->public_id.'/'.$video->public_id.'.upload');
            if ($video->storage_directory) {
                Storage::disk('media_review')->deleteDirectory($video->storage_directory);
                Storage::disk('profile_media')->deleteDirectory($video->storage_directory);
            }
        });

        // Deleted explicitly, ahead of the cascade: moderation_appeals restricts
        // deleting the moderation_action it references, which would otherwise
        // collide with that same action cascading away via this profile delete.
        ModerationAppeal::query()->where('profile_id', $profile->id)->delete();

        $profile->forceDelete();
    }

    private function purgeOwnedAgency(User $user): void
    {
        $agency = Agency::withTrashed()->where('owner_user_id', $user->id)->first();
        if (! $agency) {
            return;
        }

        // Only detaches agency_profiles pivot rows (cascadeOnDelete); the member
        // profiles themselves — other people's listings — are left untouched.
        $agency->forceDelete();
    }

    private function anonymize(User $user): void
    {
        $user->forceFill([
            'name' => 'Deleted user',
            'email' => 'deleted-'.Str::uuid().'@deleted.invalid',
            'password' => Str::random(60),
            'google_subject' => null,
            'google_sso_linked_at' => null,
            'google_sso_last_login_at' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_counter' => null,
            'anonymized_at' => now(),
        ])->save();
    }
}
