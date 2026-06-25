<?php

namespace App\Http\Middleware;

use App\Models\SubModule;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string $routeName): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'You do not have permission to access this page.');
        }

        $subModule = SubModule::query()
            ->where('route_name', $routeName)
            ->where('is_active', true)
            ->first();

        if (! $subModule || ! $user->hasSubModuleAccess($subModule)) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
