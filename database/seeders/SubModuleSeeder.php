<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\SubModule;
use App\Support\HrLookup;
use Illuminate\Database\Seeder;

class SubModuleSeeder extends Seeder
{
    public function run(): void
    {
        $humanResource = Module::query()
            ->where('name', 'Human Resource')
            ->first();

        if (! $humanResource) {
            return;
        }

        SubModule::query()
            ->where('route_name', 'maintenance-table.index')
            ->delete();

        SubModule::query()->updateOrCreate(
            ['route_name' => 'employees.index'],
            [
                'module_id' => $humanResource->id,
                'name' => 'Employees',
                'route_pattern' => 'employees.*',
                'icon' => 'employees',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        $allowedRouteNames = collect(['employees.index'])
            ->merge(collect(HrLookup::keys())->map(fn (string $lookup) => HrLookup::routeName($lookup)))
            ->all();

        SubModule::query()
            ->where('module_id', $humanResource->id)
            ->whereNotIn('route_name', $allowedRouteNames)
            ->delete();

        foreach (HrLookup::keys() as $lookup) {
            $config = HrLookup::config($lookup);

            SubModule::query()->updateOrCreate(
                ['route_name' => HrLookup::routeName($lookup)],
                [
                    'module_id' => $humanResource->id,
                    'name' => $config['name'],
                    'route_pattern' => "hr.$lookup.*",
                    'icon' => $config['icon'],
                    'sort_order' => $config['sort_order'],
                    'is_active' => true,
                ],
            );
        }

        $payroll = Module::query()
            ->where('name', 'Payroll')
            ->first();

        if ($payroll) {
            SubModule::query()->updateOrCreate(
                ['route_name' => 'payroll.rate-definitions.index'],
                [
                    'module_id' => $payroll->id,
                    'name' => 'Rate Definition',
                    'route_pattern' => 'payroll.rate-definitions.*',
                    'icon' => 'rate-definition',
                    'sort_order' => 1,
                    'is_active' => true,
                ],
            );

            SubModule::query()->updateOrCreate(
                ['route_name' => 'payroll.maintenance-table.index'],
                [
                    'module_id' => $payroll->id,
                    'name' => 'Maintenance Table',
                    'route_pattern' => 'payroll.maintenance-table.*',
                    'icon' => 'maintenance-table',
                    'sort_order' => 2,
                    'is_active' => true,
                ],
            );

            SubModule::query()->updateOrCreate(
                ['route_name' => 'payroll.government-tables.index'],
                [
                    'module_id' => $payroll->id,
                    'name' => 'Government Tables',
                    'route_pattern' => 'payroll.government-tables.*',
                    'icon' => 'government-tables',
                    'sort_order' => 3,
                    'is_active' => true,
                ],
            );

            SubModule::query()->updateOrCreate(
                ['route_name' => 'payroll.calendar.index'],
                [
                    'module_id' => $payroll->id,
                    'name' => 'Payroll Calendar',
                    'route_pattern' => 'payroll.calendar.*',
                    'icon' => 'payroll-calendar',
                    'sort_order' => 4,
                    'is_active' => true,
                ],
            );

            SubModule::query()->updateOrCreate(
                ['route_name' => 'payroll.transaction.index'],
                [
                    'module_id' => $payroll->id,
                    'name' => 'Payroll Transaction',
                    'route_pattern' => 'payroll.transaction.*',
                    'icon' => 'payroll-transaction',
                    'sort_order' => 5,
                    'is_active' => true,
                ],
            );

            SubModule::query()->updateOrCreate(
                ['route_name' => 'payroll.reports.index'],
                [
                    'module_id' => $payroll->id,
                    'name' => 'Reports',
                    'route_pattern' => 'payroll.reports.*',
                    'icon' => 'payroll-reports',
                    'sort_order' => 6,
                    'is_active' => true,
                ],
            );
        }

        $timekeeping = Module::query()
            ->where('name', 'Timekeeping')
            ->first();

        if ($timekeeping) {
            SubModule::query()->updateOrCreate(
                ['route_name' => 'timekeeping.policy.index'],
                [
                    'module_id' => $timekeeping->id,
                    'name' => 'Timekeeping Policy',
                    'route_pattern' => 'timekeeping.policy.*',
                    'icon' => 'timekeeping-policy',
                    'sort_order' => 1,
                    'is_active' => true,
                ],
            );

            SubModule::query()->updateOrCreate(
                ['route_name' => 'timekeeping.time-logs.index'],
                [
                    'module_id' => $timekeeping->id,
                    'name' => 'Time Logs',
                    'route_pattern' => 'timekeeping.time-logs.*',
                    'icon' => 'time-logs',
                    'sort_order' => 2,
                    'is_active' => true,
                ],
            );

            SubModule::query()->updateOrCreate(
                ['route_name' => 'timekeeping.employee-profile.index'],
                [
                    'module_id' => $timekeeping->id,
                    'name' => 'Employee Profile',
                    'route_pattern' => 'timekeeping.employee-profile.*',
                    'icon' => 'employee-profile',
                    'sort_order' => 3,
                    'is_active' => true,
                ],
            );

            SubModule::query()->updateOrCreate(
                ['route_name' => 'timekeeping.employee-load.index'],
                [
                    'module_id' => $timekeeping->id,
                    'name' => 'Employee Load',
                    'route_pattern' => 'timekeeping.employee-load.*',
                    'icon' => 'employee-load',
                    'sort_order' => 4,
                    'is_active' => true,
                ],
            );
        }
    }
}
