<?php

namespace App\Console\Commands;

use App\Enums\ProfileStatus;
use App\Models\Profile;
use App\Services\ProfileVerificationService;
use Illuminate\Console\Command;

class AuditProfileVerification extends Command
{
    protected $signature = 'profiles:audit-verification {--json : Emit machine-readable JSON}';

    protected $description = 'Report active profiles that do not satisfy current verification requirements';

    public function handle(ProfileVerificationService $verification): int
    {
        $findings = Profile::query()
            ->where('status', ProfileStatus::Active)
            ->with(['owner:id,email', 'currentAgency.owner:id,email'])
            ->orderBy('id')
            ->get()
            ->map(function (Profile $profile) use ($verification): ?array {
                $missing = $verification->missingTypes($profile);
                if ($missing === []) {
                    return null;
                }

                return [
                    'id' => $profile->id,
                    'slug' => $profile->slug,
                    'owner' => $profile->owner?->email ?? $profile->currentAgency->first()?->owner?->email,
                    'verification_status' => $profile->verification_status,
                    'missing_requirements' => implode(', ', $missing),
                ];
            })
            ->filter()
            ->values();

        if ($this->option('json')) {
            $this->line($findings->toJson(JSON_PRETTY_PRINT));
        } elseif ($findings->isEmpty()) {
            $this->info('All active profiles satisfy current verification requirements.');
        } else {
            $this->warn($findings->count().' active profile(s) are excluded from public discovery until verified or overridden.');
            $this->table(['ID', 'Slug', 'Owner', 'Status', 'Missing requirements'], $findings->map(fn (array $finding) => array_values($finding)));
        }

        return $findings->isEmpty() ? self::SUCCESS : self::FAILURE;
    }
}
