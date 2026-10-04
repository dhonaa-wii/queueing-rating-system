<?php

namespace App\Http\Middleware;

use App\Support\AdminCollege;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps an Admin inside their own college (AdminCollege): every record bound
 * in an admin.* route must belong to it, or the page is a 404 — the same
 * answer as a record that doesn't exist, so nothing about another college
 * leaks. Super Admins and non-admin routes pass straight through.
 */
class EnforceAdminCollege
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $name = $route?->getName();

        if ($name !== null && str_starts_with($name, 'admin.') && $request->user()?->hasRole('ADMIN')) {
            foreach ($route->parameters() as $value) {
                if ($value instanceof Model && ! AdminCollege::owns($value)) {
                    abort(404);
                }
            }
        }

        return $next($request);
    }
}
