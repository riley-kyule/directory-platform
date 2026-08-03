<?php

namespace App\Services;

use App\Models\SearchTermLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Privacy-safe search-term popularity tracking.
 *
 * Every search increments a per-day, per-term counter in cache — never
 * durably stored, never tied to a user/session/IP. Only once a term
 * crosses the daily threshold does it get a row in search_term_logs, so a
 * one-off or rare query (more likely to be personally identifying) never
 * touches persistent storage; only genuinely common terms do.
 */
class SearchTermLogger
{
    private const DAILY_THRESHOLD = 10;

    private const COUNTER_TTL_SECONDS = 25 * 60 * 60;

    public function record(string $term): void
    {
        $normalized = Str::of($term)->squish()->lower()->toString();
        if ($normalized === '') {
            return;
        }

        $date = now()->toDateString();
        $key = $this->counterKey($date, $normalized);

        Cache::add($key, 0, self::COUNTER_TTL_SECONDS);
        $count = Cache::increment($key);
        if ($count === false || $count <= self::DAILY_THRESHOLD) {
            return;
        }

        SearchTermLog::query()->updateOrCreate(
            ['search_date' => $date, 'term' => $normalized],
            ['search_count' => $count],
        );
    }

    private function counterKey(string $date, string $normalizedTerm): string
    {
        return 'search-term-count:'.$date.':'.sha1($normalizedTerm);
    }
}
