<?php

namespace Database\Seeders;

use App\Models\BasicComputation;
use App\Models\ComputationBasis;
use App\Models\DeductionType;
use App\Models\GovtTable;
use App\Models\IncomeClass;
use App\Models\IncomeType;
use App\Models\LateUndertimeLeave;
use App\Models\LeaveApplyTo;
use App\Models\LeaveType;
use App\Models\LoanClass;
use App\Models\LoanType;
use Illuminate\Database\Seeder;

class PayrollMaintenanceSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedLookups();

        $incomeTypes = [
            ['income_class_id' => 1, 'income_type_code' => 'BASC', 'description' => 'Basic Income', 'is_non_taxable' => false, 'is_in_compensation_limit' => false, 'breakdown_in_ytd_report' => false, 'is_default_basic' => true, 'is_active' => true],
            ['income_class_id' => 4, 'income_type_code' => '13TH', 'description' => '13th Month Pay', 'is_non_taxable' => true, 'is_in_compensation_limit' => true, 'breakdown_in_ytd_report' => false, 'is_default_basic' => false, 'is_active' => true],
            ['income_class_id' => 3, 'income_type_code' => 'OVRT', 'description' => 'Overtime Pay', 'is_non_taxable' => false, 'is_in_compensation_limit' => false, 'breakdown_in_ytd_report' => false, 'is_default_basic' => false, 'is_active' => true],
            ['income_class_id' => 4, 'income_type_code' => 'OTHR', 'description' => 'Other Income', 'is_non_taxable' => true, 'is_in_compensation_limit' => false, 'breakdown_in_ytd_report' => false, 'is_default_basic' => false, 'is_active' => true],
        ];

        foreach ($incomeTypes as $incomeType) {
            IncomeType::query()->updateOrCreate(
                ['income_type_code' => $incomeType['income_type_code']],
                $incomeType,
            );
        }

        $deductionTypes = [
            ['deduction_type_code' => 'ECOM', 'description' => 'Employee Compensation', 'employer_amount' => 10, 'is_valid_govt_deduction' => true, 'govt_table_id' => 4, 'is_active' => true],
            ['deduction_type_code' => 'PHIL', 'description' => 'Philhealth Premium', 'employer_amount' => 100, 'is_amount_percentage' => true, 'is_valid_govt_deduction' => true, 'govt_table_id' => 2, 'is_active' => true],
            ['deduction_type_code' => 'PIBG', 'description' => 'Pag Ibig Premium', 'employer_amount' => 100, 'is_valid_govt_deduction' => true, 'govt_table_id' => 1, 'is_active' => true],
            ['deduction_type_code' => 'SSSP', 'description' => 'SSS Premium', 'employer_amount' => 0, 'is_valid_govt_deduction' => true, 'govt_table_id' => 3, 'is_active' => true],
            ['deduction_type_code' => 'WHTX', 'description' => 'Withholding Tax', 'employer_amount' => 0, 'is_valid_govt_deduction' => true, 'govt_table_id' => 5, 'is_active' => true],
            ['deduction_type_code' => 'OTHR', 'description' => 'Other Deductions', 'employer_amount' => 0, 'is_active' => true],
        ];

        foreach ($deductionTypes as $deductionType) {
            DeductionType::query()->updateOrCreate(
                ['deduction_type_code' => $deductionType['deduction_type_code']],
                $deductionType,
            );
        }

        $loanTypes = [
            ['loan_type_code' => 'SCAL', 'description' => 'SSS Calamity Loan', 'loan_class_id' => 1, 'sss_loan_type' => 'C', 'is_viewable' => true, 'is_active' => true],
            ['loan_type_code' => 'SSAL', 'description' => 'SSS Salary Loan', 'loan_class_id' => 1, 'sss_loan_type' => 'S', 'is_viewable' => true, 'is_active' => true],
            ['loan_type_code' => 'SHOS', 'description' => 'SSS Housing Loan', 'loan_class_id' => 1, 'sss_loan_type' => 'H', 'is_viewable' => true, 'is_active' => true],
            ['loan_type_code' => 'PSAL', 'description' => 'Pag Ibig Salary Loan', 'loan_class_id' => 2, 'is_viewable' => true, 'is_active' => true],
            ['loan_type_code' => 'PGHL', 'description' => 'Pag Ibig Housing Loan', 'loan_class_id' => 2, 'is_viewable' => true, 'is_active' => true],
        ];

        foreach ($loanTypes as $loanType) {
            LoanType::query()->updateOrCreate(
                ['loan_type_code' => $loanType['loan_type_code']],
                $loanType,
            );
        }

        $leaveTypes = [
            ['leave_type_code' => 'SKLV', 'description' => 'Sick Leave', 'computation_basis_id' => 3, 'is_valid_as_earned_leave' => true, 'is_valid_for_adjustment' => true, 'is_active' => true],
            ['leave_type_code' => 'VCLV', 'description' => 'Vacation Leave', 'computation_basis_id' => 3, 'is_valid_as_earned_leave' => true, 'is_valid_for_adjustment' => true, 'is_convertible_to_cash' => true, 'hours_non_taxable' => 80, 'is_active' => true],
            ['leave_type_code' => 'AWOL', 'description' => 'Absences Without Leave', 'computation_basis_id' => 3, 'leave_apply_to_id' => 3, 'is_valid_for_adjustment' => true, 'is_active' => true],
            ['leave_type_code' => 'UNDT', 'description' => 'Undertime', 'computation_basis_id' => 3, 'leave_apply_to_id' => 2, 'late_undertime_leave_id' => 2, 'is_valid_for_adjustment' => true, 'is_active' => true],
            ['leave_type_code' => 'LATE', 'description' => 'Late', 'computation_basis_id' => 3, 'leave_apply_to_id' => 1, 'late_undertime_leave_id' => 1, 'is_valid_for_adjustment' => true, 'is_active' => true],
        ];

        foreach ($leaveTypes as $leaveType) {
            LeaveType::query()->updateOrCreate(
                ['leave_type_code' => $leaveType['leave_type_code']],
                $leaveType,
            );
        }
    }

    private function seedLookups(): void
    {
        foreach ([
            1 => 'Basic Pay',
            2 => 'Allowance',
            3 => 'Overtime Pay',
            4 => 'Other Income',
        ] as $id => $label) {
            IncomeClass::query()->updateOrCreate(['income_class_id' => $id], ['income_class' => $label]);
        }

        foreach ([
            BasicComputation::TIME_IN_OUT => 'Time-In/Time-Out',
            BasicComputation::LEAVES => 'Leaves',
        ] as $id => $label) {
            BasicComputation::query()->updateOrCreate(
                ['basic_computation_id' => $id],
                ['basic_computation' => $label],
            );
        }

        foreach ([
            1 => 'SSS Loan',
            2 => 'Pag-IBIG Loan',
            3 => 'Company Loan',
            4 => 'Other Loan',
        ] as $id => $label) {
            LoanClass::query()->updateOrCreate(['loan_class_id' => $id], ['loan_class' => $label]);
        }

        foreach ([
            ['govt_table_id' => 1, 'govt_table_code' => 'HDMF', 'description' => 'Pag-IBIG', 'order_by' => 1],
            ['govt_table_id' => 2, 'govt_table_code' => 'PHIC', 'description' => 'Philhealth', 'order_by' => 2],
            ['govt_table_id' => 3, 'govt_table_code' => 'SSS', 'description' => 'SSS', 'order_by' => 3],
            ['govt_table_id' => 4, 'govt_table_code' => 'SSEC', 'description' => 'SSS - Employee Compensation', 'order_by' => 4],
            ['govt_table_id' => 5, 'govt_table_code' => 'WTAX', 'description' => 'Withholding Tax', 'order_by' => 99],
        ] as $govtTable) {
            GovtTable::query()->updateOrCreate(['govt_table_id' => $govtTable['govt_table_id']], $govtTable);
        }

        foreach ([
            3 => ['computation_basis_code' => 'CBLV', 'description' => 'Computation Basis Leaves'],
            4 => ['computation_basis_code' => 'CBSS', 'description' => 'Computation Basis SSS Premium'],
            5 => ['computation_basis_code' => 'CBPH', 'description' => 'Computation Basis for Philhealth Premium'],
            6 => ['computation_basis_code' => 'CBPG', 'description' => 'Computation Basis for Pag Ibig Premium'],
            7 => ['computation_basis_code' => 'CBBC', 'description' => 'Computation Basis Basic Income'],
        ] as $id => $basis) {
            ComputationBasis::query()->updateOrCreate(['computation_basis_id' => $id], $basis);
        }

        foreach ([
            1 => 'Tardiness',
            2 => 'Undertime',
            3 => 'Absence',
        ] as $id => $name) {
            LeaveApplyTo::query()->updateOrCreate(['leave_apply_to_id' => $id], ['name' => $name]);
        }

        foreach ([
            1 => 'Late',
            2 => 'Undertime',
            3 => 'Break Late',
        ] as $id => $type) {
            LateUndertimeLeave::query()->updateOrCreate(['late_undertime_leave_id' => $id], ['late_undertime_leave_type' => $type]);
        }
    }
}
