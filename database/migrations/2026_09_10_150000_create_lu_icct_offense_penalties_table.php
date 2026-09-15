<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lu_icct_offense_penalties')) {
            return;
        }

        Schema::create('lu_icct_offense_penalties', function (Blueprint $table) {
            $table->id('icct_offense_penalty_id');
            $table->char('category', 1);
            $table->unsignedTinyInteger('frequency_ordinal');
            $table->string('frequency_label', 40);
            $table->string('penalty', 120);
            $table->date('effective_date')->default('2011-02-16');
            $table->timestamps();

            $table->unique(['category', 'frequency_ordinal'], 'lu_icct_offense_penalties_cat_freq_unique');
            $table->index(['category', 'frequency_ordinal'], 'lu_icct_offense_penalties_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lu_icct_offense_penalties');
    }
};
