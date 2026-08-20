<?php

namespace App\Providers;

use App\Models\User;
use App\Services\DirectorySettings;
use App\Services\LocationSidebarTree;
use App\Services\PolicyAcceptanceService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

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
        RateLimiter::for('public-reviews', fn (Request $request) => [
            Limit::perMinutes(10, 5)->by('reviews:ip:'.$this->rateLimitDigest($request->ip() ?? 'unknown')),
            Limit::perDay(2)->by('reviews:identity:'.$this->submissionIdentity($request)),
        ]);
        RateLimiter::for('public-reports', fn (Request $request) => [
            Limit::perMinutes(10, 5)->by('reports:ip:'.$this->rateLimitDigest($request->ip() ?? 'unknown')),
            Limit::perHour(3)->by('reports:identity:'.$this->submissionIdentity($request)),
        ]);

        Gate::before(function (User $user, string $ability): ?bool {
            return $user->hasPermission($ability) ? true : null;
        });

        // config('app.name') already drives the logo, page titles, JSON-LD,
        // and legal-policy variables everywhere else in the app — overriding
        // it here once, instead of at each call site, is what makes the
        // admin-editable "Website title" setting take effect app-wide.
        // Guarded: on a brand-new install this runs before migrations exist.
        try {
            $platformName = app(DirectorySettings::class)->string('site.platform_name');
            if (filled($platformName)) {
                config(['app.name' => $platformName]);
            }
        } catch (Throwable) {
            // directory_settings table not migrated yet — keep APP_NAME as-is.
        }

        // Cloudflare (or any TLS-terminating proxy) forwards over plain HTTP
        // internally, so without this, asset/route URLs can render as http://
        // even though the visitor is on https://.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        View::composer('layouts.public', function ($view): void {
            $view->with('publishedPolicies', app(PolicyAcceptanceService::class)->latestPublished());
        });

        View::composer('directory.index', function ($view): void {
            $view->with('activeLocations', app(LocationSidebarTree::class)->build());
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

    private function submissionIdentity(Request $request): string
    {
        $profile = $request->route('profile');
        $profileKey = is_object($profile) && isset($profile->id) ? $profile->id : (string) $profile;
        $email = strtolower(trim((string) $request->input('email')));
        $identity = $email !== '' ? $email : 'ip:'.($request->ip() ?? 'unknown');

        return $this->rateLimitDigest($profileKey.'|'.$identity);
    }

    private function rateLimitDigest(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
