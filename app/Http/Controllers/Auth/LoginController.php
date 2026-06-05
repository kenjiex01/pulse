<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\SysLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        SysLogService::record(
            action: 'read',
            table: 'users',
            description: 'Binuksan ang login page',
        );

        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        try {
            $request->authenticate();
        } catch (\Illuminate\Validation\ValidationException $exception) {
            SysLogService::record(
                action: 'read',
                table: 'users',
                description: 'Nabigong pag-login: '.$request->input('email'),
            );

            throw $exception;
        }

        $request->session()->regenerate();

        $user = Auth::user();

        SysLogService::record(
            action: 'read',
            table: 'users',
            recordId: $user->id,
            newValues: ['email' => $user->email, 'name' => $user->name],
            description: 'Matagumpay na nag-login si '.$user->name,
            userId: $user->id,
        );

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($user) {
            SysLogService::record(
                action: 'read',
                table: 'users',
                recordId: $user->id,
                description: 'Nag-logout si '.$user->name,
                userId: $user->id,
            );
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
