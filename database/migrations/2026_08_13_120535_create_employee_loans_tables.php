<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lu_payment_schemes', function (Blueprint $table) {
            $table->unsignedTinyInteger('payment_scheme_id')->primary();
            $table->string('payment_scheme', 45);
        });

        Schema::create('tbl_employee_loans', function (Blueprint $table) {
            $table->id('employee_loan_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('loan_type_id');
            $table->unsignedTinyInteger('payment_scheme_id');
            $table->dateTime('dt_loan');
            $table->unsignedInteger('number_of_payments')->nullable();
            $table->decimal('principal_loan_amount', 10, 2)->nullable();
            $table->decimal('loan_amount', 10, 2)->default(0);
            $table->decimal('amortization_amount', 10, 2)->nullable();
            $table->decimal('loan_interest', 10, 2)->nullable();
            $table->decimal('paid_from_previous', 10, 2)->default(0);
            $table->decimal('deducted_from_new_loan', 10, 2)->default(0);
            $table->string('loan_purpose', 150)->nullable();
            $table->dateTime('dt_start_payment')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('employee_id');
            $table->index('loan_type_id');
            $table->index('payment_scheme_id');

            $table->foreign('employee_id')
                ->references('employee_id')
                ->on('tbl_employees')
                ->cascadeOnDelete();
            $table->foreign('loan_type_id')
                ->references('loan_type_id')
                ->on('tbl_loan_types')
                ->restrictOnDelete();
            $table->foreign('payment_scheme_id')
                ->references('payment_scheme_id')
                ->on('lu_payment_schemes')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_employee_loans');
        Schema::dropIfExists('lu_payment_schemes');
    }
};
