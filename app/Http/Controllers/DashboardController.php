<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use App\Services\BiometricCollectorDashboardService;
use App\Services\SysLogService;
use App\Support\TimeLogs;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user()->load('roles');

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

    public function biometricCollectorStatus(
        Request $request,
        BiometricCollectorDashboardService $biometricCollectorDashboard,
    ): View {
        $user = $request->user();

        return view('dashboard._biometric-collector-status', [
            'biometricCollectorStatus' => $biometricCollectorDashboard->statusForDate(),
            'timeLogsPullUrl' => $this->timeLogsPullUrlFor($user),
        ]);
    }

    private function timeLogsPullUrlFor(User $user): ?string
    {
        $timeLogsModule = Module::query()
            ->where('route_name', TimeLogs::routeName('index'))
            ->first();

        if ($timeLogsModule === null || ! $user->hasModuleAccess($timeLogsModule)) {
            return null;
        }

        return route(TimeLogs::routeName('tab'), ['tab' => 'time-in-out', 's3_pull' => 1]);
    }
}
