<?php

namespace Tests\Unit;

use App\Models\CompanyDocumentForm;
use App\Models\Employee;
use App\Support\MemoPdfFilename;
use Tests\TestCase;

class MemoPdfFilenameTest extends TestCase
{
    public function test_builds_slugged_pdf_filename(): void
    {
        $form = new CompanyDocumentForm([
            'name' => 'Internal Memo (HR-MEMO-1)',
            'code' => 'hr_internal_memo',
        ]);

        $employee = new Employee([
            'employee_number' => 'EMP-001',
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
        ]);

        $filename = MemoPdfFilename::for($form, $employee, 'absent');

        $this->assertStringEndsWith('.pdf', $filename);
        $this->assertStringContainsString('internal-memo-hr-memo-1', $filename);
        $this->assertStringContainsString('emp-001', $filename);
    }
}
