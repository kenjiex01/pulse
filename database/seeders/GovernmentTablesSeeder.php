<?php

namespace Database\Seeders;

use App\Models\GovtTablePagibig;
use App\Models\GovtTablePhilhealth;
use App\Models\GovtTableSss;
use App\Models\GovtTableWtax2023;
use App\Support\GovernmentTables;
use App\Models\GovtTableWtaxAnnual2023;
use Illuminate\Database\Seeder;

class GovernmentTablesSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPagibig();
        $this->seedPhilhealth();
        $this->call(PhilhealthMinimumSeeder::class);
        $this->seedSss();
        $this->seedWtax2023Grid();
        $this->seedWtaxAnnual2023();
    }

    private function seedPagibig(): void
    {
        // Contributions are fixed peso amounts (not percentages of salary).
        foreach ([
            ['govt_table_pagibig_id' => 2, 'salary_cap' => 1500.00, 'employee_contribution' => 200.00, 'employer_contribution' => 200.00],
            ['govt_table_pagibig_id' => 3, 'salary_cap' => 5000.00, 'employee_contribution' => 200.00, 'employer_contribution' => 200.00],
        ] as $row) {
            GovtTablePagibig::query()->updateOrCreate(
                ['govt_table_pagibig_id' => $row['govt_table_pagibig_id']],
                [
                    'salary_cap' => $row['salary_cap'],
                    'employee_contribution' => $row['employee_contribution'],
                    'employer_contribution' => $row['employer_contribution'],
                ],
            );
        }
    }

    private function seedPhilhealth(): void
    {
        // PhilHealth brackets (Image 1): floor / percent band / ceiling.
        // Floor ≤ ₱10,000 fixed ₱250/₱250; ₱10,000.01–₱99,999.99 = 5% of salary (50/50);
        // ≥ ₱100,000 fixed ₱2,500/₱2,500.
        GovtTablePhilhealth::withTrashed()->forceDelete();

        $rows = [
            [
                'govt_table_philhealth_id' => 1,
                'salary_from' => 1.00,
                'salary_to' => 10000.00,
                'is_percent' => false,
                'percentage' => 0.00,
                'employee_share' => 250.00,
                'employer_share' => 250.00,
                'is_active' => true,
            ],
            [
                'govt_table_philhealth_id' => 2,
                'salary_from' => 10000.01,
                'salary_to' => 99999.99,
                'is_percent' => true,
                'percentage' => 5.00,
                'employee_share' => 0.00,
                'employer_share' => 0.00,
                'is_active' => true,
            ],
            [
                'govt_table_philhealth_id' => 3,
                'salary_from' => 100000.00,
                'salary_to' => 9999999.99,
                'is_percent' => false,
                'percentage' => 0.00,
                'employee_share' => 2500.00,
                'employer_share' => 2500.00,
                'is_active' => true,
            ],
        ];

        foreach ($rows as $row) {
            GovtTablePhilhealth::query()->updateOrCreate(
                ['govt_table_philhealth_id' => $row['govt_table_philhealth_id']],
                [
                    'salary_from' => $row['salary_from'],
                    'salary_to' => $row['salary_to'],
                    'is_percent' => $row['is_percent'],
                    'percentage' => $row['percentage'],
                    'employee_share' => $row['employee_share'],
                    'employer_share' => $row['employer_share'],
                    'is_active' => $row['is_active'],
                ],
            );
        }
    }

    private function seedSss(): void
    {
        // SSS Circular No. 2024-006 — Schedule of SSS Contributions effective January 2025.
        // Total rate 15% of MSC (Employer 10% + Employee 5%).
        // MSC floor ₱5,000 / ceiling ₱35,000.
        // Regular SS MSC capped at ₱20,000; MPF MSC = Total MSC − ₱20,000 (max ₱15,000).
        // EC (employer only): ₱10 when MSC ≤ ₱14,500; ₱30 when MSC ≥ ₱15,000.
        GovtTableSss::withTrashed()->forceDelete();

        $rows = [];
        $id = 1;

        for ($totalMsc = 5000; $totalMsc <= 35000; $totalMsc += 500) {
            $regularMsc = min($totalMsc, 20000);
            $mpfMsc = max(0, $totalMsc - 20000);

            $employerRegular = round($regularMsc * 0.10, 2);
            $employerMpf = round($mpfMsc * 0.10, 2);
            $employeeRegular = round($regularMsc * 0.05, 2);
            $employeeMpf = round($mpfMsc * 0.05, 2);
            $employerEc = $totalMsc <= 14500 ? 10.00 : 30.00;

            if ($totalMsc === 5000) {
                // Circular: "Below 5,250"
                $from = 1.00;
                $to = 5249.99;
            } elseif ($totalMsc === 35000) {
                // Circular: "34,750 - Over"
                $from = 34750.00;
                $to = 99999999.99;
            } else {
                $from = (float) ($totalMsc - 250);
                $to = (float) ($totalMsc + 249.99);
            }

            $rows[] = [
                'govt_table_sss_id' => $id++,
                'compensation_from' => $from,
                'compensation_to' => $to,
                'salary_credit' => (float) $regularMsc,
                'mpf_salary_credit' => (float) $mpfMsc,
                'employer_sss' => $employerRegular,
                'employer_mpf_share' => $employerMpf,
                'employer_ec' => $employerEc,
                'employee_sss' => $employeeRegular,
                'employee_mpf_share' => $employeeMpf,
            ];
        }

        foreach ($rows as $row) {
            GovtTableSss::query()->updateOrCreate(
                ['govt_table_sss_id' => $row['govt_table_sss_id']],
                [
                    'compensation_from' => $row['compensation_from'],
                    'compensation_to' => $row['compensation_to'],
                    'salary_credit' => $row['salary_credit'],
                    'mpf_salary_credit' => $row['mpf_salary_credit'],
                    'employer_sss' => $row['employer_sss'],
                    'employer_mpf_share' => $row['employer_mpf_share'],
                    'employer_ec' => $row['employer_ec'],
                    'employee_sss' => $row['employee_sss'],
                    'employee_mpf_share' => $row['employee_mpf_share'],
                ],
            );
        }
    }

    private function seedWtax2023Grid(): void
    {
        // TRAIN law (RA 10963) Annex E — as published (Daily / Weekly / Semi-Monthly / Monthly).
        $frequencies = [
            GovtTableWtax2023::DAILY => [
                ['column_id' => 1, 'tax_amount' => 0.00, 'tax_plus' => 0.00, 'amount' => 0.00],
                ['column_id' => 2, 'tax_amount' => 0.00, 'tax_plus' => 15.00, 'amount' => 685.00],
                ['column_id' => 3, 'tax_amount' => 61.65, 'tax_plus' => 20.00, 'amount' => 1096.00],
                ['column_id' => 4, 'tax_amount' => 280.85, 'tax_plus' => 25.00, 'amount' => 2192.00],
                ['column_id' => 5, 'tax_amount' => 1102.60, 'tax_plus' => 30.00, 'amount' => 5479.00],
                ['column_id' => 6, 'tax_amount' => 6034.30, 'tax_plus' => 35.00, 'amount' => 21918.00],
            ],
            GovtTableWtax2023::WEEKLY => [
                ['column_id' => 1, 'tax_amount' => 0.00, 'tax_plus' => 0.00, 'amount' => 0.00],
                ['column_id' => 2, 'tax_amount' => 0.00, 'tax_plus' => 15.00, 'amount' => 4808.00],
                ['column_id' => 3, 'tax_amount' => 432.60, 'tax_plus' => 20.00, 'amount' => 7692.00],
                ['column_id' => 4, 'tax_amount' => 1971.20, 'tax_plus' => 25.00, 'amount' => 15385.00],
                ['column_id' => 5, 'tax_amount' => 7740.45, 'tax_plus' => 30.00, 'amount' => 38462.00],
                ['column_id' => 6, 'tax_amount' => 42355.65, 'tax_plus' => 35.00, 'amount' => 153846.00],
            ],
            GovtTableWtax2023::SEMI_MONTHLY => [
                ['column_id' => 1, 'tax_amount' => 0.00, 'tax_plus' => 0.00, 'amount' => 0.00],
                ['column_id' => 2, 'tax_amount' => 0.00, 'tax_plus' => 15.00, 'amount' => 10417.00],
                ['column_id' => 3, 'tax_amount' => 937.50, 'tax_plus' => 20.00, 'amount' => 16667.00],
                ['column_id' => 4, 'tax_amount' => 4270.70, 'tax_plus' => 25.00, 'amount' => 33333.00],
                ['column_id' => 5, 'tax_amount' => 16770.70, 'tax_plus' => 30.00, 'amount' => 83333.00],
                ['column_id' => 6, 'tax_amount' => 91770.70, 'tax_plus' => 35.00, 'amount' => 333333.00],
            ],
            GovtTableWtax2023::MONTHLY => [
                ['column_id' => 1, 'tax_amount' => 0.00, 'tax_plus' => 0.00, 'amount' => 0.00],
                ['column_id' => 2, 'tax_amount' => 0.00, 'tax_plus' => 15.00, 'amount' => 20833.00],
                ['column_id' => 3, 'tax_amount' => 1875.00, 'tax_plus' => 20.00, 'amount' => 33333.00],
                ['column_id' => 4, 'tax_amount' => 8541.80, 'tax_plus' => 25.00, 'amount' => 66667.00],
                ['column_id' => 5, 'tax_amount' => 33541.80, 'tax_plus' => 30.00, 'amount' => 166667.00],
                ['column_id' => 6, 'tax_amount' => 183541.80, 'tax_plus' => 35.00, 'amount' => 666667.00],
            ],
        ];

        foreach ($frequencies as $typeId => $columns) {
            foreach ($columns as $column) {
                $normalized = GovernmentTables::normalizeWtaxGridColumn($column);

                GovtTableWtax2023::query()->updateOrCreate(
                    [
                        'withholding_tax_table_type_id' => $typeId,
                        'column_id' => $column['column_id'],
                    ],
                    [
                        'tax_amount' => $normalized['tax_amount'],
                        'tax_plus' => $normalized['tax_plus'],
                        'amount' => $normalized['amount'],
                    ],
                );
            }
        }
    }

    private function seedWtaxAnnual2023(): void
    {
        // TRAIN law annual tax schedule effective January 1, 2023 (RA 10963).
        $rows = [
            ['govt_table_wtax_annual_2023_id' => 1, 'income_from' => 0.00, 'income_to' => 250000.00, 'amount_due' => 0.00, 'percentage_due' => 0.00],
            ['govt_table_wtax_annual_2023_id' => 2, 'income_from' => 250000.01, 'income_to' => 400000.00, 'amount_due' => 0.00, 'percentage_due' => 15.00],
            ['govt_table_wtax_annual_2023_id' => 3, 'income_from' => 400000.01, 'income_to' => 800000.00, 'amount_due' => 22500.00, 'percentage_due' => 20.00],
            ['govt_table_wtax_annual_2023_id' => 4, 'income_from' => 800000.01, 'income_to' => 2000000.00, 'amount_due' => 102500.00, 'percentage_due' => 25.00],
            ['govt_table_wtax_annual_2023_id' => 5, 'income_from' => 2000000.01, 'income_to' => 8000000.00, 'amount_due' => 402500.00, 'percentage_due' => 30.00],
            ['govt_table_wtax_annual_2023_id' => 6, 'income_from' => 8000000.01, 'income_to' => 99999999.99, 'amount_due' => 2202500.00, 'percentage_due' => 35.00],
        ];

        foreach ($rows as $row) {
            $normalized = GovernmentTables::normalizeAnnualWtaxAttributes($row);

            GovtTableWtaxAnnual2023::query()->updateOrCreate(
                ['govt_table_wtax_annual_2023_id' => $row['govt_table_wtax_annual_2023_id']],
                [
                    'income_from' => $normalized['income_from'],
                    'income_to' => $normalized['income_to'],
                    'amount_due' => $normalized['amount_due'],
                    'percentage_due' => $normalized['percentage_due'],
                ],
            );
        }
    }
}
