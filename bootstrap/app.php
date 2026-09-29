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
            'account.active' => \App\Http\Middleware\EnsureAccountIsActive::class,
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);

        // Runs the automatic sweeps this app has no scheduler for. Appended to
        // the whole web group rather than aliased onto each admin route group
        // (there are a dozen) — the middleware itself no-ops unless a signed-in
        // Admin is loading a page, so public/student traffic is unaffected.
        //
        // The maintenance gate goes first so a closed site never reaches the
        // sweeps (which write to the database a restore may be rewriting).
        $middleware->appendToGroup('web', [
            \App\Http\Middleware\EnforceMaintenanceMode::class,
            \App\Http\Middleware\RunAdminMaintenanceSweeps::class,
        ]);

        // The restore step authenticates itself with a per-run token instead
        // of the session (the users table is being rewritten while it runs).
        $middleware->validateCsrfTokens(except: ['restore/*/step']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
