<?php

namespace App\Providers;

use App\Services\DesktopUpdaterService;
use Native\Laravel\Contracts\ProvidesPhpIni;
use Native\Laravel\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    public function boot(): void
    {
        Window::open()
            ->title(config('app.name'))
            ->url(route('login'))
            ->width(1280)
            ->height(800)
            ->minWidth(1024)
            ->minHeight(640);

        try {
            app(DesktopUpdaterService::class)->checkForUpdates();
        } catch (\Throwable) {
            // Ignore updater failures so login still loads.
        }
    }

    public function phpIni(): array
    {
        return config('uploads.php_ini', []);
    }
}
