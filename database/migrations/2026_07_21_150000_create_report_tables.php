<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lu_report_classifications', function (Blueprint $table) {
            $table->tinyIncrements('report_classification_id');
            $table->string('code', 30)->unique();
            $table->string('name', 60);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('tbl_report_groups', function (Blueprint $table) {
            $table->tinyIncrements('report_group_id');
            $table->unsignedTinyInteger('report_classification_id');
            $table->string('name', 60);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->foreign('report_classification_id')
                ->references('report_classification_id')
                ->on('lu_report_classifications')
                ->cascadeOnDelete();
        });

        Schema::create('tbl_reports', function (Blueprint $table) {
            $table->increments('report_id');
            $table->unsignedTinyInteger('report_classification_id');
            $table->unsignedTinyInteger('report_group_id');
            $table->string('title', 120);
            $table->string('description', 255)->nullable();
            $table->string('options_key', 60);
            $table->string('generator_key', 60);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->foreign('report_classification_id')
                ->references('report_classification_id')
                ->on('lu_report_classifications')
                ->cascadeOnDelete();

            $table->foreign('report_group_id')
                ->references('report_group_id')
                ->on('tbl_report_groups')
                ->cascadeOnDelete();
        });

        Schema::create('lu_report_file_types', function (Blueprint $table) {
            $table->tinyIncrements('report_file_type_id');
            $table->string('code', 30)->unique();
            $table->string('label', 60);
            $table->string('extension', 10);
            $table->string('content_type', 80);
        });

        Schema::create('tbl_report_file_type_options', function (Blueprint $table) {
            $table->increments('report_file_type_option_id');
            $table->unsignedInteger('report_id');
            $table->unsignedTinyInteger('report_file_type_id');

            $table->foreign('report_id')
                ->references('report_id')
                ->on('tbl_reports')
                ->cascadeOnDelete();

            $table->foreign('report_file_type_id')
                ->references('report_file_type_id')
                ->on('lu_report_file_types')
                ->cascadeOnDelete();

            $table->unique(['report_id', 'report_file_type_id'], 'report_file_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_report_file_type_options');
        Schema::dropIfExists('lu_report_file_types');
        Schema::dropIfExists('tbl_reports');
        Schema::dropIfExists('tbl_report_groups');
        Schema::dropIfExists('lu_report_classifications');
    }
};
