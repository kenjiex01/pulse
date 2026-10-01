<?php

namespace App\Http\Middleware;

use App\Services\BiometricMissingUploadHrNotificationScheduleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBiometricMissingUploadHrNotification
{
    public function handle(Request $request, Closure $next): Response
    {
        app(BiometricMissingUploadHrNotificationScheduleService::class)->runIfNeeded();

        return $next($request);
    }
}
