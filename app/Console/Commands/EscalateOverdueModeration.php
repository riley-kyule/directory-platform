<?php

namespace App\Console\Commands;

use App\Models\ModerationAppeal;
use App\Models\ProfileReport;
use App\Models\SystemHeartbeat;
use App\Models\User;
use App\Notifications\OverdueModerationDigestNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Throwable;

class EscalateOverdueModeration extends Command
{
    protected $signature = 'moderation:escalate-overdue';

    protected $description = 'Notify active Admin and CSR staff about newly overdue moderation reports and appeals';

    public function handle(): int
    {
        $reports = ProfileReport::query()->overdue()->whereNull('sla_escalated_at')->get();
        $appeals = ModerationAppeal::query()->overdue()->whereNull('sla_escalated_at')->get();
        $recipients = User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['admin', 'csr']))
            ->get();

        $urgent = $reports->where('priority', 'urgent')->count();
        $normal = $reports->where('priority', 'normal')->count();
        $metadata = [
            'urgent_reports' => $urgent,
            'normal_reports' => $normal,
            'appeals' => $appeals->count(),
            'recipients' => $recipients->count(),
        ];
        if ($reports->isEmpty() && $appeals->isEmpty()) {
            $this->recordHeartbeat($metadata + ['status' => 'ok']);
            $this->info('No newly overdue moderation cases.');

            return self::SUCCESS;
        }

        if ($recipients->isEmpty()) {
            $this->recordHeartbeat($metadata + ['status' => 'blocked']);
            $this->error('Overdue moderation cases exist, but no active Admin or CSR recipient is available.');

            return self::FAILURE;
        }

        try {
            Notification::send($recipients, new OverdueModerationDigestNotification($urgent, $normal, $appeals->count()));
        } catch (Throwable $exception) {
            $this->recordHeartbeat($metadata + ['status' => 'failed']);
            $this->error('Moderation escalation notification failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $escalatedAt = now();
        ProfileReport::query()->whereKey($reports->modelKeys())->update(['sla_escalated_at' => $escalatedAt]);
        ModerationAppeal::query()->whereKey($appeals->modelKeys())->update(['sla_escalated_at' => $escalatedAt]);
        $this->recordHeartbeat($metadata + ['status' => 'delivered']);

        $this->info("Escalated {$reports->count()} report(s) and {$appeals->count()} appeal(s) to {$recipients->count()} staff recipient(s).");

        return self::SUCCESS;
    }

    /** @param array<string, int|string> $metadata */
    private function recordHeartbeat(array $metadata): void
    {
        SystemHeartbeat::query()->updateOrCreate(
            ['name' => 'moderation_escalation'],
            ['last_seen_at' => now(), 'metadata' => $metadata],
        );
    }
}
