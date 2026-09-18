<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        then: function () {
            \Illuminate\Support\Facades\Route::middleware('web')->group(__DIR__.'/../routes/admin.php');
        },
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'set.organisation' => \App\Http\Middleware\SetCurrentOrganisation::class,
            'admin.auth' => \App\Http\Middleware\EnsureAdminAuthenticated::class,
            'admin.finance' => \App\Http\Middleware\EnsureAdminIsFinance::class,
        ]);

        // Trust the reverse proxy in front of `php artisan serve` (a local
        // ngrok/cloudflared tunnel terminating TLS) so Laravel reads its
        // X-Forwarded-Proto header instead of assuming the raw local
        // connection's scheme (http) — without this, every absolute URL
        // Laravel generates (asset URLs, form actions, redirects) comes out
        // as http:// even when the page itself loaded over https://,
        // which browsers then block as mixed content. Local dev only —
        // trusting '*' is not something to carry into a real deployment
        // behind an untrusted network.
        $middleware->trustProxies(at: '*');

        // Server-to-server webhooks (Cartninja_admin_dashboard's PHP install/
        // uninstall handlers) have no Laravel session/CSRF token — they're
        // authenticated by the shared-secret header checked inside
        // ShopifyWebhookController instead.
        $middleware->validateCsrfTokens(except: [
            'internal/shopify/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
