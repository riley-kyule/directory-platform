<?php

namespace App\Http\Controllers;

use App\Services\DirectorySettings;
use App\Services\PwaIconService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class PwaController extends Controller
{
    public function serviceWorker(): Response
    {
        $contents = file_get_contents(resource_path('pwa/sw.js'));
        abort_unless(is_string($contents), 404);
        $manifestPath = public_path('build/manifest.json');
        $manifestHash = is_file($manifestPath) ? hash_file('sha256', $manifestPath) : false;
        $version = is_string($manifestHash) ? substr($manifestHash, 0, 12) : 'development';
        $contents = str_replace('__VERSION__', $version, $contents);

        return response($contents, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    public function manifest(DirectorySettings $settings): JsonResponse
    {
        $name = $settings->string('site.platform_name') ?: (string) config('app.name');

        return response()->json([
            'id' => '/',
            'name' => $name,
            'short_name' => Str::limit($name, 24, ''),
            'description' => 'Browse the directory and discover active listings.',
            'lang' => str_replace('_', '-', (string) config('app.locale')),
            'dir' => 'ltr',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#171717',
            'theme_color' => '#171717',
            'categories' => ['lifestyle'],
            'icons' => [
                ['src' => route('pwa.icon', 192, false), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => route('pwa.icon', 512, false), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => route('pwa.icon.maskable', 512, false), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'prefer_related_applications' => false,
        ], 200, [
            'Content-Type' => 'application/manifest+json; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600, must-revalidate',
        ], JSON_UNESCAPED_SLASHES);
    }

    public function icon(int $size, PwaIconService $icons): Response
    {
        return $this->iconResponse($icons->render($size));
    }

    public function maskableIcon(int $size, PwaIconService $icons): Response
    {
        return $this->iconResponse($icons->render($size, true));
    }

    private function iconResponse(string $contents): Response
    {
        return response($contents, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=3600, must-revalidate',
            'ETag' => '"'.hash('sha256', $contents).'"',
        ]);
    }
}
