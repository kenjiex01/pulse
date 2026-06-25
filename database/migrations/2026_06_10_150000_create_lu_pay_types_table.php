<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lu_pay_types', function (Blueprint $table) {
            $table->unsignedTinyInteger('pay_type_id')->autoIncrement();
            $table->string('pay_type', 45);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lu_pay_types');
    }
};
