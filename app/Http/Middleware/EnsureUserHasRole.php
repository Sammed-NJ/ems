<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    // used as ->middleware('role:organizer'), returns 403 for any other role
    public function handle(Request $request, Closure $next, string $role): Response
    {
        abort_unless($request->user()?->role === $role, 403, 'This action is only allowed for '.$role.'s.');

        return $next($request);
    }
}
