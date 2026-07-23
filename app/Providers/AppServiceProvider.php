<?php

namespace App\Providers;

use App\Models\User;
use App\Services\PolicyAcceptanceService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->hasPermission($ability) ? true : null;
        });

        View::composer('layouts.public', function ($view): void {
            $view->with('publishedPolicies', app(PolicyAcceptanceService::class)->latestPublished());
        });

        DB::listen(function (QueryExecuted $query): void {
            if ($query->time < config('operations.slow_query_milliseconds')) {
                return;
            }

            Log::warning('Slow database query detected.', [
                'connection' => $query->connectionName,
                'duration_ms' => $query->time,
                'operation' => str($query->sql)->trim()->before(' ')->upper()->toString(),
                'query_hash' => hash('sha256', $query->sql),
            ]);
        });
    }
}
