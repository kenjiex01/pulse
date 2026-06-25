<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_govt_table_pagibig', function (Blueprint $table) {
            $table->unsignedInteger('govt_table_pagibig_id')->autoIncrement();
            $table->decimal('salary_cap', 10, 2);
            $table->decimal('employee_contribution', 5, 2);
            $table->decimal('employer_contribution', 5, 2);
        });

        Schema::create('tbl_govt_table_philhealth', function (Blueprint $table) {
            $table->unsignedInteger('govt_table_philhealth_id')->autoIncrement();
            $table->decimal('salary_from', 10, 2);
            $table->decimal('salary_to', 10, 2);
            $table->decimal('contribution_base', 10, 2);
            $table->decimal('employee_share', 10, 2);
            $table->decimal('employer_share', 10, 2);
        });

        Schema::create('tbl_govt_table_sss', function (Blueprint $table) {
            $table->unsignedInteger('govt_table_sss_id')->autoIncrement();
            $table->decimal('compensation_from', 10, 2);
            $table->decimal('compensation_to', 10, 2);
            $table->decimal('salary_credit', 10, 2);
            $table->decimal('employer_sss', 10, 2);
            $table->decimal('employee_sss', 10, 2);
            $table->decimal('employer_ec', 10, 2);
        });

        Schema::create('tbl_withholding_tax_classes', function (Blueprint $table) {
            $table->unsignedInteger('withholding_tax_class_id')->autoIncrement();
            $table->string('withholding_tax_class_code', 4);
            $table->string('description', 45);
            $table->unsignedTinyInteger('number_of_dependents')->nullable();
            $table->decimal('exemption_amount', 10, 2)->nullable();
            $table->boolean('is_married')->nullable();
        });

        Schema::create('tbl_govt_table_wtax_2023', function (Blueprint $table) {
            $table->unsignedInteger('govt_table_wtax_2023_id')->autoIncrement();
            $table->unsignedTinyInteger('withholding_tax_table_type_id');
            $table->unsignedTinyInteger('column_id');
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('tax_plus', 5, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->unique(['withholding_tax_table_type_id', 'column_id'], 'govt_wtax_2023_type_column_unique');
        });

        Schema::create('tbl_govt_table_wtax_annual_2023', function (Blueprint $table) {
            $table->unsignedInteger('govt_table_wtax_annual_2023_id')->autoIncrement();
            $table->decimal('income_from', 10, 2);
            $table->decimal('income_to', 10, 2);
            $table->decimal('amount_due', 10, 2);
            $table->decimal('percentage_due', 5, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_govt_table_wtax_annual_2023');
        Schema::dropIfExists('tbl_govt_table_wtax_2023');
        Schema::dropIfExists('tbl_withholding_tax_classes');
        Schema::dropIfExists('tbl_govt_table_sss');
        Schema::dropIfExists('tbl_govt_table_philhealth');
        Schema::dropIfExists('tbl_govt_table_pagibig');
    }
};
