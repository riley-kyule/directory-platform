<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceCanonicalHost;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CanonicalHostTest extends TestCase
{
    public function test_safe_requests_to_an_alternate_production_host_redirect_to_the_canonical_host(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config([
            'app.url' => 'https://www.example.test',
            'security.canonical_host' => 'www.example.test',
        ]);

        $request = Request::create('http://alternate.example.test/search?q=nairobi', 'GET');
        $response = app(EnforceCanonicalHost::class)->handle($request, fn () => response('continued'));

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('https://www.example.test/search?q=nairobi', $response->headers->get('Location'));
    }

    public function test_non_idempotent_requests_to_an_alternate_production_host_are_rejected(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config([
            'security.canonical_host' => 'www.example.test',
        ]);

        $this->expectException(HttpException::class);

        app(EnforceCanonicalHost::class)->handle(
            Request::create('https://alternate.example.test/profile', 'POST'),
            fn () => response('continued'),
        );
    }
}
