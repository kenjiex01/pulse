<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RawPayrollTransaction extends Model
{
    protected $table = 'raw_payroll_transactions';

    protected $primaryKey = 'payroll_transaction_id';

    public $timestamps = false;

    protected $fillable = [
        'payroll_transaction_type_id',
        'payroll_calendar_id',
        'uploaded_by_id',
        'dt_uploaded',
        'batch_no',
        'filename',
    ];

    protected function casts(): array
    {
        return [
            'payroll_transaction_type_id' => 'integer',
            'dt_uploaded' => 'datetime',
            'batch_no' => 'integer',
        ];
    }

    public function payrollCalendar(): BelongsTo
    {
        return $this->belongsTo(PayrollCalendar::class, 'payroll_calendar_id', 'payroll_calendar_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function incomeRecords(): HasMany
    {
        return $this->hasMany(RawPayrollIncome::class, 'payroll_transaction_id', 'payroll_transaction_id');
    }

    public function deductionRecords(): HasMany
    {
        return $this->hasMany(RawPayrollDeduction::class, 'payroll_transaction_id', 'payroll_transaction_id');
    }

    public function hoursWorkedRecords(): HasMany
    {
        return $this->hasMany(RawPayrollHoursWorked::class, 'payroll_transaction_id', 'payroll_transaction_id');
    }

    public function shiftCodeRecords(): HasMany
    {
        return $this->hasMany(RawPayrollShiftCode::class, 'payroll_transaction_id', 'payroll_transaction_id');
    }

    public function overtimeRecords(): HasMany
    {
        return $this->hasMany(RawPayrollOvertime::class, 'payroll_transaction_id', 'payroll_transaction_id');
    }

    public function leaveRecords(): HasMany
    {
        return $this->hasMany(RawPayrollLeave::class, 'payroll_transaction_id', 'payroll_transaction_id');
    }

    public function loanPaymentRecords(): HasMany
    {
        return $this->hasMany(RawPayrollLoanPayment::class, 'payroll_transaction_id', 'payroll_transaction_id');
    }

    public function resignedEmployeeRecords(): HasMany
    {
        return $this->hasMany(RawPayrollResignedEmployee::class, 'payroll_transaction_id', 'payroll_transaction_id');
    }
}
