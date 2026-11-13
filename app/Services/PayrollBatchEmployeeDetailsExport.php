<?php

namespace App\Services;

use App\Models\EmployeeOvertimeApproval;
use App\Models\EmployeeShiftOverride;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchDetail;
use App\Models\PayrollDispute;
use App\Support\PayrollBatchNetPayLines;
use App\Support\SpreadsheetDownload;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Excel download of the employee payroll detail tabs for selected employees in one batch.
 */
class PayrollBatchEmployeeDetailsExport
{
    /**
     * @param  list<int>  $detailIds
     */
    public function stream(PayrollBatch $batch, array $detailIds): StreamedResponse
    {
        $batch->loadMissing(['payrollCalendar.payType', 'status']);

        $details = PayrollBatchDetail::query()
            ->with([
                'employee',
                'incomes.incomeType',
                'deductions.deductionType',
                'loanPayments.employeeLoan.loanType',
                'disputes.shiftCode',
            ])
            ->where('payroll_batch_id', $batch->payroll_batch_id)
            ->whereIn('payroll_batch_detail_id', $detailIds)
            ->get()
            ->sortBy(fn (PayrollBatchDetail $detail) => $detail->employee?->employee_number ?? '')
            ->values();

        $shiftOverrides = $this->shiftOverridesFor($batch, $details);
        $overtimeApprovals = $this->overtimeApprovalsFor($batch, $details);

        $spreadsheet = new Spreadsheet();
        $detailsSheet = $spreadsheet->getActiveSheet();
        $detailsSheet->setTitle('Payroll Details');
        $this->writePayrollDetails($detailsSheet, $batch, $details, $shiftOverrides, $overtimeApprovals);

        $incomes = $spreadsheet->createSheet();
        $incomes->setTitle('Incomes');
        $this->writeIncomes($incomes, $details, $shiftOverrides, $overtimeApprovals);

        $deductions = $spreadsheet->createSheet();
        $deductions->setTitle('Deductions');
        $this->writeDeductions($deductions, $details);

        $loans = $spreadsheet->createSheet();
        $loans->setTitle('Loans');
        $this->writeLoans($loans, $details);

        $disputes = $spreadsheet->createSheet();
        $disputes->setTitle('Disputes');
        $this->writeDisputes($disputes, $details);

        $netPay = $spreadsheet->createSheet();
        $netPay->setTitle('Net Pay');
        $this->writeNetPay($netPay, $details);

        $spreadsheet->setActiveSheetIndex(0);

        return SpreadsheetDownload::stream(
            $spreadsheet,
            'Payroll_Batch_'.$batch->formattedBatchNo().'_details',
        );
    }

    /**
     * @param  Collection<int, PayrollBatchDetail>  $details
     * @param  Collection<int|string, Collection<int, EmployeeShiftOverride>>  $shiftOverrides
     * @param  Collection<int|string, Collection<int, EmployeeOvertimeApproval>>  $overtimeApprovals
     */
    private function writePayrollDetails(
        Worksheet $sheet,
        PayrollBatch $batch,
        Collection $details,
        Collection $shiftOverrides,
        Collection $overtimeApprovals,
    ): void {
        $row = $this->writeBatchHeader($sheet, $batch);
        $row += 2;

        foreach ($details as $detail) {
            $employeeId = (int) $detail->employee_id;
            $sheet->setCellValue('A'.$row, 'Employee No.');
            $sheet->setCellValue('B'.$row, $detail->employee?->employee_number ?? '');
            $sheet->setCellValue('C'.$row, 'Employee Name');
            $sheet->setCellValue('D'.$row, $detail->employee?->full_name ?? '');
            $sheet->getStyle('A'.$row.':D'.$row)->getFont()->setBold(true);
            $row += 2;

            $row = $this->writeSectionTitle($sheet, $row, 'Incomes');
            $shifts = $shiftOverrides->get($employeeId, collect());
            if ($shifts->isNotEmpty()) {
                $row = $this->writeHeaderRow($sheet, $row, ['Work Date', 'Shift Code', 'Schedule']);
                foreach ($shifts as $override) {
                    $sheet->setCellValue('A'.$row, $override->work_date?->format('M j, Y'));
                    $sheet->setCellValue('B'.$row, trim(($override->shiftCode?->shift_code ?? '').($override->shiftCode?->description ? ' — '.$override->shiftCode->description : '')));
                    $sheet->setCellValue('C'.$row, $this->shiftSchedule($override));
                    $row++;
                }
                $row++;
            }

            $overtime = $overtimeApprovals->get($employeeId, collect());
            if ($overtime->isNotEmpty()) {
                $row = $this->writeHeaderRow($sheet, $row, ['Work Date', 'OT Start', 'OT End']);
                foreach ($overtime as $approval) {
                    $sheet->setCellValue('A'.$row, $approval->work_date?->format('M j, Y'));
                    $sheet->setCellValue('B'.$row, $approval->ot_start?->format('g:i A'));
                    $sheet->setCellValue('C'.$row, $approval->ot_end?->format('g:i A'));
                    $row++;
                }
                $row++;
            }

            $row = $this->writeHeaderRow($sheet, $row, ['Income Type Code', 'Description', 'Hours', 'Days', 'Taxable', 'Non-Taxable']);
            $incomes = $detail->incomes->sortBy(fn ($income) => $income->incomeType?->income_type_code ?? '');
            $start = $row;
            foreach ($incomes as $income) {
                $sheet->setCellValue('A'.$row, $income->incomeType?->income_type_code ?? '');
                $sheet->setCellValue('B'.$row, $this->incomeDescription($income));
                $this->setNumber($sheet, 'C'.$row, $income->hours);
                $this->setNumber($sheet, 'D'.$row, $income->days);
                $sheet->setCellValue('E'.$row, (float) $income->taxable);
                $sheet->setCellValue('F'.$row, (float) $income->non_taxable);
                $row++;
            }
            if ($incomes->isNotEmpty()) {
                $sheet->getStyle('C'.$start.':F'.($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->setCellValue('A'.$row, 'Total');
                $sheet->setCellValue('E'.$row, $incomes->sum(fn ($income) => (float) $income->taxable));
                $sheet->setCellValue('F'.$row, $incomes->sum(fn ($income) => (float) $income->non_taxable));
                $sheet->getStyle('A'.$row.':F'.$row)->getFont()->setBold(true);
                $sheet->getStyle('E'.$row.':F'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
                $row++;
            }
            $row += 2;

            $row = $this->writeSectionTitle($sheet, $row, 'Deductions');
            $row = $this->writeDeductionTable($sheet, $row, $detail);
            $row += 2;

            $row = $this->writeSectionTitle($sheet, $row, 'Loans');
            $row = $this->writeLoanTable($sheet, $row, $detail);
            $row += 2;

            $row = $this->writeSectionTitle($sheet, $row, 'Disputes');
            $row = $this->writeDisputeTable($sheet, $row, $detail);
            $row += 2;

            $row = $this->writeSectionTitle($sheet, $row, 'Net Pay');
            $row = $this->writeNetPayBlock($sheet, $row, $detail);
            $row += 3;
        }

        $this->autosize($sheet, 8);
        $sheet->freezePane('A6');
    }

    /**
     * @param  Collection<int, PayrollBatchDetail>  $details
     * @param  Collection<int|string, Collection<int, EmployeeShiftOverride>>  $shiftOverrides
     * @param  Collection<int|string, Collection<int, EmployeeOvertimeApproval>>  $overtimeApprovals
     */
    private function writeIncomes(Worksheet $sheet, Collection $details, Collection $shiftOverrides, Collection $overtimeApprovals): void
    {
        $row = $this->writeHeaderRow($sheet, 1, [
            'Employee No.', 'Employee Name', 'Income Type Code', 'Description', 'Hours', 'Days', 'Taxable', 'Non-Taxable', 'Remarks',
        ]);

        foreach ($details as $detail) {
            foreach ($detail->incomes->sortBy(fn ($income) => $income->incomeType?->income_type_code ?? '') as $income) {
                $sheet->setCellValue('A'.$row, $detail->employee?->employee_number ?? '');
                $sheet->setCellValue('B'.$row, $detail->employee?->full_name ?? '');
                $sheet->setCellValue('C'.$row, $income->incomeType?->income_type_code ?? '');
                $sheet->setCellValue('D'.$row, $income->incomeType?->description ?? '');
                $this->setNumber($sheet, 'E'.$row, $income->hours);
                $this->setNumber($sheet, 'F'.$row, $income->days);
                $sheet->setCellValue('G'.$row, (float) $income->taxable);
                $sheet->setCellValue('H'.$row, (float) $income->non_taxable);
                $sheet->setCellValue('I'.$row, $income->remarks ?? '');
                $row++;
            }
        }

        if ($row > 2) {
            $sheet->getStyle('E2:H'.($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        }

        $row += 2;
        $row = $this->writeSectionTitle($sheet, $row, 'Shift Codes');
        $row = $this->writeHeaderRow($sheet, $row, ['Employee No.', 'Employee Name', 'Work Date', 'Shift Code', 'Schedule']);
        foreach ($details as $detail) {
            foreach ($shiftOverrides->get((int) $detail->employee_id, collect()) as $override) {
                $sheet->setCellValue('A'.$row, $detail->employee?->employee_number ?? '');
                $sheet->setCellValue('B'.$row, $detail->employee?->full_name ?? '');
                $sheet->setCellValue('C'.$row, $override->work_date?->format('M j, Y'));
                $sheet->setCellValue('D'.$row, $override->shiftCode?->shift_code ?? '');
                $sheet->setCellValue('E'.$row, $this->shiftSchedule($override));
                $row++;
            }
        }

        $row += 2;
        $row = $this->writeSectionTitle($sheet, $row, 'Overtime');
        $row = $this->writeHeaderRow($sheet, $row, ['Employee No.', 'Employee Name', 'Work Date', 'OT Start', 'OT End']);
        foreach ($details as $detail) {
            foreach ($overtimeApprovals->get((int) $detail->employee_id, collect()) as $approval) {
                $sheet->setCellValue('A'.$row, $detail->employee?->employee_number ?? '');
                $sheet->setCellValue('B'.$row, $detail->employee?->full_name ?? '');
                $sheet->setCellValue('C'.$row, $approval->work_date?->format('M j, Y'));
                $sheet->setCellValue('D'.$row, $approval->ot_start?->format('g:i A'));
                $sheet->setCellValue('E'.$row, $approval->ot_end?->format('g:i A'));
                $row++;
            }
        }

        $this->autosize($sheet, 9);
        $sheet->freezePane('A2');
    }

    /**
     * @param  Collection<int, PayrollBatchDetail>  $details
     */
    private function writeDeductions(Worksheet $sheet, Collection $details): void
    {
        $row = $this->writeHeaderRow($sheet, 1, [
            'Employee No.', 'Employee Name', 'Deduction Type Code', 'Description', 'Minutes', 'Hours', 'Days', 'Employee Amount', 'Employer Share',
        ]);

        foreach ($details as $detail) {
            foreach (PayrollBatchNetPayLines::deductionRows($detail->deductions) as $deduction) {
                $sheet->setCellValue('A'.$row, $detail->employee?->employee_number ?? '');
                $sheet->setCellValue('B'.$row, $detail->employee?->full_name ?? '');
                $sheet->setCellValue('C'.$row, $deduction['code'] ?? '');
                $sheet->setCellValue('D'.$row, $deduction['description'] ?? '');
                $sheet->setCellValue('E'.$row, $deduction['minutes']);
                $this->setNumber($sheet, 'F'.$row, ($deduction['show_hours'] ?? false) ? $deduction['hours'] : null);
                $this->setNumber($sheet, 'G'.$row, ($deduction['show_days'] ?? false) ? $deduction['days'] : null);
                $sheet->setCellValue('H'.$row, (float) $deduction['employee_amount']);
                $sheet->setCellValue('I'.$row, (float) $deduction['employer_amount']);
                $row++;
            }

            foreach ($detail->loanPayments as $payment) {
                $total = round((float) $payment->payment + (float) ($payment->penalty ?? 0), 2);
                if ($total <= 0) {
                    continue;
                }

                $sheet->setCellValue('A'.$row, $detail->employee?->employee_number ?? '');
                $sheet->setCellValue('B'.$row, $detail->employee?->full_name ?? '');
                $sheet->setCellValue('C'.$row, $payment->employeeLoan?->loanType?->loan_type_code ?? 'LOAN');
                $sheet->setCellValue('D'.$row, $payment->employeeLoan?->loanType?->description ?? 'Loan');
                $sheet->setCellValue('H'.$row, $total);
                $sheet->setCellValue('I'.$row, 0);
                $row++;
            }
        }

        if ($row > 2) {
            $sheet->getStyle('F2:I'.($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        }

        $this->autosize($sheet, 9);
        $sheet->freezePane('A2');
    }

    /**
     * @param  Collection<int, PayrollBatchDetail>  $details
     */
    private function writeLoans(Worksheet $sheet, Collection $details): void
    {
        $row = $this->writeHeaderRow($sheet, 1, [
            'Employee No.', 'Employee Name', 'Loan Type', 'Description', 'Loan Date', 'Amortization', 'Balance', 'Payment', 'Penalty', 'Total', 'Source',
        ]);

        foreach ($details as $detail) {
            foreach ($detail->loanPayments->sortBy('payroll_loan_payment_id') as $payment) {
                $loan = $payment->employeeLoan;
                $sheet->setCellValue('A'.$row, $detail->employee?->employee_number ?? '');
                $sheet->setCellValue('B'.$row, $detail->employee?->full_name ?? '');
                $sheet->setCellValue('C'.$row, $loan?->loanType?->loan_type_code ?? 'LOAN');
                $sheet->setCellValue('D'.$row, $loan?->loanType?->description ?? '');
                $sheet->setCellValue('E'.$row, $loan?->dt_loan?->format('M d, Y'));
                $this->setNumber($sheet, 'F'.$row, $loan?->amortization_amount);
                $sheet->setCellValue('G'.$row, $loan ? $loan->loanBalance() : null);
                $sheet->setCellValue('H'.$row, (float) $payment->payment);
                $sheet->setCellValue('I'.$row, (float) ($payment->penalty ?? 0));
                $sheet->setCellValue('J'.$row, $payment->amount());
                $sheet->setCellValue('K'.$row, $payment->is_manual ? 'Manual' : 'Auto');
                $row++;
            }
        }

        if ($row > 2) {
            $sheet->getStyle('F2:J'.($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        }

        $this->autosize($sheet, 11);
        $sheet->freezePane('A2');
    }

    /**
     * @param  Collection<int, PayrollBatchDetail>  $details
     */
    private function writeDisputes(Worksheet $sheet, Collection $details): void
    {
        $row = $this->writeHeaderRow($sheet, 1, [
            'Employee No.', 'Employee Name', 'Work Date', 'Type', 'Filed Times', 'Shift Code', 'Expected Pay', 'Paid in this Batch', 'Remarks',
        ]);

        foreach ($details as $detail) {
            foreach ($detail->disputes->sortBy(fn (PayrollDispute $dispute) => $dispute->work_date?->toDateString().$dispute->category) as $dispute) {
                $computed = $dispute->signedComputedTotal();
                $sheet->setCellValue('A'.$row, $detail->employee?->employee_number ?? '');
                $sheet->setCellValue('B'.$row, $detail->employee?->full_name ?? '');
                $sheet->setCellValue('C'.$row, $dispute->work_date?->format('M j, Y'));
                $sheet->setCellValue('D'.$row, $dispute->categoryName());
                $sheet->setCellValue('E'.$row, $this->disputeTimes($dispute));
                $sheet->setCellValue('F'.$row, $dispute->shiftCode?->shift_code ?? '');
                $sheet->setCellValue('G'.$row, $dispute->signedExpectedTotal());
                $sheet->setCellValue('H'.$row, $computed);
                $sheet->setCellValue('I'.$row, $dispute->remarks ?? '');
                $row++;
            }
        }

        if ($row > 2) {
            $sheet->getStyle('G2:H'.($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        }

        $this->autosize($sheet, 9);
        $sheet->freezePane('A2');
    }

    /**
     * @param  Collection<int, PayrollBatchDetail>  $details
     */
    private function writeNetPay(Worksheet $sheet, Collection $details): void
    {
        $row = $this->writeHeaderRow($sheet, 1, [
            'Employee No.', 'Employee Name', 'Line', 'Hours', 'Days', 'Minutes', 'Amount',
        ]);

        foreach ($details as $detail) {
            $totals = $this->totals($detail);
            $incomeLines = PayrollBatchNetPayLines::incomeLines($detail->incomes);
            foreach ($incomeLines as $line) {
                $sheet->setCellValue('A'.$row, $detail->employee?->employee_number ?? '');
                $sheet->setCellValue('B'.$row, $detail->employee?->full_name ?? '');
                $sheet->setCellValue('C'.$row, $line['label']);
                $this->setNumber($sheet, 'D'.$row, $line['hours']);
                $this->setNumber($sheet, 'E'.$row, $line['days']);
                $sheet->setCellValue('G'.$row, (float) $line['amount']);
                $row++;
            }

            $sheet->setCellValue('A'.$row, $detail->employee?->employee_number ?? '');
            $sheet->setCellValue('B'.$row, $detail->employee?->full_name ?? '');
            $sheet->setCellValue('C'.$row, 'Gross Income');
            $sheet->setCellValue('G'.$row, $totals['gross']);
            $sheet->getStyle('A'.$row.':G'.$row)->getFont()->setBold(true);
            $row++;

            foreach ($this->netPayDeductionLines($detail) as $line) {
                $sheet->setCellValue('A'.$row, $detail->employee?->employee_number ?? '');
                $sheet->setCellValue('B'.$row, $detail->employee?->full_name ?? '');
                $sheet->setCellValue('C'.$row, $line['label']);
                $this->setNumber($sheet, 'D'.$row, $line['hours']);
                $this->setNumber($sheet, 'E'.$row, $line['days']);
                $sheet->setCellValue('F'.$row, $line['minutes']);
                $sheet->setCellValue('G'.$row, -1 * (float) $line['amount']);
                $row++;
            }

            $sheet->setCellValue('A'.$row, $detail->employee?->employee_number ?? '');
            $sheet->setCellValue('B'.$row, $detail->employee?->full_name ?? '');
            $sheet->setCellValue('C'.$row, 'Total Deductions');
            $sheet->setCellValue('G'.$row, -1 * $totals['deductions']);
            $sheet->getStyle('A'.$row.':G'.$row)->getFont()->setBold(true);
            $row++;

            $sheet->setCellValue('A'.$row, $detail->employee?->employee_number ?? '');
            $sheet->setCellValue('B'.$row, $detail->employee?->full_name ?? '');
            $sheet->setCellValue('C'.$row, 'Net Pay');
            $sheet->setCellValue('G'.$row, $totals['net']);
            $sheet->getStyle('A'.$row.':G'.$row)->getFont()->setBold(true);
            $row += 2;
        }

        $sheet->getStyle('D2:G'.max(2, $row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        $this->autosize($sheet, 7);
        $sheet->freezePane('A2');
    }

    private function writeDeductionTable(Worksheet $sheet, int $row, PayrollBatchDetail $detail): int
    {
        $row = $this->writeHeaderRow($sheet, $row, [
            'Deduction Type Code', 'Description', 'Minutes', 'Hours', 'Days', 'Employee Amount', 'Employer Share',
        ]);
        $start = $row;

        foreach (PayrollBatchNetPayLines::deductionRows($detail->deductions) as $deduction) {
            $sheet->setCellValue('A'.$row, $deduction['code'] ?? '');
            $sheet->setCellValue('B'.$row, $deduction['description'] ?? '');
            $sheet->setCellValue('C'.$row, $deduction['minutes']);
            $this->setNumber($sheet, 'D'.$row, ($deduction['show_hours'] ?? false) ? $deduction['hours'] : null);
            $this->setNumber($sheet, 'E'.$row, ($deduction['show_days'] ?? false) ? $deduction['days'] : null);
            $sheet->setCellValue('F'.$row, (float) $deduction['employee_amount']);
            $sheet->setCellValue('G'.$row, (float) $deduction['employer_amount']);
            $row++;
        }

        foreach ($detail->loanPayments as $payment) {
            $total = round((float) $payment->payment + (float) ($payment->penalty ?? 0), 2);
            if ($total <= 0) {
                continue;
            }

            $sheet->setCellValue('A'.$row, $payment->employeeLoan?->loanType?->loan_type_code ?? 'LOAN');
            $sheet->setCellValue('B'.$row, $payment->employeeLoan?->loanType?->description ?? 'Loan');
            $sheet->setCellValue('F'.$row, $total);
            $sheet->setCellValue('G'.$row, 0);
            $row++;
        }

        if ($row > $start) {
            $sheet->getStyle('D'.$start.':G'.($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
            $totals = $this->totals($detail);
            $sheet->setCellValue('A'.$row, 'Total');
            $sheet->setCellValue('F'.$row, $totals['deductions']);
            $sheet->setCellValue('G'.$row, $totals['employer']);
            $sheet->getStyle('A'.$row.':G'.$row)->getFont()->setBold(true);
            $sheet->getStyle('F'.$row.':G'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
            $row++;
        }

        return $row;
    }

    private function writeLoanTable(Worksheet $sheet, int $row, PayrollBatchDetail $detail): int
    {
        $row = $this->writeHeaderRow($sheet, $row, [
            'Loan Type', 'Loan Date', 'Amortization', 'Balance', 'Payment', 'Penalty', 'Total', 'Source',
        ]);
        $start = $row;

        foreach ($detail->loanPayments->sortBy('payroll_loan_payment_id') as $payment) {
            $loan = $payment->employeeLoan;
            $sheet->setCellValue('A'.$row, trim(($loan?->loanType?->loan_type_code ?? 'LOAN').($loan?->loanType?->description ? ' — '.$loan->loanType->description : '')));
            $sheet->setCellValue('B'.$row, $loan?->dt_loan?->format('M d, Y'));
            $this->setNumber($sheet, 'C'.$row, $loan?->amortization_amount);
            $sheet->setCellValue('D'.$row, $loan ? $loan->loanBalance() : null);
            $sheet->setCellValue('E'.$row, (float) $payment->payment);
            $sheet->setCellValue('F'.$row, (float) ($payment->penalty ?? 0));
            $sheet->setCellValue('G'.$row, $payment->amount());
            $sheet->setCellValue('H'.$row, $payment->is_manual ? 'Manual' : 'Auto');
            $row++;
        }

        if ($row > $start) {
            $sheet->getStyle('C'.$start.':G'.($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        }

        return $row;
    }

    private function writeDisputeTable(Worksheet $sheet, int $row, PayrollBatchDetail $detail): int
    {
        $row = $this->writeHeaderRow($sheet, $row, [
            'Work Date', 'Type', 'Filed Times', 'Expected Pay', 'Paid in this Batch', 'Remarks',
        ]);

        foreach ($detail->disputes as $dispute) {
            $computed = $dispute->signedComputedTotal();
            $sheet->setCellValue('A'.$row, $dispute->work_date?->format('M j, Y'));
            $sheet->setCellValue('B'.$row, $dispute->categoryName());
            $sheet->setCellValue('C'.$row, $this->disputeTimes($dispute));
            $sheet->setCellValue('D'.$row, $dispute->signedExpectedTotal());
            $sheet->setCellValue('E'.$row, $computed);
            $sheet->setCellValue('F'.$row, $dispute->remarks ?? '');
            $row++;
        }

        if ($detail->disputes->isNotEmpty()) {
            $sheet->getStyle('D'.($row - $detail->disputes->count()).':E'.($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        }

        return $row;
    }

    private function writeNetPayBlock(Worksheet $sheet, int $row, PayrollBatchDetail $detail): int
    {
        $totals = $this->totals($detail);
        $start = $row + 1;
        $row = $this->writeHeaderRow($sheet, $row, ['Line', 'Hours', 'Days', 'Minutes', 'Amount']);

        foreach (PayrollBatchNetPayLines::incomeLines($detail->incomes) as $line) {
            $sheet->setCellValue('A'.$row, $line['label']);
            $this->setNumber($sheet, 'B'.$row, $line['hours']);
            $this->setNumber($sheet, 'C'.$row, $line['days']);
            $sheet->setCellValue('E'.$row, (float) $line['amount']);
            $row++;
        }

        $sheet->setCellValue('A'.$row, 'Gross Income');
        $sheet->setCellValue('E'.$row, $totals['gross']);
        $sheet->getStyle('A'.$row.':E'.$row)->getFont()->setBold(true);
        $row++;

        foreach ($this->netPayDeductionLines($detail) as $line) {
            $sheet->setCellValue('A'.$row, $line['label']);
            $this->setNumber($sheet, 'B'.$row, $line['hours']);
            $this->setNumber($sheet, 'C'.$row, $line['days']);
            $sheet->setCellValue('D'.$row, $line['minutes']);
            $sheet->setCellValue('E'.$row, -1 * (float) $line['amount']);
            $row++;
        }

        $sheet->setCellValue('A'.$row, 'Total Deductions');
        $sheet->setCellValue('E'.$row, -1 * $totals['deductions']);
        $sheet->getStyle('A'.$row.':E'.$row)->getFont()->setBold(true);
        $row++;

        $sheet->setCellValue('A'.$row, 'Net Pay');
        $sheet->setCellValue('E'.$row, $totals['net']);
        $sheet->getStyle('A'.$row.':E'.$row)->getFont()->setBold(true);
        $sheet->getStyle('B'.$start.':E'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
        $row++;

        return $row;
    }

    /**
     * @return list<array{label: string, hours: ?float, days: ?float, minutes: ?int, amount: float}>
     */
    private function netPayDeductionLines(PayrollBatchDetail $detail): array
    {
        $lines = PayrollBatchNetPayLines::deductionRows($detail->deductions)
            ->map(fn (array $deduction) => [
                'label' => $deduction['description'] ?? $deduction['code'] ?? 'Deduction',
                'minutes' => $deduction['minutes'] ?? null,
                'hours' => ($deduction['show_hours'] ?? false) ? (float) ($deduction['hours'] ?? 0) : null,
                'days' => ($deduction['show_days'] ?? false) ? (float) ($deduction['days'] ?? 0) : null,
                'amount' => (float) $deduction['employee_amount'],
            ])
            ->filter(fn (array $line) => $line['amount'] !== 0.0)
            ->values();

        return $lines
            ->concat(PayrollBatchNetPayLines::loanPaymentLines($detail->loanPayments))
            ->values()
            ->all();
    }

    /**
     * @return array{gross: float, deductions: float, employer: float, net: float}
     */
    private function totals(PayrollBatchDetail $detail): array
    {
        $gross = round($detail->incomes->sum(fn ($income) => (float) $income->taxable + (float) $income->non_taxable), 2);
        $deductionRows = PayrollBatchNetPayLines::deductionRows($detail->deductions);
        $loanLines = PayrollBatchNetPayLines::loanPaymentLines($detail->loanPayments);
        $deductions = round(
            $deductionRows->sum(fn (array $row) => (float) $row['employee_amount']) + $loanLines->sum('amount'),
            2,
        );

        return [
            'gross' => $gross,
            'deductions' => $deductions,
            'employer' => round($deductionRows->sum(fn (array $row) => (float) $row['employer_amount']), 2),
            'net' => round($gross - $deductions, 2),
        ];
    }

    /**
     * @param  Collection<int, PayrollBatchDetail>  $details
     * @return Collection<int|string, Collection<int, EmployeeShiftOverride>>
     */
    private function shiftOverridesFor(PayrollBatch $batch, Collection $details): Collection
    {
        $calendar = $batch->payrollCalendar;
        if ($calendar?->dt_from === null || $calendar->dt_to === null || $details->isEmpty()) {
            return collect();
        }

        return EmployeeShiftOverride::query()
            ->with('shiftCode')
            ->whereIn('employee_id', $details->pluck('employee_id'))
            ->whereDate('work_date', '>=', $calendar->dt_from->toDateString())
            ->whereDate('work_date', '<=', $calendar->dt_to->toDateString())
            ->orderBy('work_date')
            ->get()
            ->groupBy('employee_id');
    }

    /**
     * @param  Collection<int, PayrollBatchDetail>  $details
     * @return Collection<int|string, Collection<int, EmployeeOvertimeApproval>>
     */
    private function overtimeApprovalsFor(PayrollBatch $batch, Collection $details): Collection
    {
        $calendar = $batch->payrollCalendar;
        if ($calendar?->dt_from === null || $calendar->dt_to === null || $details->isEmpty()) {
            return collect();
        }

        return EmployeeOvertimeApproval::query()
            ->whereIn('employee_id', $details->pluck('employee_id'))
            ->whereDate('work_date', '>=', $calendar->dt_from->toDateString())
            ->whereDate('work_date', '<=', $calendar->dt_to->toDateString())
            ->orderBy('work_date')
            ->orderBy('ot_start')
            ->get()
            ->groupBy('employee_id');
    }

    private function writeBatchHeader(Worksheet $sheet, PayrollBatch $batch): int
    {
        $calendar = $batch->payrollCalendar;
        $period = $calendar
            ? $calendar->formattedPayPeriod().' · '.$calendar->dt_from->format('M j').' – '.$calendar->dt_to->format('M j, Y')
            : '—';

        $sheet->setCellValue('A1', 'Payroll Batch');
        $sheet->setCellValue('B1', $batch->formattedBatchNo());
        $sheet->setCellValue('A2', 'Pay Type');
        $sheet->setCellValue('B2', $calendar?->payType?->pay_type ?? '—');
        $sheet->setCellValue('A3', 'Pay Period');
        $sheet->setCellValue('B3', $period);
        $sheet->setCellValue('A4', 'Status');
        $sheet->setCellValue('B4', $batch->status?->payroll_batch_status ?? '—');

        return 4;
    }

    private function writeSectionTitle(Worksheet $sheet, int $row, string $title): int
    {
        $sheet->setCellValue('A'.$row, $title);
        $sheet->getStyle('A'.$row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '0B318F'], 'size' => 13],
        ]);

        return $row + 1;
    }

    /**
     * @param  list<string>  $headers
     */
    private function writeHeaderRow(Worksheet $sheet, int $row, array $headers): int
    {
        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, $row], $header);
        }

        $lastColumn = chr(ord('A') + count($headers) - 1);
        $sheet->getStyle('A'.$row.':'.$lastColumn.$row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0B318F'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        return $row + 1;
    }

    private function incomeDescription(mixed $income): string
    {
        $description = (string) ($income->incomeType?->description ?? '');

        if (filled($income->remarks ?? null)) {
            $description .= ($description !== '' ? ' ' : '').'('.$income->remarks.')';
        }

        return $description;
    }

    private function shiftSchedule(EmployeeShiftOverride $override): string
    {
        $shift = $override->shiftCode;
        if ($shift?->time_in === null || $shift->time_out === null) {
            return '';
        }

        $label = substr((string) $shift->time_in, 0, 5).' – '.substr((string) $shift->time_out, 0, 5);

        return $shift->is_flexi_time ? $label.' (Flexi)' : $label;
    }

    private function disputeTimes(PayrollDispute $dispute): string
    {
        $short = fn (?string $value) => $value ? substr($value, 0, 5) : null;

        return match ($dispute->category) {
            PayrollDispute::UNPAID_OVERTIME => 'OT '.($short($dispute->ot_start) ?? '—').' – '.($short($dispute->ot_end) ?? '—'),
            PayrollDispute::OVER_PAYMENT_OVERTIME => $dispute->ot_start
                ? 'Actual OT '.$short($dispute->ot_start).' – '.($short($dispute->ot_end) ?? '—')
                : 'No OT rendered',
            PayrollDispute::OVER_PAYMENT_DAYS => $dispute->time_in
                ? ($short($dispute->time_in) ?? '—').' – '.($short($dispute->time_out) ?? '—')
                : 'Absent',
            PayrollDispute::OVER_DEDUCTION_LATE => 'In '.($short($dispute->time_in) ?? '—'),
            default => ($short($dispute->time_in) ?? '—').' – '.($short($dispute->time_out) ?? '—'),
        };
    }

    private function setNumber(Worksheet $sheet, string $cell, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $sheet->setCellValue($cell, (float) $value);
    }

    private function autosize(Worksheet $sheet, int $columns): void
    {
        for ($index = 1; $index <= $columns; $index++) {
            $sheet->getColumnDimension(chr(ord('A') + $index - 1))->setAutoSize(true);
        }
    }
}
