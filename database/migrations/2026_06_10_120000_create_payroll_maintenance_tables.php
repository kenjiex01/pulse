<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_income_types', function (Blueprint $table) {
            $table->id('income_type_id');
            $table->unsignedTinyInteger('income_class_id')->default(0);
            $table->string('income_type_code', 4);
            $table->string('description', 45);
            $table->boolean('is_non_taxable')->nullable();
            $table->boolean('is_in_compensation_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('income_type_code');
        });

        Schema::create('tbl_deduction_types', function (Blueprint $table) {
            $table->id('deduction_type_id');
            $table->string('deduction_type_code', 4);
            $table->string('description', 45);
            $table->decimal('employer_amount', 10, 2)->default(0);
            $table->boolean('is_amount_percentage')->nullable();
            $table->boolean('is_valid_govt_deduction')->nullable();
            $table->unsignedTinyInteger('govt_table_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('deduction_type_code');
        });

        Schema::create('tbl_loan_types', function (Blueprint $table) {
            $table->id('loan_type_id');
            $table->string('loan_type_code', 4);
            $table->string('description', 45);
            $table->unsignedTinyInteger('loan_class_id')->default(0);
            $table->char('sss_loan_type', 1)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('loan_type_code');
        });

        Schema::create('tbl_leave_types', function (Blueprint $table) {
            $table->id('leave_type_id');
            $table->string('leave_type_code', 4);
            $table->string('description', 45);
            $table->unsignedInteger('computation_basis_id')->default(0);
            $table->boolean('is_valid_as_earned_leave')->nullable();
            $table->boolean('is_valid_for_adjustment')->nullable();
            $table->boolean('is_convertible_to_cash')->nullable();
            $table->decimal('hours_non_taxable', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('leave_type_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_leave_types');
        Schema::dropIfExists('tbl_loan_types');
        Schema::dropIfExists('tbl_deduction_types');
        Schema::dropIfExists('tbl_income_types');
    }
};
