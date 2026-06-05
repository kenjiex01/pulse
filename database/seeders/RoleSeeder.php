<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Administrator',
                'slug' => Role::SLUG_ADMIN,
                'description' => 'Buong access sa sistema kasama ang user at role management.',
            ],
            [
                'name' => 'Staff',
                'slug' => Role::SLUG_STAFF,
                'description' => 'Karaniwang staff na may access sa pang-araw-araw na modules.',
            ],
            [
                'name' => 'Viewer',
                'slug' => Role::SLUG_VIEWER,
                'description' => 'View-only access sa mga approved na reports at records.',
            ],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                ['slug' => $role['slug']],
                $role,
            );
        }
    }
}
