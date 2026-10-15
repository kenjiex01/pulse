<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\EmployeeCategory;
use App\Models\EmployeeEmploymentInformation;
use App\Models\EmployeeSalary;
use App\Services\FacultyLoadPayrollService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeCategoryPayrollBasisTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function seeded_faculty_uses_teaching_loads_and_staff_uses_logs(): void
    {
        $this->assertTrue(EmployeeCategory::usesTeachingLoads(EmployeeEmploymentInformation::TYPE_FACULTY));
        $this->assertFalse(EmployeeCategory::usesTeachingLoads(EmployeeEmploymentInformation::TYPE_STAFF));
        $this->assertFalse(EmployeeCategory::usesTeachingLoads(EmployeeEmploymentInformation::TYPE_ADMIN));
    }

    #[Test]
    public function a_new_logs_category_follows_the_staff_payroll_path(): void
    {
        $category = EmployeeCategory::query()->create([
            'name' => 'Clerk',
            'payroll_basis' => EmployeeCategory::BASIS_LOGS,
            'is_active' => true,
        ]);

        $this->assertFalse(EmployeeCategory::usesTeachingLoads($category->code));

        $salary = new EmployeeSalary();
        $salary->setRelation('employmentInformation', new EmployeeEmploymentInformation([
            'user_type' => $category->code,
        ]));

        $this->assertFalse(app(FacultyLoadPayrollService::class)->shouldUseFacultyLoadPath(
            new Employee(),
            $salary,
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-15'),
        ));
    }

    #[Test]
    public function a_new_teaching_load_category_follows_the_faculty_payroll_path(): void
    {
        $category = EmployeeCategory::query()->create([
            'name' => 'Lecturer',
            'payroll_basis' => EmployeeCategory::BASIS_TEACHING_LOADS,
            'is_active' => true,
        ]);

        $this->assertTrue(EmployeeCategory::usesTeachingLoads($category->code));
        $this->assertContains($category->code, EmployeeCategory::teachingLoadCodes());

        $salary = new EmployeeSalary();
        $salary->setRelation('employmentInformation', new EmployeeEmploymentInformation([
            'user_type' => $category->code,
        ]));

        $this->assertTrue(app(FacultyLoadPayrollService::class)->shouldUseFacultyLoadPath(
            new Employee(),
            $salary,
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-15'),
        ));
    }
}
