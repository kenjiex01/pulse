<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class EmployeeDeduction extends Model
{
    use SoftDeletes;

    /** Attendance deductions already computed when the calendar checkbox is ticked. */
    public const EXCLUDED_CODES = ['LTDE', 'UTDE', 'ABST', 'ALWD'];

    protected $table = 'tbl_employee_deductions';

    protected $primaryKey = 'employee_deduction_id';

    protected $fillable = [
        'employee_id',
        'deduction_type_id',
        'dt_deduction',
        'deduction_amount',
        'amortization_amount',
        'deduction_interest',
    ];

    protected function casts(): array
    {
        return [
            'dt_deduction' => 'datetime',
            'deduction_amount' => 'decimal:2',
            'amortization_amount' => 'decimal:2',
            'deduction_interest' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function deductionType(): BelongsTo
    {
        return $this->belongsTo(DeductionType::class, 'deduction_type_id', 'deduction_type_id');
    }

    public function payrollDeductions(): HasMany
    {
        return $this->hasMany(PayrollDeduction::class, 'employee_deduction_id', 'employee_deduction_id')
            ->orderBy('payroll_deduction_id');
    }

    public function outstandingBeforePayroll(): float
    {
        return round((float) $this->deduction_amount + (float) ($this->deduction_interest ?? 0), 2);
    }

    public function payrollDeductionsTotal(): float
    {
        if ($this->relationLoaded('payrollDeductions')) {
            return round($this->payrollDeductions->sum(fn (PayrollDeduction $line) => (float) $line->employee_amount), 2);
        }

        return round((float) $this->payrollDeductions()->sum(DB::raw('COALESCE(employee_amount, 0)')), 2);
    }

    public function balance(): float
    {
        return round($this->outstandingBeforePayroll() - $this->payrollDeductionsTotal(), 2);
    }

    /**
     * @return Builder<DeductionType>
     */
    public static function selectableTypesQuery(): Builder
    {
        return DeductionType::query()
            ->where('is_active', true)
            ->where(function (Builder $query) {
                $query->where('is_valid_govt_deduction', false)
                    ->orWhereNull('is_valid_govt_deduction');
            })
            ->whereNotIn('deduction_type_code', self::EXCLUDED_CODES)
            ->orderBy('description');
    }
}
