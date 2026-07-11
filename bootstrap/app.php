<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/dashboard');
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'module' => \App\Http\Middleware\EnsureModuleAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

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
