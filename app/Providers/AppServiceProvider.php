<?php

namespace App\Providers;

use App\Session\DeviceAwareDatabaseSessionHandler;
use App\Support\SessionLifetimes;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RedirectIfAuthenticated::redirectUsing(fn () => route(Auth::user()->dashboardRouteName()));

        Paginator::useBootstrapFive();

        // Database sessions: the account timeout for signed-in users, a
        // whole-day lifetime for room session tablets. Custom creators win
        // over the built-in 'database' driver.
        Session::extend('database', fn ($app) => new DeviceAwareDatabaseSessionHandler(
            $app['db']->connection($app['config']->get('session.connection')),
            $app['config']->get('session.table'),
            SessionLifetimes::account(),
            SessionLifetimes::device(),
            $app,
        ));
    }
}
