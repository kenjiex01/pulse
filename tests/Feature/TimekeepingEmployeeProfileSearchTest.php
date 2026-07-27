<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Support\TimekeepingEmployeeProfile;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimekeepingEmployeeProfileSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_ajax_search_filters_employee_profile_results(): void
    {
        $user = User::query()->firstOrFail();

        Employee::query()->create([
            'employee_number' => 'EMP-BISCO',
            'first_name' => 'JUSTIN',
            'last_name' => 'BISCOCHO',
        ]);

        Employee::query()->create([
            'employee_number' => 'EMP-OTHER',
            'first_name' => 'MARIA',
            'last_name' => 'SANTOS',
        ]);

        $response = $this->actingAs($user)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route(TimekeepingEmployeeProfile::routeName('index'), ['search' => 'bisco']));

        $response->assertOk();
        $response->assertSee('BISCOCHO');
        $response->assertDontSee('EMP-OTHER');
        $response->assertSee('data-total="1"', false);
    }
}
