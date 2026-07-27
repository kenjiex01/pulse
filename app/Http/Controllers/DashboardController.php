<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\SysLogService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = auth()->user()->load('roles');

        SysLogService::record(
            action: 'read',
            table: 'users',
            recordId: $user->id,
            description: 'Opened dashboard: '.$user->name,
            userId: $user->id,
        );

        return view('dashboard', [
            'user' => $user,
            'userCount' => $user->isAdmin() ? User::query()->count() : null,
            'roleCount' => $user->isAdmin() ? Role::query()->count() : null,
            'databaseBackupPath' => $user->isAdmin() ? route('database.index') : null,
        ]);
    }
}
