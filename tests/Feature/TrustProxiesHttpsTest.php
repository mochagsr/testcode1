<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustProxiesHttpsTest extends TestCase
{
    private function registerProbe(): void
    {
        Route::get('/__proxy_probe', fn () => response()->json([
            'secure' => request()->isSecure(),
            'url' => url('/foo'),
            'asset' => asset('bar.css'),
            'host' => request()->getHost(),
        ]));
    }

    public function test_forwarded_proto_https_makes_urls_secure(): void
    {
        $this->registerProbe();

        $response = $this->get('/__proxy_probe', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'erpos.kiananreho.com',
        ]);

        $response->assertOk();
        $response->assertJson(['secure' => true, 'host' => 'erpos.kiananreho.com']);

        $data = $response->json();
        $this->assertStringStartsWith('https://', (string) $data['url']);
        $this->assertStringStartsWith('https://', (string) $data['asset']);
        $this->assertStringContainsString('erpos.kiananreho.com', (string) $data['url']);
    }

    public function test_plain_http_request_stays_http(): void
    {
        $this->registerProbe();

        // No forwarded header: a direct HTTP hit must not be treated as secure.
        $response = $this->get('/__proxy_probe');

        $response->assertOk();
        $response->assertJson(['secure' => false]);
        $this->assertStringStartsWith('http://', (string) $response->json()['url']);
    }
}
