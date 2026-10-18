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

        // Security Settings' session timeout — before StartSession (2026-10-16).
        $middleware->prependToGroup('web', \App\Http\Middleware\ApplySessionTimeout::class);

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
            // An Admin only reaches records of their own college (2026-10-03).
            \App\Http\Middleware\EnforceAdminCollege::class,
            // Audit trail for every successful Admin save (2026-09-30).
            \App\Http\Middleware\RecordAdminAudit::class,
        ]);

        // The restore step authenticates itself with a per-run token instead
        // of the session (the users table is being rewritten while it runs).
        $middleware->validateCsrfTokens(except: ['restore/*/step']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // An expired form/session (CSRF mismatch, 419) never shows the error
        // page: a page request goes back to the landing page, a background
        // fetch gets a short JSON message instead (user-directed 2026-10-19).
        // Laravel has already turned TokenMismatchException into a 419
        // HttpException by the time render callbacks run.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session has expired. Please reload the page.',
                    'redirect' => route('landing'),
                ], 419);
            }

            return redirect()->route('landing');
        });
    })->create();
