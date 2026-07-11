<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_govt_table_philhealth_minimum', function (Blueprint $table) {
            $table->unsignedInteger('govt_table_philhealth_minimum_id')->autoIncrement();
            $table->decimal('minimum_amount', 10, 2);
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_govt_table_philhealth_minimum');
    }
};
