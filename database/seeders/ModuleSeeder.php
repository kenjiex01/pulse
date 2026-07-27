<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            [
                'name' => 'Dashboard',
                'route_name' => 'dashboard',
                'route_pattern' => 'dashboard',
                'icon' => 'dashboard',
                'section' => 'Administration',
                'admin_only' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'User Management',
                'route_name' => 'users.index',
                'route_pattern' => 'users.*',
                'icon' => 'users',
                'section' => 'Administration',
                'admin_only' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Role Management',
                'route_name' => 'roles.index',
                'route_pattern' => 'roles.*',
                'icon' => 'roles',
                'section' => 'Administration',
                'admin_only' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Database',
                'route_name' => 'database.index',
                'route_pattern' => 'database.*',
                'icon' => 'database',
                'section' => 'Administration',
                'admin_only' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Human Resource',
                'route_name' => null,
                'route_pattern' => null,
                'icon' => 'human-resource',
                'section' => 'Human Resource',
                'admin_only' => false,
                'sort_order' => 10,
            ],
            [
                'name' => 'Payroll',
                'route_name' => null,
                'route_pattern' => null,
                'icon' => 'payroll',
                'section' => 'Payroll',
                'admin_only' => false,
                'sort_order' => 20,
            ],
            [
                'name' => 'Timekeeping',
                'route_name' => null,
                'route_pattern' => null,
                'icon' => 'timekeeping',
                'section' => 'Timekeeping',
                'admin_only' => false,
                'sort_order' => 15,
            ],
        ];

        foreach ($modules as $module) {
            Module::query()->updateOrCreate(
                ['name' => $module['name']],
                $module,
            );
        }
    }
}
