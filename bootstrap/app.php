<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'set.organisation' => \App\Http\Middleware\SetCurrentOrganisation::class,
        ]);

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
