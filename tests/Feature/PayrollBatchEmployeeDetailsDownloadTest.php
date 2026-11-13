<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\IncomeType;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollBatchStatus;
use App\Models\PayrollCalendar;
use App\Models\PayrollIncome;
use App\Models\PayType;
use App\Models\SysLog;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PayrollBatchEmployeeDetailsDownloadTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function selected_employees_download_as_a_spreadsheet(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::query()->firstOrFail();

        $employee = Employee::query()->create([
            'employee_number' => 'EMP-DL-1',
            'first_name' => 'Dina',
            'last_name' => 'Download',
        ]);

        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => 910,
            'dt_from' => '2026-10-11 00:00:00',
            'dt_to' => '2026-10-26 00:00:00',
            'calendar_month' => 10,
            'is_regular_period' => true,
        ]);

        $batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => 11,
            'created_by_id' => $user->id,
            'dt_created' => now(),
            'payroll_batch_status_id' => PayrollBatchStatus::PROCESSED,
        ]);

        $detail = PayrollBatchDetail::query()->create([
            'payroll_batch_id' => $batch->payroll_batch_id,
            'employee_id' => $employee->employee_id,
        ]);

        $skipped = PayrollBatchDetail::query()->create([
            'payroll_batch_id' => $batch->payroll_batch_id,
            'employee_id' => Employee::query()->create([
                'employee_number' => 'EMP-DL-2',
                'first_name' => 'Skip',
                'last_name' => 'Me',
            ])->employee_id,
        ]);

        PayrollIncome::query()->create([
            'payroll_batch_detail_id' => $detail->payroll_batch_detail_id,
            'income_type_id' => (int) IncomeType::query()->where('income_type_code', 'BASC')->value('income_type_id'),
            'taxable' => 1500,
            'non_taxable' => 0,
            'is_manual' => false,
        ]);

        $this->actingAs($user)
            ->get(route('payroll.transaction.tab', [
                'tab' => 'batches',
                'view_payroll_batch' => $batch->payroll_batch_id,
            ]))
            ->assertOk()
            ->assertSee('data-payroll-batch-download-btn', false)
            ->assertSee('EMP-DL-1');

        $response = $this->actingAs($user)->post(
            route('payroll.transaction.employees.download', $batch),
            ['detail_ids' => [$detail->payroll_batch_detail_id]],
        );

        $response->assertOk();
        $this->assertStringContainsString('Payroll_Batch_0011_details', (string) $response->headers->get('content-disposition'));

        $path = tempnam(sys_get_temp_dir(), 'batch_details_');
        file_put_contents($path, $response->streamedContent());

        $workbook = IOFactory::load($path);
        $this->assertSame(
            ['Payroll Details', 'Incomes', 'Deductions', 'Loans', 'Disputes', 'Net Pay'],
            $workbook->getSheetNames(),
        );

        $details = $workbook->getSheetByName('Payroll Details');
        $foundIncome = false;
        $foundNet = false;

        for ($row = 1; $row <= 40; $row++) {
            $this->assertNotSame('EMP-DL-2', $details->getCell('B'.$row)->getValue());

            if ($details->getCell('A'.$row)->getValue() === 'BASC') {
                $foundIncome = true;
                $this->assertSame(1500.0, (float) $details->getCell('E'.$row)->getValue());
            }

            if ($details->getCell('A'.$row)->getValue() === 'Net Pay' && $details->getCell('E'.$row)->getValue() !== null) {
                $foundNet = true;
                $this->assertSame(1500.0, (float) $details->getCell('E'.$row)->getValue());
            }
        }

        $this->assertTrue($foundIncome);
        $this->assertTrue($foundNet);

        $incomes = $workbook->getSheetByName('Incomes');
        $this->assertSame('EMP-DL-1', $incomes->getCell('A2')->getValue());
        $this->assertSame('BASC', $incomes->getCell('C2')->getValue());
        $this->assertSame(1500.0, (float) $incomes->getCell('G2')->getValue());
        $this->assertNotSame($skipped->payroll_batch_detail_id, $detail->payroll_batch_detail_id);
        $this->assertTrue(
            SysLog::query()
                ->where('table_name', 'trn_payroll_batches')
                ->where('action', 'read')
                ->where('record_id', $batch->payroll_batch_id)
                ->exists()
        );

        @unlink($path);
    }

    #[Test]
    public function download_requires_a_selected_employee(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::query()->firstOrFail();

        $calendar = PayrollCalendar::query()->create([
            'pay_type_id' => PayType::SEMI_MONTHLY,
            'pay_year' => 2026,
            'pay_period' => 911,
            'dt_from' => '2026-10-11 00:00:00',
            'dt_to' => '2026-10-26 00:00:00',
            'calendar_month' => 10,
            'is_regular_period' => true,
        ]);

        $batch = PayrollBatch::query()->create([
            'payroll_calendar_id' => $calendar->payroll_calendar_id,
            'batch_no' => 12,
            'created_by_id' => $user->id,
            'dt_created' => now(),
            'payroll_batch_status_id' => PayrollBatchStatus::PROCESSED,
        ]);

        $this->actingAs($user)
            ->from(route('payroll.transaction.tab', ['tab' => 'batches', 'view_payroll_batch' => $batch->payroll_batch_id]))
            ->post(route('payroll.transaction.employees.download', $batch), [])
            ->assertRedirect()
            ->assertSessionHasErrors('detail_ids');
    }
}
