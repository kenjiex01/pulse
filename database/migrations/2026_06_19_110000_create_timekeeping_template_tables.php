<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lu_template', function (Blueprint $table) {
            $table->unsignedTinyInteger('template_id')->primary();
            $table->string('template', 100);
        });

        Schema::create('tbl_timekeeping_templates', function (Blueprint $table) {
            $table->increments('timekeeping_template_id');
            $table->unsignedTinyInteger('template_name');
            $table->string('content', 1000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('template_name', 'fk_timekeeping_templates_template_name')
                ->references('template_id')
                ->on('lu_template');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_timekeeping_templates');
        Schema::dropIfExists('lu_template');
    }
};
