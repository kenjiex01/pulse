<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use App\Services\BiometricCollectorDashboardService;
use App\Services\SysLogService;
use App\Support\TimeLogs;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(BiometricCollectorDashboardService $biometricCollectorDashboard): View
    {
        $user = auth()->user()->load('roles');

        SysLogService::record(
            action: 'read',
            table: 'users',
            recordId: $user->id,
            description: 'Opened dashboard: '.$user->name,
            userId: $user->id,
        );

        $timeLogsModule = Module::query()
            ->where('route_name', TimeLogs::routeName('index'))
            ->first();

        $timeLogsPullUrl = $timeLogsModule !== null && $user->hasModuleAccess($timeLogsModule)
            ? route(TimeLogs::routeName('tab'), ['tab' => 'time-in-out', 's3_pull' => 1])
            : null;

        return view('dashboard', [
            'user' => $user,
            'userCount' => $user->isAdmin() ? User::query()->count() : null,
            'roleCount' => $user->isAdmin() ? Role::query()->count() : null,
            'databaseBackupPath' => $user->isAdmin() ? route('database.index') : null,
            'biometricCollectorStatus' => $biometricCollectorDashboard->statusForDate(),
            'timeLogsPullUrl' => $timeLogsPullUrl,
        ]);
    }
}
