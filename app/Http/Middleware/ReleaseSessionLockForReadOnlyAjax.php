<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Release the session file lock early on read-only AJAX/JSON requests so
 * concurrent live-table filter refreshes are not blocked by a slow sibling request.
 */
class ReleaseSessionLockForReadOnlyAjax
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && ($request->ajax() || $request->expectsJson())) {
            $request->session()->save();
        }

        return $next($request);
    }
}
