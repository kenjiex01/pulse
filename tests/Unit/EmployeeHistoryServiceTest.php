<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\SysLog;
use App\Services\EmployeeHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeHistoryServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function changes_for_update_log_returns_only_changed_fields(): void
    {
        $service = new EmployeeHistoryService();

        $log = new SysLog([
            'action' => 'update',
            'old_values' => [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane@example.com',
            ],
            'new_values' => [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane@example.com',
            ],
        ]);

        $changes = $service->changesForLog($log);

        $this->assertCount(1, $changes);
        $this->assertSame('last_name', $changes[0]['field']);
        $this->assertSame('Doe', $changes[0]['old']);
        $this->assertSame('Smith', $changes[0]['new']);
    }

    #[Test]
    public function logs_for_employee_includes_legacy_upload_edit_entries(): void
    {
        $employee = Employee::query()->create([
            'employee_number' => '25-HIST001',
            'first_name' => 'History',
            'last_name' => 'Test',
            'email' => 'history.test@example.com',
            'employment_status' => Employee::STATUS_ACTIVE,
            'compliance_status' => Employee::COMPLIANCE_PENDING,
            'is_active' => true,
            'is_hybrid' => false,
            'is_confidential' => false,
            'country' => 'Philippines',
        ]);

        SysLog::query()->create([
            'action' => 'edit',
            'table_name' => 'tbl_employees',
            'record_id' => $employee->employee_id,
            'old_values' => ['first_name' => 'History'],
            'new_values' => ['first_name' => 'Updated'],
            'description' => 'Updated employee via upload: 25-HIST001',
        ]);

        SysLog::query()->create([
            'action' => 'read',
            'table_name' => 'tbl_employees',
            'record_id' => $employee->employee_id,
            'description' => 'Viewed employee',
        ]);

        $service = new EmployeeHistoryService;
        $logs = $service->logsForEmployee($employee);

        $this->assertCount(1, $logs);
        $this->assertSame('edit', $logs->first()->action);
        $this->assertSame('Updated', $service->actionLabel('edit'));
    }

    #[Test]
    public function expand_history_actions_includes_edit_when_update_is_selected(): void
    {
        $service = new EmployeeHistoryService;

        $this->assertSame(
            ['create', 'update', 'edit', 'delete'],
            $service->expandHistoryActions([]),
        );

        $this->assertSame(
            ['update', 'edit'],
            $service->expandHistoryActions(['update']),
        );
    }
}
