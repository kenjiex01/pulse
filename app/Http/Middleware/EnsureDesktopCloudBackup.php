<?php

namespace App\Http\Middleware;

use App\Services\DesktopCloudBackupService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDesktopCloudBackup
{
    public function handle(Request $request, Closure $next): Response
    {
        app(DesktopCloudBackupService::class)->backupIfNeeded();

        return $next($request);
    }
}
