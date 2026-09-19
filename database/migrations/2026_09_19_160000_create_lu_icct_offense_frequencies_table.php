<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lu_icct_offense_frequencies')) {
            return;
        }

        Schema::create('lu_icct_offense_frequencies', function (Blueprint $table) {
            $table->id('icct_offense_frequency_id');
            $table->unsignedSmallInteger('frequency_ordinal');
            $table->string('label', 64);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('frequency_ordinal', 'lu_icct_offense_frequencies_ordinal_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lu_icct_offense_frequencies');
    }
};
