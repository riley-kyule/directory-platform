<?php

namespace App\Services;

use App\Models\ModerationAction;
use App\Models\ModerationAppeal;
use App\Models\ProfileReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ModerationMetricsService
{
    /** @return array<string, mixed> */
    public function summary(): array
    {
        $sla = $this->slaSummary();

        return [
            'reports_by_status' => $this->countBy(ProfileReport::query(), 'status'),
            'reports_by_category' => $this->countBy(ProfileReport::query(), 'category'),
            'reports_by_priority' => $this->countBy(ProfileReport::query(), 'priority'),
            'open_urgent_reports' => ProfileReport::query()
                ->where('priority', 'urgent')
                ->whereIn('status', ['new', 'in_review'])
                ->count(),
            'average_resolution_hours' => $this->averageResolutionHours(),
            'actions_last_30_days' => $this->countBy(
                ModerationAction::query()->where('created_at', '>=', now()->subDays(30)),
                'action',
            ),
            'appeals_by_status' => $this->countBy(ModerationAppeal::query(), 'status'),
            'overdue_urgent_reports' => $sla['overdue_urgent_reports'],
            'overdue_normal_reports' => $sla['overdue_normal_reports'],
            'overdue_appeals' => $sla['overdue_appeals'],
            'unassigned_open_reports' => $sla['unassigned_open_reports'],
            'oldest_open_hours' => $sla['oldest_open_hours'],
        ];
    }

    /** @return array{overdue_urgent_reports: int, overdue_normal_reports: int, overdue_appeals: int, unassigned_open_reports: int, oldest_open_hours: ?float} */
    public function slaSummary(): array
    {
        $overdue = ProfileReport::query()->overdue();
        $oldest = ProfileReport::query()->open()->oldest('created_at')->value('created_at');

        return [
            'overdue_urgent_reports' => (clone $overdue)->where('priority', 'urgent')->count(),
            'overdue_normal_reports' => (clone $overdue)->where('priority', 'normal')->count(),
            'overdue_appeals' => ModerationAppeal::query()->overdue()->count(),
            'unassigned_open_reports' => ProfileReport::query()->open()->whereNull('assigned_to')->count(),
            'oldest_open_hours' => $oldest ? round(Carbon::parse($oldest)->diffInMinutes(now()) / 60, 1) : null,
        ];
    }

    private function countBy(Builder $query, string $column): Collection
    {
        return $query->select($column, DB::raw('count(*) as total'))
            ->groupBy($column)
            ->pluck('total', $column);
    }

    /**
     * Computed in PHP rather than a driver-specific DATEDIFF/TIMESTAMPDIFF
     * expression, since this runs against both MySQL in production and
     * SQLite in tests — moderation report volume is small enough that this
     * is not a performance concern.
     */
    private function averageResolutionHours(): ?float
    {
        $resolved = ProfileReport::query()
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', now()->subDays(90))
            ->get(['created_at', 'resolved_at']);
        if ($resolved->isEmpty()) {
            return null;
        }

        $averageMinutes = $resolved->avg(fn (ProfileReport $report) => $report->created_at->diffInMinutes($report->resolved_at));

        return round($averageMinutes / 60, 1);
    }
}
