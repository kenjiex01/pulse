<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.skolaris');
        Paginator::defaultSimpleView('vendor.pagination.skolaris');

        if ($this->app->runningInConsole() && ! $this->isNativeDesktop()) {
            return;
        }

        $this->ensureDesktopDatabase();
    }

    private function isNativeDesktop(): bool
    {
        return (bool) config('nativephp-internal.running', env('NATIVEPHP_RUNNING', false));
    }

    private function ensureDesktopDatabase(): void
    {
        if (! $this->isNativeDesktop()) {
            return;
        }

        $databasePath = storage_path('app/iskolaris.sqlite');
        $isFirstLaunch = ! File::exists($databasePath);

        if ($isFirstLaunch) {
            File::ensureDirectoryExists(dirname($databasePath));
            File::put($databasePath, '');
        }

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $databasePath,
        ]);

        Artisan::call('migrate', ['--force' => true]);

        if ($isFirstLaunch || ! User::query()->exists()) {
            Artisan::call('db:seed', ['--force' => true]);
        }
    }
}
