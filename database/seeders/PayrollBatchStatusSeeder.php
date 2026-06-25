<?php

namespace Database\Seeders;

use App\Models\PayrollBatchStatus;
use Illuminate\Database\Seeder;

class PayrollBatchStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            PayrollBatchStatus::PENDING => 'Pending',
            PayrollBatchStatus::LOCKED => 'Locked',
            PayrollBatchStatus::PROCESSING => 'Processing',
            PayrollBatchStatus::PROCESSED => 'Processed',
            PayrollBatchStatus::AWAITING_APPROVAL => 'Awaiting Approval',
            PayrollBatchStatus::POSTING => 'Posting',
            PayrollBatchStatus::POSTED => 'Posted',
        ];

        foreach ($statuses as $id => $label) {
            PayrollBatchStatus::query()->updateOrCreate(
                ['payroll_batch_status_id' => $id],
                ['payroll_batch_status' => $label],
            );
        }
    }
}
