<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Location;
use App\Models\PageContent;
use App\Models\Profile;
use App\Models\Role;
use App\Models\SearchTermLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    /** @return array<string, mixed> */
    public function summary(): array
    {
        return [
            'profiles_by_status' => $this->countBy(Profile::query(), 'status')
                ->mapWithKeys(fn ($total, $status) => [$status instanceof \BackedEnum ? $status->value : $status => $total]),
            'profiles_active' => Profile::query()->publiclyVisible()->count(),
            'pages_count' => PageContent::query()->count() + Location::query()->count(),
            'locations_published' => Location::query()->where('status', 'published')->where('is_indexable', true)->count(),
            'users_total' => User::query()->count(),
            'users_by_role' => Role::query()->withCount('users')->get(['id', 'name', 'slug'])->pluck('users_count', 'name'),
            'recent_activity' => AuditLog::query()->with('actor')->latest()->limit(10)->get(),
            'search_top_terms' => SearchTermLog::query()
                ->where('search_date', '>=', now()->subDays(7)->toDateString())
                ->orderByDesc('search_count')
                ->limit(5)
                ->get(['term', 'search_count']),
            'search_total_last_7_days' => (int) SearchTermLog::query()
                ->where('search_date', '>=', now()->subDays(7)->toDateString())
                ->sum('search_count'),
        ];
    }

    private function countBy(Builder $query, string $column): Collection
    {
        return $query->select($column, DB::raw('count(*) as total'))
            ->groupBy($column)
            ->pluck('total', $column);
    }
}
