<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trn_payroll_batch_sources', function (Blueprint $table) {
            $table->id('payroll_batch_source_id');
            $table->unsignedBigInteger('payroll_batch_id');
            $table->unsignedBigInteger('source_payroll_batch_id');
            $table->softDeletes();

            $table->unique(['payroll_batch_id', 'source_payroll_batch_id'], 'pb_source_unique');
            $table->foreign('payroll_batch_id', 'pb_sources_batch_fk')
                ->references('payroll_batch_id')
                ->on('trn_payroll_batches');
            $table->foreign('source_payroll_batch_id', 'pb_sources_source_fk')
                ->references('payroll_batch_id')
                ->on('trn_payroll_batches');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trn_payroll_batch_sources');
    }
};
