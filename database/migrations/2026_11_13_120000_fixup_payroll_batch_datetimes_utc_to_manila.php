<?php

use App\Models\PayrollBatch;
use Illuminate\Database\Migrations\Migration;

/**
 * Payroll batch dt_* columns were stored with config('app.timezone') = UTC (naive local clock).
 * Shift stored values +8h so they match Philippine wall time after APP_TIMEZONE=Asia/Manila.
 */
return new class extends Migration
{
    private const DATETIME_ATTRIBUTES = [
        'dt_created',
        'dt_locked',
        'dt_processed',
        'dt_posted',
    ];

    public function up(): void
    {
        PayrollBatch::withTrashed()->each(function (PayrollBatch $batch): void {
            $changed = false;

            foreach (self::DATETIME_ATTRIBUTES as $attribute) {
                $value = $batch->{$attribute};
                if ($value === null) {
                    continue;
                }

                $batch->{$attribute} = $value->copy()->addHours(8);
                $changed = true;
            }

            if ($changed) {
                $batch->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        PayrollBatch::withTrashed()->each(function (PayrollBatch $batch): void {
            $changed = false;

            foreach (self::DATETIME_ATTRIBUTES as $attribute) {
                $value = $batch->{$attribute};
                if ($value === null) {
                    continue;
                }

                $batch->{$attribute} = $value->copy()->subHours(8);
                $changed = true;
            }

            if ($changed) {
                $batch->saveQuietly();
            }
        });
    }
};
