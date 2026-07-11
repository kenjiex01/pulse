<?php

namespace Database\Seeders;

use App\Models\GovtTablePagibig;
use App\Models\GovtTablePhilhealth;
use App\Models\GovtTablePhilhealthMinimum;
use App\Models\GovtTableSss;
use App\Models\GovtTableWtax2023;
use App\Models\GovtTableWtaxAnnual2023;
use Illuminate\Database\Seeder;

class GovernmentTablesSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPagibig();
        $this->seedPhilhealth();
        $this->seedPhilhealthMinimum();
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
        $rows = [
            ['govt_table_philhealth_id' => 9, 'salary_from' => 1.00, 'salary_to' => 4999.99, 'contribution_base' => 4000.00, 'employee_share' => 50.00, 'employer_share' => 50.00],
            ['govt_table_philhealth_id' => 10, 'salary_from' => 5000.00, 'salary_to' => 5999.99, 'contribution_base' => 5000.00, 'employee_share' => 62.50, 'employer_share' => 62.50],
            ['govt_table_philhealth_id' => 11, 'salary_from' => 6000.00, 'salary_to' => 6999.99, 'contribution_base' => 6000.00, 'employee_share' => 75.00, 'employer_share' => 75.00],
            ['govt_table_philhealth_id' => 12, 'salary_from' => 7000.00, 'salary_to' => 7999.99, 'contribution_base' => 7000.00, 'employee_share' => 87.50, 'employer_share' => 87.50],
            ['govt_table_philhealth_id' => 13, 'salary_from' => 8000.00, 'salary_to' => 8999.99, 'contribution_base' => 8000.00, 'employee_share' => 100.00, 'employer_share' => 100.00],
            ['govt_table_philhealth_id' => 14, 'salary_from' => 9000.00, 'salary_to' => 9999.99, 'contribution_base' => 9000.00, 'employee_share' => 112.50, 'employer_share' => 112.50],
            ['govt_table_philhealth_id' => 15, 'salary_from' => 10000.00, 'salary_to' => 10999.99, 'contribution_base' => 10000.00, 'employee_share' => 125.00, 'employer_share' => 125.00],
            ['govt_table_philhealth_id' => 16, 'salary_from' => 11000.00, 'salary_to' => 11999.99, 'contribution_base' => 11000.00, 'employee_share' => 137.50, 'employer_share' => 137.50],
            ['govt_table_philhealth_id' => 17, 'salary_from' => 12000.00, 'salary_to' => 12999.99, 'contribution_base' => 12000.00, 'employee_share' => 150.00, 'employer_share' => 150.00],
            ['govt_table_philhealth_id' => 18, 'salary_from' => 13000.00, 'salary_to' => 13999.99, 'contribution_base' => 13000.00, 'employee_share' => 162.50, 'employer_share' => 162.50],
            ['govt_table_philhealth_id' => 19, 'salary_from' => 14000.00, 'salary_to' => 14999.99, 'contribution_base' => 14000.00, 'employee_share' => 175.00, 'employer_share' => 175.00],
            ['govt_table_philhealth_id' => 20, 'salary_from' => 15000.00, 'salary_to' => 15999.99, 'contribution_base' => 15000.00, 'employee_share' => 187.50, 'employer_share' => 187.50],
            ['govt_table_philhealth_id' => 21, 'salary_from' => 16000.00, 'salary_to' => 16999.99, 'contribution_base' => 16000.00, 'employee_share' => 200.00, 'employer_share' => 200.00],
            ['govt_table_philhealth_id' => 22, 'salary_from' => 17000.00, 'salary_to' => 17999.99, 'contribution_base' => 17000.00, 'employee_share' => 212.50, 'employer_share' => 212.50],
            ['govt_table_philhealth_id' => 23, 'salary_from' => 18000.00, 'salary_to' => 18999.99, 'contribution_base' => 18000.00, 'employee_share' => 225.00, 'employer_share' => 225.00],
            ['govt_table_philhealth_id' => 24, 'salary_from' => 19000.00, 'salary_to' => 19999.99, 'contribution_base' => 19000.00, 'employee_share' => 237.50, 'employer_share' => 237.50],
            ['govt_table_philhealth_id' => 25, 'salary_from' => 20000.00, 'salary_to' => 20999.99, 'contribution_base' => 20000.00, 'employee_share' => 250.00, 'employer_share' => 250.00],
            ['govt_table_philhealth_id' => 26, 'salary_from' => 21000.00, 'salary_to' => 21999.99, 'contribution_base' => 21000.00, 'employee_share' => 262.50, 'employer_share' => 262.50],
            ['govt_table_philhealth_id' => 27, 'salary_from' => 22000.00, 'salary_to' => 22999.99, 'contribution_base' => 22000.00, 'employee_share' => 275.00, 'employer_share' => 275.00],
            ['govt_table_philhealth_id' => 29, 'salary_from' => 23000.00, 'salary_to' => 23999.99, 'contribution_base' => 23000.00, 'employee_share' => 287.50, 'employer_share' => 287.50],
            ['govt_table_philhealth_id' => 30, 'salary_from' => 24000.00, 'salary_to' => 24999.99, 'contribution_base' => 24000.00, 'employee_share' => 300.00, 'employer_share' => 300.00],
            ['govt_table_philhealth_id' => 31, 'salary_from' => 25000.00, 'salary_to' => 25999.99, 'contribution_base' => 25000.00, 'employee_share' => 312.50, 'employer_share' => 312.50],
            ['govt_table_philhealth_id' => 32, 'salary_from' => 26000.00, 'salary_to' => 26999.99, 'contribution_base' => 26000.00, 'employee_share' => 325.00, 'employer_share' => 325.00],
            ['govt_table_philhealth_id' => 33, 'salary_from' => 27000.00, 'salary_to' => 27999.99, 'contribution_base' => 27000.00, 'employee_share' => 337.50, 'employer_share' => 337.50],
            ['govt_table_philhealth_id' => 34, 'salary_from' => 28000.00, 'salary_to' => 28999.99, 'contribution_base' => 28000.00, 'employee_share' => 350.00, 'employer_share' => 350.00],
            ['govt_table_philhealth_id' => 35, 'salary_from' => 29000.00, 'salary_to' => 29999.99, 'contribution_base' => 29000.00, 'employee_share' => 362.50, 'employer_share' => 362.50],
            ['govt_table_philhealth_id' => 36, 'salary_from' => 30000.00, 'salary_to' => 99999999.99, 'contribution_base' => 30000.00, 'employee_share' => 375.00, 'employer_share' => 375.00],
        ];

        foreach ($rows as $row) {
            GovtTablePhilhealth::query()->updateOrCreate(
                ['govt_table_philhealth_id' => $row['govt_table_philhealth_id']],
                [
                    'salary_from' => $row['salary_from'],
                    'salary_to' => $row['salary_to'],
                    'contribution_base' => $row['contribution_base'],
                    'employee_share' => $row['employee_share'],
                    'employer_share' => $row['employer_share'],
                ],
            );
        }
    }

    private function seedPhilhealthMinimum(): void
    {
        GovtTablePhilhealthMinimum::query()->updateOrCreate(
            ['govt_table_philhealth_minimum_id' => 1],
            ['employee_amount' => 250.00, 'employer_amount' => 250.00],
        );
    }

    private function seedSss(): void
    {
        $rows = [
            ['govt_table_sss_id' => 6, 'compensation_from' => 1000.00, 'compensation_to' => 1249.99, 'salary_credit' => 1000.00, 'employer_sss' => 70.70, 'employee_sss' => 33.30, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 7, 'compensation_from' => 1250.00, 'compensation_to' => 1749.99, 'salary_credit' => 1500.00, 'employer_sss' => 106.00, 'employee_sss' => 50.00, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 8, 'compensation_from' => 1750.00, 'compensation_to' => 2249.99, 'salary_credit' => 2000.00, 'employer_sss' => 141.30, 'employee_sss' => 66.70, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 9, 'compensation_from' => 2250.00, 'compensation_to' => 2749.99, 'salary_credit' => 2500.00, 'employer_sss' => 176.70, 'employee_sss' => 83.30, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 10, 'compensation_from' => 2750.00, 'compensation_to' => 3249.99, 'salary_credit' => 3000.00, 'employer_sss' => 212.00, 'employee_sss' => 100.00, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 11, 'compensation_from' => 3250.00, 'compensation_to' => 3749.99, 'salary_credit' => 3500.00, 'employer_sss' => 247.30, 'employee_sss' => 116.70, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 12, 'compensation_from' => 3750.00, 'compensation_to' => 4249.99, 'salary_credit' => 4000.00, 'employer_sss' => 282.70, 'employee_sss' => 133.30, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 13, 'compensation_from' => 4250.00, 'compensation_to' => 4749.99, 'salary_credit' => 4500.00, 'employer_sss' => 318.00, 'employee_sss' => 150.00, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 14, 'compensation_from' => 4750.00, 'compensation_to' => 5249.99, 'salary_credit' => 5000.00, 'employer_sss' => 353.30, 'employee_sss' => 166.70, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 15, 'compensation_from' => 5250.00, 'compensation_to' => 5749.99, 'salary_credit' => 5500.00, 'employer_sss' => 388.70, 'employee_sss' => 183.30, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 16, 'compensation_from' => 5750.00, 'compensation_to' => 6249.99, 'salary_credit' => 6000.00, 'employer_sss' => 424.00, 'employee_sss' => 200.00, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 17, 'compensation_from' => 6250.00, 'compensation_to' => 6749.99, 'salary_credit' => 6500.00, 'employer_sss' => 459.30, 'employee_sss' => 216.70, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 18, 'compensation_from' => 6750.00, 'compensation_to' => 7249.99, 'salary_credit' => 7000.00, 'employer_sss' => 494.70, 'employee_sss' => 233.30, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 19, 'compensation_from' => 7250.00, 'compensation_to' => 7749.99, 'salary_credit' => 7500.00, 'employer_sss' => 530.00, 'employee_sss' => 250.00, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 20, 'compensation_from' => 7750.00, 'compensation_to' => 8249.99, 'salary_credit' => 8000.00, 'employer_sss' => 565.30, 'employee_sss' => 266.70, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 21, 'compensation_from' => 8250.00, 'compensation_to' => 8749.99, 'salary_credit' => 8500.00, 'employer_sss' => 600.70, 'employee_sss' => 283.30, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 22, 'compensation_from' => 8750.00, 'compensation_to' => 9249.99, 'salary_credit' => 9000.00, 'employer_sss' => 636.00, 'employee_sss' => 300.00, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 23, 'compensation_from' => 9250.00, 'compensation_to' => 9749.99, 'salary_credit' => 9500.00, 'employer_sss' => 671.30, 'employee_sss' => 316.70, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 24, 'compensation_from' => 9750.00, 'compensation_to' => 10249.99, 'salary_credit' => 10000.00, 'employer_sss' => 706.70, 'employee_sss' => 333.30, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 25, 'compensation_from' => 10250.00, 'compensation_to' => 10749.99, 'salary_credit' => 10500.00, 'employer_sss' => 742.00, 'employee_sss' => 350.00, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 26, 'compensation_from' => 10750.00, 'compensation_to' => 11249.99, 'salary_credit' => 11000.00, 'employer_sss' => 777.30, 'employee_sss' => 366.70, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 27, 'compensation_from' => 11250.00, 'compensation_to' => 11749.99, 'salary_credit' => 11500.00, 'employer_sss' => 812.70, 'employee_sss' => 383.30, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 28, 'compensation_from' => 11750.00, 'compensation_to' => 12249.99, 'salary_credit' => 12000.00, 'employer_sss' => 848.00, 'employee_sss' => 400.00, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 29, 'compensation_from' => 12250.00, 'compensation_to' => 12749.99, 'salary_credit' => 12500.00, 'employer_sss' => 883.30, 'employee_sss' => 416.70, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 30, 'compensation_from' => 12750.00, 'compensation_to' => 13249.99, 'salary_credit' => 13000.00, 'employer_sss' => 918.70, 'employee_sss' => 433.30, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 31, 'compensation_from' => 13250.00, 'compensation_to' => 13749.99, 'salary_credit' => 13500.00, 'employer_sss' => 954.00, 'employee_sss' => 450.00, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 32, 'compensation_from' => 13750.00, 'compensation_to' => 14249.99, 'salary_credit' => 14000.00, 'employer_sss' => 989.30, 'employee_sss' => 466.70, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 33, 'compensation_from' => 14250.00, 'compensation_to' => 14749.99, 'salary_credit' => 14500.00, 'employer_sss' => 1024.70, 'employee_sss' => 483.30, 'employer_ec' => 10.00],
            ['govt_table_sss_id' => 34, 'compensation_from' => 14750.00, 'compensation_to' => 99999999.99, 'salary_credit' => 15000.00, 'employer_sss' => 1060.00, 'employee_sss' => 500.00, 'employer_ec' => 30.00],
        ];

        foreach ($rows as $row) {
            GovtTableSss::query()->updateOrCreate(
                ['govt_table_sss_id' => $row['govt_table_sss_id']],
                [
                    'compensation_from' => $row['compensation_from'],
                    'compensation_to' => $row['compensation_to'],
                    'salary_credit' => $row['salary_credit'],
                    'employer_sss' => $row['employer_sss'],
                    'employee_sss' => $row['employee_sss'],
                    'employer_ec' => $row['employer_ec'],
                ],
            );
        }
    }

    private function seedWtax2023Grid(): void
    {
        // TRAIN law (RA 10963) withholding tax brackets — same structure as paths-legacy wtax_2023.
        $frequencies = [
            GovtTableWtax2023::DAILY => [
                ['column_id' => 1, 'tax_amount' => 0.00, 'tax_plus' => 0.00, 'amount' => 0.00],
                ['column_id' => 2, 'tax_amount' => 0.00, 'tax_plus' => 15.00, 'amount' => 684.93],
                ['column_id' => 3, 'tax_amount' => 61.64, 'tax_plus' => 20.00, 'amount' => 1095.89],
                ['column_id' => 4, 'tax_amount' => 280.82, 'tax_plus' => 25.00, 'amount' => 2191.78],
                ['column_id' => 5, 'tax_amount' => 1102.74, 'tax_plus' => 30.00, 'amount' => 5479.45],
                ['column_id' => 6, 'tax_amount' => 6034.25, 'tax_plus' => 35.00, 'amount' => 21917.81],
            ],
            GovtTableWtax2023::WEEKLY => [
                ['column_id' => 1, 'tax_amount' => 0.00, 'tax_plus' => 0.00, 'amount' => 0.00],
                ['column_id' => 2, 'tax_amount' => 0.00, 'tax_plus' => 15.00, 'amount' => 4807.69],
                ['column_id' => 3, 'tax_amount' => 432.69, 'tax_plus' => 20.00, 'amount' => 7692.31],
                ['column_id' => 4, 'tax_amount' => 1971.15, 'tax_plus' => 25.00, 'amount' => 15384.62],
                ['column_id' => 5, 'tax_amount' => 7740.38, 'tax_plus' => 30.00, 'amount' => 38461.54],
                ['column_id' => 6, 'tax_amount' => 42355.77, 'tax_plus' => 35.00, 'amount' => 153846.15],
            ],
            GovtTableWtax2023::SEMI_MONTHLY => [
                ['column_id' => 1, 'tax_amount' => 0.00, 'tax_plus' => 0.00, 'amount' => 0.00],
                ['column_id' => 2, 'tax_amount' => 0.00, 'tax_plus' => 15.00, 'amount' => 10416.67],
                ['column_id' => 3, 'tax_amount' => 937.50, 'tax_plus' => 20.00, 'amount' => 16666.67],
                ['column_id' => 4, 'tax_amount' => 4270.83, 'tax_plus' => 25.00, 'amount' => 33333.33],
                ['column_id' => 5, 'tax_amount' => 16770.83, 'tax_plus' => 30.00, 'amount' => 83333.33],
                ['column_id' => 6, 'tax_amount' => 91770.83, 'tax_plus' => 35.00, 'amount' => 333333.33],
            ],
            GovtTableWtax2023::MONTHLY => [
                ['column_id' => 1, 'tax_amount' => 0.00, 'tax_plus' => 0.00, 'amount' => 0.00],
                ['column_id' => 2, 'tax_amount' => 0.00, 'tax_plus' => 15.00, 'amount' => 20833.33],
                ['column_id' => 3, 'tax_amount' => 1875.00, 'tax_plus' => 20.00, 'amount' => 33333.33],
                ['column_id' => 4, 'tax_amount' => 8541.67, 'tax_plus' => 25.00, 'amount' => 66666.67],
                ['column_id' => 5, 'tax_amount' => 33541.67, 'tax_plus' => 30.00, 'amount' => 166666.67],
                ['column_id' => 6, 'tax_amount' => 183541.67, 'tax_plus' => 35.00, 'amount' => 666666.67],
            ],
        ];

        foreach ($frequencies as $typeId => $columns) {
            foreach ($columns as $column) {
                GovtTableWtax2023::query()->updateOrCreate(
                    [
                        'withholding_tax_table_type_id' => $typeId,
                        'column_id' => $column['column_id'],
                    ],
                    [
                        'tax_amount' => $column['tax_amount'],
                        'tax_plus' => $column['tax_plus'],
                        'amount' => $column['amount'],
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
            GovtTableWtaxAnnual2023::query()->updateOrCreate(
                ['govt_table_wtax_annual_2023_id' => $row['govt_table_wtax_annual_2023_id']],
                [
                    'income_from' => $row['income_from'],
                    'income_to' => $row['income_to'],
                    'amount_due' => $row['amount_due'],
                    'percentage_due' => $row['percentage_due'],
                ],
            );
        }
    }
}
