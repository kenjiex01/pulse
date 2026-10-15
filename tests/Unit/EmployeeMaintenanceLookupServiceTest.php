<?php

namespace Tests\Unit;

use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\Position;
use App\Services\EmployeeFormOptions;
use App\Services\EmployeeMaintenanceLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeMaintenanceLookupServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function upload_names_are_inserted_into_position_and_designation_maintenance(): void
    {
        $service = app(EmployeeMaintenanceLookupService::class);

        $employments = $service->ensureFromEmployment([
            [
                'position' => 'HR Assistant',
                'designation' => 'HR Assistant',
            ],
            [
                'position' => 'Registrar',
                'designation' => 'Records Officer',
            ],
        ]);

        $this->assertSame('HR Assistant', $employments[0]['position']);
        $this->assertSame('HR Assistant', $employments[0]['designation']);
        $this->assertTrue(Position::query()->where('position_name', 'HR Assistant')->where('is_active', true)->exists());
        $this->assertTrue(Designation::query()->where('designation_name', 'HR Assistant')->where('is_active', true)->exists());
        $this->assertSame(2, Position::query()->count());
        $this->assertSame(2, Designation::query()->count());
    }

    #[Test]
    public function an_existing_name_is_reused_without_a_duplicate(): void
    {
        Position::query()->create([
            'position_name' => 'HR Assistant',
            'is_active' => true,
        ]);

        $name = app(EmployeeMaintenanceLookupService::class)->ensurePosition('hr assistant');

        $this->assertSame('HR Assistant', $name);
        $this->assertSame(1, Position::withTrashed()->count());
    }

    #[Test]
    public function a_removed_or_inactive_name_is_put_back_on_the_active_list(): void
    {
        $position = Position::query()->create([
            'position_name' => 'HR Assistant',
            'is_active' => false,
        ]);
        $position->delete();

        $designation = Designation::query()->create([
            'designation_name' => 'HR Assistant',
            'is_active' => false,
        ]);

        $service = app(EmployeeMaintenanceLookupService::class);
        $service->ensurePosition('HR Assistant');
        $service->ensureDesignation('HR Assistant');

        $position->refresh();
        $designation->refresh();

        $this->assertNull($position->deleted_at);
        $this->assertTrue(Position::query()->active()->whereKey($position->getKey())->exists());
        $this->assertTrue(Designation::query()->active()->whereKey($designation->getKey())->exists());
        $this->assertSame(1, Position::query()->count());
        $this->assertSame(1, Designation::query()->count());
    }

    #[Test]
    public function legacy_employee_names_are_added_to_maintenance_when_the_form_loads(): void
    {
        $employee = Employee::query()->create([
            'employee_number' => 'LEG-100',
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => 'ana.reyes@example.com',
            'employment_status' => Employee::STATUS_ACTIVE,
            'is_active' => true,
        ]);

        EmployeeEmploymentInformation::query()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => 'staff',
            'position' => 'HR Assistant',
            'designation' => 'HR Assistant',
            'sort_order' => 0,
        ]);

        $options = app(EmployeeFormOptions::class)->resolve();

        $this->assertTrue($options['positions']->contains('position_name', 'HR Assistant'));
        $this->assertTrue($options['designations']->contains('designation_name', 'HR Assistant'));
        $this->assertSame([], app(EmployeeFormOptions::class)->legacyOption($options['positions'], 'position_name', 'HR Assistant'));
    }
}
