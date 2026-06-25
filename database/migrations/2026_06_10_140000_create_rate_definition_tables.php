<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lu_days', function (Blueprint $table) {
            $table->unsignedTinyInteger('day_id')->autoIncrement();
            $table->string('day', 45);
        });

        Schema::create('lu_rate_basis', function (Blueprint $table) {
            $table->unsignedTinyInteger('rate_basis_id')->autoIncrement();
            $table->string('rate_basis', 45);
        });

        Schema::create('tbl_day_types', function (Blueprint $table) {
            $table->unsignedInteger('day_type_id')->autoIncrement();
            $table->string('day_type_code', 4);
            $table->string('description', 45);
            $table->boolean('is_restday')->nullable();
            $table->boolean('is_special_holiday')->nullable();
            $table->boolean('is_legal_holiday')->nullable();
            $table->unsignedTinyInteger('day_id')->nullable();
            $table->foreign('day_id')->references('day_id')->on('lu_days')->nullOnDelete();
        });

        Schema::create('tbl_time_types', function (Blueprint $table) {
            $table->unsignedTinyInteger('time_type_id')->autoIncrement();
            $table->string('time_type_code', 4);
            $table->string('description', 45);
            $table->unsignedTinyInteger('time_class_id')->default(0);
        });

        Schema::create('tbl_rate_groups', function (Blueprint $table) {
            $table->unsignedInteger('rate_group_id')->autoIncrement();
            $table->unsignedTinyInteger('rate_basis_id');
            $table->string('rate_group_code', 4);
            $table->string('description', 45);
            $table->foreign('rate_basis_id')->references('rate_basis_id')->on('lu_rate_basis');
        });

        Schema::create('tbl_rate_group_day_types', function (Blueprint $table) {
            $table->unsignedInteger('rate_group_day_type_id')->autoIncrement();
            $table->unsignedInteger('rate_group_id');
            $table->unsignedInteger('day_type_id');
            $table->unsignedTinyInteger('time_type_id');
            $table->unsignedBigInteger('computation_basis_id')->nullable();
            $table->unsignedBigInteger('income_type_id');
            $table->decimal('rate', 12, 4)->default(0);
            $table->boolean('is_taxable')->default(true);
            $table->foreign('rate_group_id')->references('rate_group_id')->on('tbl_rate_groups')->cascadeOnDelete();
            $table->foreign('day_type_id')->references('day_type_id')->on('tbl_day_types')->cascadeOnDelete();
            $table->foreign('time_type_id')->references('time_type_id')->on('tbl_time_types');
            $table->foreign('computation_basis_id')->references('computation_basis_id')->on('tbl_computation_basis')->nullOnDelete();
            $table->foreign('income_type_id')->references('income_type_id')->on('tbl_income_types');
        });

        Schema::create('tbl_nd_rate_groups', function (Blueprint $table) {
            $table->unsignedInteger('nd_rate_group_id')->autoIncrement();
            $table->unsignedTinyInteger('rate_basis_id');
            $table->string('nd_rate_group_code', 4);
            $table->string('description', 45);
            $table->string('tm_start', 5)->default('');
            $table->string('tm_end', 5)->default('');
            $table->foreign('rate_basis_id')->references('rate_basis_id')->on('lu_rate_basis');
        });

        Schema::create('tbl_nd_rate_group_day_types', function (Blueprint $table) {
            $table->unsignedInteger('nd_rate_group_day_type_id')->autoIncrement();
            $table->unsignedInteger('nd_rate_group_id');
            $table->unsignedInteger('day_type_id');
            $table->unsignedTinyInteger('time_type_id');
            $table->unsignedBigInteger('computation_basis_id')->nullable();
            $table->unsignedBigInteger('income_type_id');
            $table->decimal('rate', 12, 4)->default(0);
            $table->boolean('is_taxable')->default(true);
            $table->foreign('nd_rate_group_id')->references('nd_rate_group_id')->on('tbl_nd_rate_groups')->cascadeOnDelete();
            $table->foreign('day_type_id')->references('day_type_id')->on('tbl_day_types')->cascadeOnDelete();
            $table->foreign('time_type_id')->references('time_type_id')->on('tbl_time_types');
            $table->foreign('computation_basis_id')->references('computation_basis_id')->on('tbl_computation_basis')->nullOnDelete();
            $table->foreign('income_type_id')->references('income_type_id')->on('tbl_income_types');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_nd_rate_group_day_types');
        Schema::dropIfExists('tbl_nd_rate_groups');
        Schema::dropIfExists('tbl_rate_group_day_types');
        Schema::dropIfExists('tbl_rate_groups');
        Schema::dropIfExists('tbl_time_types');
        Schema::dropIfExists('tbl_day_types');
        Schema::dropIfExists('lu_rate_basis');
        Schema::dropIfExists('lu_days');
    }
};
