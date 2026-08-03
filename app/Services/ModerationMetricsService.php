<?php

namespace App\Services;

use App\Models\ModerationAction;
use App\Models\ModerationAppeal;
use App\Models\ProfileReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ModerationMetricsService
{
    /** @return array<string, mixed> */
    public function summary(): array
    {
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
        $resolved = ProfileReport::query()->whereNotNull('resolved_at')->get(['created_at', 'resolved_at']);
        if ($resolved->isEmpty()) {
            return null;
        }

        $averageMinutes = $resolved->avg(fn (ProfileReport $report) => $report->created_at->diffInMinutes($report->resolved_at));

        return round($averageMinutes / 60, 1);
    }
}
