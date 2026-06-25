<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_timekeeping_holidays', function (Blueprint $table) {
            $table->increments('timekeeping_holiday_id');
            $table->string('timekeeping_holiday_code', 4);
            $table->string('description', 75);
            $table->string('short_description', 25)->nullable();
            $table->date('dt_datestamp');
            $table->boolean('is_legal')->default(true);
            $table->boolean('recurring')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('timekeeping_holiday_code', 'uq_tk_holidays_code');
        });

        Schema::create('tbl_timekeeping_holiday_groups', function (Blueprint $table) {
            $table->increments('timekeeping_holiday_group_id');
            $table->string('timekeeping_holiday_group_code', 4);
            $table->string('description', 75);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('timekeeping_holiday_group_code', 'uq_tk_holiday_groups_code');
        });

        Schema::create('tbl_timekeeping_holiday_group_list', function (Blueprint $table) {
            $table->increments('timekeeping_holiday_group_list_id');
            $table->unsignedInteger('timekeeping_holiday_group_id');
            $table->unsignedInteger('timekeeping_holiday_id');

            $table->foreign('timekeeping_holiday_group_id', 'fk_holiday_group_list_group_id')
                ->references('timekeeping_holiday_group_id')
                ->on('tbl_timekeeping_holiday_groups')
                ->cascadeOnDelete();
            $table->foreign('timekeeping_holiday_id', 'fk_holiday_group_list_holiday_id')
                ->references('timekeeping_holiday_id')
                ->on('tbl_timekeeping_holidays')
                ->cascadeOnDelete();

            $table->unique(['timekeeping_holiday_group_id', 'timekeeping_holiday_id'], 'uq_holiday_group_list');
        });

        Schema::create('tbl_timekeeping_years', function (Blueprint $table) {
            $table->increments('timekeeping_year_id');
            $table->unsignedSmallInteger('timekeeping_year');
            $table->timestamps();
            $table->softDeletes();

            $table->unique('timekeeping_year', 'uq_tk_years_year');
        });

        Schema::create('tbl_timekeeping_holiday_years', function (Blueprint $table) {
            $table->increments('timekeeping_holiday_year_id');
            $table->unsignedInteger('timekeeping_year_id');
            $table->unsignedInteger('timekeeping_holiday_id');
            $table->string('timekeeping_holiday_code', 4);
            $table->date('dt_datestamp');
            $table->boolean('is_legal')->default(true);
            $table->boolean('recurring')->default(false);
            $table->timestamps();

            $table->foreign('timekeeping_year_id', 'fk_holiday_years_year_id')
                ->references('timekeeping_year_id')
                ->on('tbl_timekeeping_years')
                ->cascadeOnDelete();
            $table->foreign('timekeeping_holiday_id', 'fk_holiday_years_holiday_id')
                ->references('timekeeping_holiday_id')
                ->on('tbl_timekeeping_holidays')
                ->restrictOnDelete();

            $table->unique(['timekeeping_year_id', 'timekeeping_holiday_id'], 'uq_holiday_year_holiday');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_timekeeping_holiday_years');
        Schema::dropIfExists('tbl_timekeeping_years');
        Schema::dropIfExists('tbl_timekeeping_holiday_group_list');
        Schema::dropIfExists('tbl_timekeeping_holiday_groups');
        Schema::dropIfExists('tbl_timekeeping_holidays');
    }
};
