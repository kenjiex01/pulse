<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_load_pull_batch_employees', function (Blueprint $table) {
            $table->id('teaching_load_pull_batch_employee_id');
            $table->unsignedBigInteger('teaching_load_pull_batch_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedInteger('rows_count')->default(0);
            $table->string('status', 32)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['teaching_load_pull_batch_id', 'employee_id'], 'tl_pull_batch_employee_unique');
            $table->foreign('teaching_load_pull_batch_id', 'tl_pull_batch_employee_batch_fk')
                ->references('teaching_load_pull_batch_id')
                ->on('teaching_load_pull_batches')
                ->cascadeOnDelete();
            $table->foreign('employee_id', 'tl_pull_batch_employee_employee_fk')
                ->references('employee_id')
                ->on('tbl_employees')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_load_pull_batch_employees');
    }
};
