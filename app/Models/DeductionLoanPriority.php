<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeductionLoanPriority extends Model
{
    public $timestamps = false;

    protected $table = 'tbl_deduction_loan_priority';

    protected $primaryKey = 'deduction_loan_priority_id';

    protected $fillable = [
        'deduction_type_id',
        'loan_type_id',
        'priority',
    ];

    public function deductionType(): BelongsTo
    {
        return $this->belongsTo(DeductionType::class, 'deduction_type_id', 'deduction_type_id');
    }

    public function loanType(): BelongsTo
    {
        return $this->belongsTo(LoanType::class, 'loan_type_id', 'loan_type_id');
    }

    public function typeLabel(): string
    {
        return $this->deduction_type_id ? 'Deduction' : 'Loan';
    }

    public function descriptionLabel(): string
    {
        if ($this->deduction_type_id) {
            return $this->deductionType?->description ?? '—';
        }

        return $this->loanType?->description ?? '—';
    }
}
