<?php

namespace App\Http\Middleware;

use App\Services\ProbationaryEndNotificationScheduleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProbationaryEndNotifications
{
    public function handle(Request $request, Closure $next): Response
    {
        app(ProbationaryEndNotificationScheduleService::class)->runIfNeeded();

        return $next($request);
    }
}
