<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_time_codes', function (Blueprint $table) {
            $table->id('time_code_id');
            $table->string('time_code', 15);
            $table->string('description', 100);
            $table->boolean('is_billable')->nullable();
            $table->boolean('pass_out')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('time_code');
        });

        Schema::create('tbl_timecapture_formats', function (Blueprint $table) {
            $table->id('timecapture_format_id');
            $table->string('device_name', 50);
            $table->string('description', 100);
            $table->char('time_in_identifier', 1)->nullable();
            $table->char('time_out_identifier', 1)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('device_name');
        });

        Schema::create('tbl_timecapture_fields', function (Blueprint $table) {
            $table->id('timecapture_field_id');
            $table->foreignId('timecapture_format_id')
                ->constrained('tbl_timecapture_formats', 'timecapture_format_id')
                ->cascadeOnDelete();
            $table->string('field_name', 50);
            $table->unsignedSmallInteger('column');
            $table->boolean('new_field')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['timecapture_format_id', 'column']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_timecapture_fields');
        Schema::dropIfExists('tbl_timecapture_formats');
        Schema::dropIfExists('tbl_time_codes');
    }
};
