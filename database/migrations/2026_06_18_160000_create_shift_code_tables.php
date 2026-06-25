<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_shift_codes', function (Blueprint $table) {
            $table->increments('shift_code_id');
            $table->string('shift_code', 4)->unique();
            $table->string('description', 45);
            $table->string('time_in', 5);
            $table->string('time_out', 5);
            $table->timestamps();
        });

        Schema::create('tbl_shift_code_breaks', function (Blueprint $table) {
            $table->increments('shift_code_break_id');
            $table->unsignedInteger('shift_code_id');
            $table->unsignedTinyInteger('shift_code_break_no');
            $table->unsignedSmallInteger('shift_code_break_minute');
            $table->boolean('shift_code_is_paid_break')->default(false);

            $table->foreign('shift_code_id', 'fk_shift_code_breaks_shift_code_id')
                ->references('shift_code_id')
                ->on('tbl_shift_codes')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_shift_code_breaks');
        Schema::dropIfExists('tbl_shift_codes');
    }
};
