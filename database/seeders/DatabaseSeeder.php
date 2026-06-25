<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(ModuleSeeder::class);
        $this->call(SubModuleSeeder::class);
        $this->call(RoleModuleSeeder::class);
        $this->call(ReferenceDataSeeder::class);

        $adminRole = Role::query()->where('slug', Role::SLUG_ADMIN)->first();

        $adminUser = User::query()->updateOrCreate(
            ['email' => 'superadmin@icct.edu.ph'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('Password123!'),
            ],
        );

        User::query()->where('email', 'admin@pulso.local')->delete();

        if ($adminRole) {
            $adminUser->roles()->syncWithoutDetaching([$adminRole->id]);
        }
    }
}
