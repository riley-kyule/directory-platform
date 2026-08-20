<?php

namespace App\Console\Commands;

use GuzzleHttp\TransferStats;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class LoadSmokeTest extends Command
{
    protected $signature = 'system:load-smoke
        {--base-url= : Target origin; defaults to APP_URL}
        {--requests=40 : Total measured requests (1-200)}
        {--concurrency=5 : Maximum concurrent requests (1-20)}
        {--timeout=10 : Per-request timeout in seconds (1-30)}
        {--max-p95-ms=1500 : Fail when p95 latency exceeds this threshold}
        {--path=* : Relative path to test; repeat for multiple paths}
        {--production : Explicitly authorize a bounded run against APP_ENV=production}';

    protected $description = 'Run a bounded HTTP load smoke test against public, cacheable pages';

    /** @var array<int, float> */
    private array $timings = [];

    public function handle(): int
    {
        $this->timings = [];

        if (app()->environment('production') && ! $this->option('production')) {
            $this->components->error('Production load smoke tests require the explicit --production flag.');

            return self::FAILURE;
        }

        $baseUrl = rtrim((string) ($this->option('base-url') ?: config('app.url')), '/');
        $requests = (int) $this->option('requests');
        $concurrency = (int) $this->option('concurrency');
        $timeout = (int) $this->option('timeout');
        $maxP95 = (float) $this->option('max-p95-ms');
        $paths = $this->paths();

        if (! $this->validBaseUrl($baseUrl)
            || $requests < 1 || $requests > 200
            || $concurrency < 1 || $concurrency > 20
            || $timeout < 1 || $timeout > 30
            || $maxP95 <= 0
            || $paths === null
        ) {
            $this->components->error('Invalid options. Use an HTTP(S) origin, relative paths, requests 1-200, concurrency 1-20, timeout 1-30, and a positive p95 target.');

            return self::INVALID;
        }

        $headers = ['User-Agent' => 'DirectoryPlatform-LoadSmoke/1.0', 'Accept' => 'text/html,application/xml'];
        foreach ($paths as $path) {
            try {
                $warm = Http::withHeaders($headers)->timeout($timeout)->get($baseUrl.$path);
                if (! $warm->successful()) {
                    $this->components->error("Warm-up failed for {$path} with HTTP {$warm->status()}.");

                    return self::FAILURE;
                }
            } catch (Throwable $exception) {
                $this->components->error("Warm-up failed for {$path}: {$exception->getMessage()}");

                return self::FAILURE;
            }
        }

        $planned = collect(range(0, $requests - 1))
            ->mapWithKeys(fn (int $index): array => [$index => $paths[$index % count($paths)]])
            ->all();
        $failures = [];
        $cacheHits = 0;

        foreach (array_chunk($planned, $concurrency, true) as $batch) {
            $batchStarted = hrtime(true);
            try {
                $responses = Http::pool(function (Pool $pool) use ($batch, $baseUrl, $headers, $timeout): array {
                    $pending = [];
                    foreach ($batch as $index => $path) {
                        $pending[] = $pool->as((string) $index)
                            ->withHeaders($headers)
                            ->timeout($timeout)
                            ->withOptions([
                                'on_stats' => function (TransferStats $stats) use ($index): void {
                                    $this->timings[$index] = $stats->getTransferTime() * 1000;
                                },
                            ])->get($baseUrl.$path);
                    }

                    return $pending;
                });
            } catch (Throwable $exception) {
                foreach ($batch as $index => $path) {
                    $failures[] = "#{$index} {$path}: {$exception->getMessage()}";
                }

                continue;
            }

            $fallbackMilliseconds = ((hrtime(true) - $batchStarted) / 1_000_000) / max(1, count($batch));
            foreach ($batch as $index => $path) {
                $response = $responses[(string) $index] ?? $responses[$index] ?? null;
                $this->timings[$index] ??= $fallbackMilliseconds;

                if (! $response instanceof Response || ! $response->successful()) {
                    $status = $response instanceof Response ? 'HTTP '.$response->status() : 'connection failure';
                    $failures[] = "#{$index} {$path}: {$status}";

                    continue;
                }

                if (strtolower((string) $response->header('X-Page-Cache')) === 'hit') {
                    $cacheHits++;
                }
            }
        }

        sort($this->timings);
        $completed = count($this->timings);
        $average = $completed > 0 ? array_sum($this->timings) / $completed : 0;
        $p95 = $this->percentile($this->timings, 95);

        $this->table(['Metric', 'Result'], [
            ['Target', $baseUrl],
            ['Requests', "{$requests} across ".count($paths).' paths'],
            ['Concurrency', $concurrency],
            ['Average latency', number_format($average, 1).' ms'],
            ['p95 latency', number_format($p95, 1).' ms'],
            ['Public-cache hits', $cacheHits],
            ['Failures', count($failures)],
        ]);

        foreach (array_slice($failures, 0, 10) as $failure) {
            $this->line(' - '.$failure);
        }

        if ($failures !== [] || $completed !== $requests || $p95 > $maxP95) {
            $this->components->error($p95 > $maxP95
                ? 'Load smoke test failed: p95 latency exceeded '.number_format($maxP95, 1).' ms.'
                : 'Load smoke test failed: one or more requests did not complete successfully.');

            return self::FAILURE;
        }

        $this->components->info('Load smoke test passed.');

        return self::SUCCESS;
    }

    /** @return array<int, string>|null */
    private function paths(): ?array
    {
        $paths = $this->option('path') ?: ['/', '/locations', '/sitemap.xml'];

        foreach ($paths as $path) {
            if (! is_string($path) || ! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '#')) {
                return null;
            }
        }

        return array_values(array_unique($paths));
    }

    private function validBaseUrl(string $url): bool
    {
        $parts = parse_url($url);

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            && filled($parts['host'] ?? null)
            && in_array($parts['path'] ?? '', ['', '/'], true)
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['query'])
            && ! isset($parts['fragment']);
    }

    /** @param array<int, float> $values */
    private function percentile(array $values, int $percentile): float
    {
        if ($values === []) {
            return 0;
        }

        $index = max(0, (int) ceil(($percentile / 100) * count($values)) - 1);

        return $values[$index];
    }
}
