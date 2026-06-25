<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_timekeeping_transactions', function (Blueprint $table) {
            $table->id('timekeeping_transaction_id');
            $table->unsignedInteger('timekeeping_transaction_type_id')->default(0);
            $table->dateTime('dt_from');
            $table->dateTime('dt_to');
            $table->unsignedBigInteger('uploaded_by_id')->default(0);
            $table->timestamp('dt_uploaded')->useCurrent();
            $table->unsignedInteger('batch_no')->default(0);
            $table->string('filename', 255)->nullable();
            $table->foreignId('timecapture_format_id')
                ->nullable()
                ->constrained('tbl_timecapture_formats', 'timecapture_format_id')
                ->nullOnDelete();

            $table->index('timekeeping_transaction_type_id', 'idx_rtt_transaction_type');
            $table->index('batch_no', 'idx_rtt_batch_no');
        });

        Schema::create('raw_timekeeping_inandout', function (Blueprint $table) {
            $table->id('timekeeping_inandout_id');
            $table->foreignId('timekeeping_transaction_id')
                ->constrained('raw_timekeeping_transactions', 'timekeeping_transaction_id')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id');
            $table->dateTime('dt_datetime');
            $table->boolean('is_in')->nullable();
            $table->integer('timekeeping_trantype')->default(1);
            $table->integer('reference_number')->nullable();

            $table->index('employee_id', 'idx_rtio_employee');
            $table->index('timekeeping_trantype', 'idx_rtio_trantype');
        });

        Schema::create('raw_timekeeping_time_logs', function (Blueprint $table) {
            $table->id('timekeeping_time_log_id');
            $table->foreignId('timekeeping_transaction_id')
                ->constrained('raw_timekeeping_transactions', 'timekeeping_transaction_id')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id');
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
            $table->date('date_out')->nullable();
            $table->unsignedBigInteger('time_code_id')->nullable();

            $table->index('employee_id', 'idx_rttl_employee');
            $table->index('time_code_id', 'idx_rttl_time_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_timekeeping_time_logs');
        Schema::dropIfExists('raw_timekeeping_inandout');
        Schema::dropIfExists('raw_timekeeping_transactions');
    }
};
