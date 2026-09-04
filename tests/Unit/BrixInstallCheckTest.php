<?php

namespace Tests\Unit;

use App\Services\Brix\BrixInstallCheck;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BrixInstallCheckTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.shopify.internal_secret', 'test-secret');
        config()->set('services.shopify.backend_url', 'https://backend.test');
    }

    public function test_returns_true_when_backend_reports_installed(): void
    {
        Http::fake([
            'backend.test/store_install_status.php*' => Http::response([
                'success' => true,
                'data' => ['shop_domain' => 'acme.myshopify.com', 'installed' => true],
            ]),
        ]);

        $this->assertTrue(BrixInstallCheck::isInstalled('acme.myshopify.com'));

        Http::assertSent(fn ($request) => $request->hasHeader('X-Internal-Secret', 'test-secret')
            && str_contains($request->url(), 'shop_domain=acme.myshopify.com'));
    }

    public function test_returns_false_when_backend_reports_not_installed(): void
    {
        Http::fake([
            'backend.test/*' => Http::response([
                'success' => true,
                'data' => ['shop_domain' => 'acme.myshopify.com', 'installed' => false],
            ]),
        ]);

        $this->assertFalse(BrixInstallCheck::isInstalled('acme.myshopify.com'));
    }

    public function test_returns_null_on_http_error(): void
    {
        Http::fake(['backend.test/*' => Http::response('nope', 500)]);

        $this->assertNull(BrixInstallCheck::isInstalled('acme.myshopify.com'));
    }

    public function test_returns_null_on_connection_exception(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));

        $this->assertNull(BrixInstallCheck::isInstalled('acme.myshopify.com'));
    }

    public function test_returns_null_when_not_configured(): void
    {
        config()->set('services.shopify.internal_secret', '');
        Http::fake();

        $this->assertNull(BrixInstallCheck::isInstalled('acme.myshopify.com'));

        Http::assertNothingSent();
    }
}
