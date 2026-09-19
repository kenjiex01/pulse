<?php

namespace Tests\Unit;

use App\Models\CompanyDocumentSendLog;
use App\Models\Employee;
use App\Support\CompanyDocumentSendHistorySummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyDocumentSendHistorySummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_by_employee_groups_send_count_and_dates(): void
    {
        $employee = Employee::query()->create([
            'employee_number' => 'DOC-SUMMARY-001',
            'first_name' => 'Ivy',
            'last_name' => 'Santos',
            'email' => 'ivy@example.com',
        ]);

        $first = CompanyDocumentSendLog::query()->make([
            'company_document_form_id' => 1,
            'employee_id' => $employee->employee_id,
            'sent_at' => now()->subDays(2),
        ]);
        $first->setRelation('employee', $employee);

        $second = CompanyDocumentSendLog::query()->make([
            'company_document_form_id' => 1,
            'employee_id' => $employee->employee_id,
            'sent_at' => now()->subDay(),
        ]);
        $second->setRelation('employee', $employee);

        $summary = CompanyDocumentSendHistorySummary::byEmployee(collect([$first, $second]));

        $this->assertCount(1, $summary);
        $this->assertSame(2, $summary->first()->send_count);
        $this->assertCount(2, $summary->first()->sent_at_list);
    }
}
