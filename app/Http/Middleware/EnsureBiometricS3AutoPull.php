<?php

namespace App\Http\Middleware;

use App\Services\BiometricS3AutoPullService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBiometricS3AutoPull
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->user()) {
            app(BiometricS3AutoPullService::class)->autoPullIfNeeded($request->user());
        }
    }
}
