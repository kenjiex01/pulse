<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lu_icct_offenses')) {
            return;
        }

        Schema::create('lu_icct_offenses', function (Blueprint $table) {
            $table->id('icct_offense_id');
            $table->string('heading_roman', 8);
            $table->string('heading_label', 120);
            $table->unsignedSmallInteger('section_number');
            $table->string('section_code', 16);
            $table->text('nature_of_offense');
            $table->string('category', 16);
            $table->unsignedSmallInteger('sort_order');
            $table->date('effective_date')->default('2011-02-16');
            $table->timestamps();

            $table->unique('section_code', 'lu_icct_offenses_section_code_unique');
            $table->index(['heading_roman', 'section_number'], 'lu_icct_offenses_heading_section_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lu_icct_offenses');
    }
};
