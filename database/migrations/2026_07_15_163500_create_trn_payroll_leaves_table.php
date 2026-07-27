<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trn_payroll_leaves', function (Blueprint $table) {
            $table->id('payroll_leave_id');
            $table->unsignedBigInteger('payroll_batch_detail_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->dateTime('dt_from');
            $table->dateTime('dt_to');
            $table->decimal('leave_hours', 5, 2)->default(0);
            $table->decimal('cost', 10, 2)->nullable();
            $table->string('reason', 150)->nullable();
            $table->boolean('is_editable')->nullable();
            $table->boolean('is_deletable')->nullable();
            $table->boolean('is_manual')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('payroll_batch_detail_id', 'payroll_leaves_batch_detail_fk')
                ->references('payroll_batch_detail_id')
                ->on('trn_payroll_batch_details')
                ->cascadeOnDelete();
            $table->foreign('leave_type_id', 'payroll_leaves_leave_type_fk')
                ->references('leave_type_id')
                ->on('tbl_leave_types')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trn_payroll_leaves');
    }
};
