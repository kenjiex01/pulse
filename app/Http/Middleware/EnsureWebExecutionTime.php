<?php

namespace App\Http\Middleware;

use App\Support\PhpExecutionTime;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Homebrew PHP 8.5 often ships with max_execution_time=0, which some runtimes treat as ~0s and kills long HTTP requests (and can stop artisan serve).
 */
class EnsureWebExecutionTime
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.debug') || app()->environment('local')) {
            $seconds = max(120, (int) config('employee_load.pull_step_time_limit_seconds', 900));
            PhpExecutionTime::ensureAtLeast($seconds);
        }

        return $next($request);
    }
}
