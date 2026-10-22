<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollDispute extends Model
{
    use SoftDeletes;

    public const UNPAID_DAYS = 'UPD';

    public const UNPAID_OVERTIME = 'UOT';

    public const OVER_DEDUCTION_UNDERTIME = 'ODU';

    public const OVER_DEDUCTION_LATE = 'ODL';

    /** @var array<string, string> */
    public const CATEGORIES = [
        self::UNPAID_DAYS => 'Unpaid Days',
        self::UNPAID_OVERTIME => 'Unpaid Overtime',
        self::OVER_DEDUCTION_UNDERTIME => 'Over Deduction - Undertime',
        self::OVER_DEDUCTION_LATE => 'Over Deduction - Late',
    ];

    /** Income type code used for each category's main line. */
    public const INCOME_TYPE_CODES = [
        self::UNPAID_DAYS => 'DUPD',
        self::UNPAID_OVERTIME => 'DUOT',
        self::OVER_DEDUCTION_UNDERTIME => 'DODU',
        self::OVER_DEDUCTION_LATE => 'DODL',
    ];

    public const UNPAID_DAYS_OT_INCOME_TYPE_CODE = 'DUPO';

    protected $table = 'trn_payroll_disputes';

    protected $primaryKey = 'payroll_dispute_id';

    protected $fillable = [
        'payroll_batch_id',
        'payroll_batch_detail_id',
        'employee_id',
        'category',
        'work_date',
        'shift_code_id',
        'time_in',
        'time_out',
        'ot_start',
        'ot_end',
        'remarks',
        'source_payroll_batch_id',
        'expected_pay',
        'expected_ot_pay',
        'computed_hours',
        'computed_amount',
        'computed_ot_hours',
        'computed_ot_amount',
        'payroll_income_id',
        'ot_payroll_income_id',
        'payroll_transaction_id',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'expected_pay' => 'decimal:2',
            'expected_ot_pay' => 'decimal:2',
            'computed_hours' => 'decimal:4',
            'computed_amount' => 'decimal:2',
            'computed_ot_hours' => 'decimal:4',
            'computed_ot_amount' => 'decimal:2',
        ];
    }

    public static function categoryLabel(?string $category): string
    {
        return self::CATEGORIES[$category ?? ''] ?? (string) $category;
    }

    public function categoryName(): string
    {
        return self::categoryLabel($this->category);
    }

    public function expectedTotal(): float
    {
        return round((float) $this->expected_pay + (float) $this->expected_ot_pay, 2);
    }

    public function computedTotal(): ?float
    {
        if ($this->computed_amount === null && $this->computed_ot_amount === null) {
            return null;
        }

        return round((float) $this->computed_amount + (float) $this->computed_ot_amount, 2);
    }

    public function changedAfterFiling(): bool
    {
        $computed = $this->computedTotal();

        return $computed !== null && abs($computed - $this->expectedTotal()) >= 0.01;
    }

    public function payrollBatch(): BelongsTo
    {
        return $this->belongsTo(PayrollBatch::class, 'payroll_batch_id', 'payroll_batch_id');
    }

    public function payrollBatchDetail(): BelongsTo
    {
        return $this->belongsTo(PayrollBatchDetail::class, 'payroll_batch_detail_id', 'payroll_batch_detail_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function shiftCode(): BelongsTo
    {
        return $this->belongsTo(ShiftCode::class, 'shift_code_id', 'shift_code_id');
    }

    public function sourcePayrollBatch(): BelongsTo
    {
        return $this->belongsTo(PayrollBatch::class, 'source_payroll_batch_id', 'payroll_batch_id');
    }
}
