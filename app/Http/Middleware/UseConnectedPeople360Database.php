<?php

namespace App\Http\Middleware;

use App\Database\People360LanConnection;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UseConnectedPeople360Database
{
    public function handle(Request $request, Closure $next): Response
    {
        $remote = $request->hasSession() ? $request->session()->get('people360_lan_database') : null;

        if (! is_array($remote) || ! isset($remote['address'], $remote['http_port'])) {
            if (is_array($remote) && $request->hasSession()) {
                $request->session()->forget('people360_lan_database');
            }

            People360LanConnection::useLocal();

            return $next($request);
        }

        $guard = Auth::guard('web');
        $userId = $request->session()->get($guard->getName());
        $localUser = $userId ? User::query()->with('roles')->find($userId) : null;

        People360LanConnection::useRemote((string) $remote['address'], (int) $remote['http_port']);

        if ($localUser instanceof User) {
            $guard->setUser($localUser);
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        People360LanConnection::useLocal();
    }
}
