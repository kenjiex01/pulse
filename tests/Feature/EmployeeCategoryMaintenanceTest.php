<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeCategoryMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_maintenance_lists_logs_and_teaching_loads(): void
    {
        $this->seed([
            RoleSeeder::class,
            ModuleSeeder::class,
            SubModuleSeeder::class,
        ]);

        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', Role::SLUG_ADMIN)->firstOrFail());

        $html = $this->actingAs($user)
            ->get(route('hr.employee-categories.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Employee Categories', $html);
        $this->assertStringContainsString('Faculty', $html);
        $this->assertStringContainsString('Teaching Loads', $html);
        $this->assertStringContainsString('Staff', $html);
        $this->assertStringContainsString('Logs', $html);
    }
}
