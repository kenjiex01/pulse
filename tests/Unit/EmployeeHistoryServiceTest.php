<?php

namespace Tests\Unit;

use App\Models\SysLog;
use App\Services\EmployeeHistoryService;
use Tests\TestCase;

class EmployeeHistoryServiceTest extends TestCase
{
    public function test_changes_for_update_log_returns_only_changed_fields(): void
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
}
