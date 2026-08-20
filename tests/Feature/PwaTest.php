<?php

namespace Tests\Feature;

use App\Models\DirectorySetting;
use Database\Seeders\DirectoryDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DirectoryDefaultsSeeder::class);
    }

    public function test_manifest_exposes_installable_metadata_and_privacy_safe_icons(): void
    {
        DirectorySetting::query()->create([
            'key' => 'site.platform_name',
            'value' => 'Example Directory',
            'value_type' => 'string',
            'group' => 'site',
        ]);

        $response = $this->get(route('pwa.manifest'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json; charset=UTF-8')
            ->assertJsonPath('name', 'Example Directory')
            ->assertJsonPath('start_url', '/')
            ->assertJsonPath('scope', '/')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('icons.0.sizes', '192x192')
            ->assertJsonPath('icons.1.sizes', '512x512')
            ->assertJsonPath('icons.2.purpose', 'maskable')
            ->assertJsonPath('prefer_related_applications', false);
        $this->assertStringContainsString('max-age=3600', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('must-revalidate', (string) $response->headers->get('Cache-Control'));
        $this->assertFalse($response->headers->has('Set-Cookie'));
    }

    public function test_public_pages_advertise_manifest_ios_metadata_and_worker_policy(): void
    {
        $response = $this->get(route('directory.home'))
            ->assertOk()
            ->assertSee('<link rel="manifest" href="'.route('pwa.manifest').'">', false)
            ->assertSee('rel="apple-touch-icon" sizes="180x180"', false)
            ->assertSee('<meta name="mobile-web-app-capable" content="yes">', false)
            ->assertSee('<meta name="apple-mobile-web-app-capable" content="yes">', false);
        $policy = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("manifest-src 'self'", $policy);
        $this->assertStringContainsString("worker-src 'self'", $policy);
    }

    public function test_pwa_icons_are_valid_pngs_at_required_sizes(): void
    {
        foreach ([180, 192, 512] as $size) {
            $response = $this->get(route('pwa.icon', $size))->assertOk()->assertHeader('Content-Type', 'image/png');
            $dimensions = getimagesizefromstring($response->getContent());
            $this->assertIsArray($dimensions);
            $this->assertSame($size, $dimensions[0]);
            $this->assertSame($size, $dimensions[1]);
            $this->assertSame(IMAGETYPE_PNG, $dimensions[2]);
            $this->assertFalse($response->headers->has('Set-Cookie'));
        }

        $response = $this->get(route('pwa.icon.maskable', 512))->assertOk();
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $response->getContent());
        $this->get('/pwa/icon-256.png')->assertNotFound();
    }

    public function test_service_worker_never_persists_sensitive_content(): void
    {
        $workerResponse = $this->get(route('pwa.worker'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8')
            ->assertHeader('Service-Worker-Allowed', '/');
        $workerCacheControl = (string) $workerResponse->headers->get('Cache-Control');
        $this->assertStringContainsString('no-cache', $workerCacheControl);
        $this->assertStringContainsString('no-store', $workerCacheControl);
        $this->assertStringContainsString('must-revalidate', $workerCacheControl);
        $this->assertFalse($workerResponse->headers->has('Set-Cookie'));
        $worker = $workerResponse->getContent();
        $offline = file_get_contents(public_path('offline.html'));

        $this->assertIsString($worker);
        $this->assertStringNotContainsString('__VERSION__', $worker);
        $this->assertMatchesRegularExpression("/const CACHE_VERSION = 'directory-pwa-[a-f0-9]{12}';/", $worker);
        foreach (['/escort/', '/media/', '/branding/', '/conversion/', '/login', '/admin', '/staff', '/seo', '/my-profiles'] as $prefix) {
            $this->assertStringContainsString("'{$prefix}'", $worker);
        }
        $this->assertStringContainsString("request.mode === 'navigate'", $worker);
        $this->assertStringContainsString('fetch(request).catch(() => caches.match(OFFLINE_URL))', $worker);
        $this->assertStringContainsString("url.pathname.startsWith('/build/')", $worker);

        $this->assertIsString($offline);
        $this->assertStringContainsString('noindex,nofollow', $offline);
        $this->assertStringContainsString('never stored for offline viewing', $offline);
    }
}
