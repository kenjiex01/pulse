<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_designations', function (Blueprint $table) {
            $table->id('designation_id');
            $table->string('designation_name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tbl_positions', function (Blueprint $table) {
            $table->id('position_id');
            $table->string('position_name', 200)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tbl_ranks', function (Blueprint $table) {
            $table->id('rank_id');
            $table->string('rank_name', 200)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tbl_employment_types', function (Blueprint $table) {
            $table->id('employment_type_id');
            $table->string('type_code', 20)->nullable()->unique();
            $table->string('type_name', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tbl_employee_departments', function (Blueprint $table) {
            $table->id('employee_department_id');
            $table->string('department_code', 50)->nullable()->unique();
            $table->string('department_name', 150)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tbl_colleges', function (Blueprint $table) {
            $table->id('college_id');
            $table->foreignId('campus_id')->constrained('tbl_campuses', 'campus_id')->cascadeOnDelete();
            $table->string('college_code', 20);
            $table->string('college_name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['campus_id', 'college_code']);
        });

        Schema::create('tbl_programs', function (Blueprint $table) {
            $table->id('program_id');
            $table->foreignId('campus_id')->constrained('tbl_campuses', 'campus_id')->cascadeOnDelete();
            $table->string('program_code', 20);
            $table->string('program_name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['campus_id', 'program_code']);
        });

        Schema::create('tbl_countries', function (Blueprint $table) {
            $table->id('country_id');
            $table->string('country_name', 100);
            $table->string('country_code', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tbl_regions', function (Blueprint $table) {
            $table->id('region_id');
            $table->string('region_code', 10)->unique();
            $table->string('region_name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tbl_provinces', function (Blueprint $table) {
            $table->id('province_id');
            $table->foreignId('region_id')->constrained('tbl_regions', 'region_id')->cascadeOnDelete();
            $table->string('province_code', 10)->unique();
            $table->string('province_name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tbl_cities', function (Blueprint $table) {
            $table->id('city_id');
            $table->foreignId('province_id')->constrained('tbl_provinces', 'province_id')->cascadeOnDelete();
            $table->string('city_code', 20)->unique();
            $table->string('city_name', 100);
            $table->string('type', 20)->default('city');
            $table->string('postal_code', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_cities');
        Schema::dropIfExists('tbl_provinces');
        Schema::dropIfExists('tbl_regions');
        Schema::dropIfExists('tbl_countries');
        Schema::dropIfExists('tbl_programs');
        Schema::dropIfExists('tbl_colleges');
        Schema::dropIfExists('tbl_employee_departments');
        Schema::dropIfExists('tbl_employment_types');
        Schema::dropIfExists('tbl_ranks');
        Schema::dropIfExists('tbl_positions');
        Schema::dropIfExists('tbl_designations');
    }
};
