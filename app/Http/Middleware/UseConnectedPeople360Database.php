<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class UseConnectedPeople360Database
{
    public function handle(Request $request, Closure $next): Response
    {
        $remote = $request->hasSession() ? $request->session()->get('people360_lan_database') : null;

        if (! is_array($remote) || ! is_file((string) ($remote['path'] ?? ''))) {
            if (is_array($remote) && $request->hasSession()) {
                $request->session()->forget('people360_lan_database');
            }

            return $next($request);
        }

        $guard = Auth::guard('web');
        $userId = $request->session()->get($guard->getName());
        $localUser = $userId ? User::query()->with('roles')->find($userId) : null;

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $remote['path'],
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        if ($localUser instanceof User) {
            $guard->setUser($localUser);
        }

        return $next($request);
    }
}
