<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->command('backup:upload-cloud')
            ->dailyAt(sprintf(
                '%02d:%02d',
                (int) config('backup.cloud.schedule_hour', 10),
                (int) config('backup.cloud.schedule_minute', 0),
            ))
            ->timezone((string) config('backup.cloud.timezone', 'Asia/Manila'))
            ->when(fn () => (bool) config('backup.cloud.enabled', false));
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/dashboard');
        $middleware->web(append: [
            \App\Http\Middleware\PrepareAuthenticatedUser::class,
            \App\Http\Middleware\EnsureDesktopCloudBackup::class,
            \App\Http\Middleware\EnsureDesktopInstallerUpdate::class,
        ]);
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'module' => \App\Http\Middleware\EnsureModuleAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (HttpException $exception, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            $status = $exception->getStatusCode();

            if ($request->user() && in_array($status, [403, 404], true)) {
                $message = $exception->getMessage() ?: ($status === 404
                    ? 'That page was not found.'
                    : 'You do not have permission to access this page.');

                return redirect()
                    ->route('dashboard')
                    ->with('error', $message);
            }

            return null;
        });

        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            if ($request->is('logout')) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login');
            }

            if ($request->expectsJson()) {
                return null;
            }

            $message = 'Your session expired. Refresh the page and try again.';

            if ($request->is('timekeeping/time-logs/upload/*')) {
                $tab = \App\Support\TimeLogs::resolveTab($request->input('tab'));

                return redirect()
                    ->route('timekeeping.time-logs.tab', ['tab' => $tab, 'upload' => 1])
                    ->with('error', $message);
            }

            return redirect()
                ->back()
                ->withInput($request->except('upload_file', '_token', 'password', 'password_confirmation'))
                ->with('error', $message);
        });
    })->create();
