<?php

namespace App\Http\Middleware;

use App\Services\DesktopInstallerUpdateService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnsureDesktopInstallerUpdate
{
    public function handle(Request $request, Closure $next): Response
    {
        $service = app(DesktopInstallerUpdateService::class);

        if ($service->isEnabled()) {
            View::share('desktopInstallerUpdate', $service->pendingUpdateForUi());
        }

        return $next($request);
    }
}
