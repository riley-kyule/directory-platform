<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LoadSmokeTest extends TestCase
{
    public function test_bounded_load_smoke_reports_latency_cache_and_success(): void
    {
        Http::fake([
            'https://directory.example/*' => Http::response('ok', 200, ['X-Page-Cache' => 'hit']),
        ]);

        $exit = Artisan::call('system:load-smoke', [
            '--base-url' => 'https://directory.example',
            '--requests' => 12,
            '--concurrency' => 3,
            '--max-p95-ms' => 1000,
        ]);

        $this->assertSame(0, $exit);
        $output = Artisan::output();
        $this->assertStringContainsString('Load smoke test passed', $output);
        $this->assertStringContainsString('Public-cache hits', $output);
        Http::assertSentCount(15);
    }

    public function test_load_smoke_fails_on_an_unhealthy_warmup(): void
    {
        Http::fake([
            'https://directory.example/*' => Http::response('unavailable', 503),
        ]);

        $exit = Artisan::call('system:load-smoke', [
            '--base-url' => 'https://directory.example',
            '--requests' => 5,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Warm-up failed', Artisan::output());
    }

    public function test_load_smoke_rejects_unsafe_or_unbounded_options(): void
    {
        $exit = Artisan::call('system:load-smoke', [
            '--base-url' => 'file:///etc/passwd',
            '--requests' => 1000,
            '--path' => ['https://other.example/'],
        ]);

        $this->assertSame(2, $exit);
        $this->assertStringContainsString('Invalid options', Artisan::output());
        Http::assertNothingSent();
    }
}
