<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_employee_requests', function (Blueprint $table) {
            $table->id('employee_request_id');
            $table->unsignedBigInteger('skolaris_request_id');
            $table->string('request_number', 80);
            $table->string('request_type_code', 60);
            $table->string('request_type_name', 120);
            $table->string('status_code', 40);
            $table->string('prepared_by_employee_number', 80)->nullable();
            $table->string('prepared_by_name')->nullable();
            $table->string('department')->nullable();
            $table->string('campus_name')->nullable();
            $table->date('request_date')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('source_updated_at')->nullable();
            $table->json('approvers')->nullable();
            $table->json('form_payload')->nullable();
            $table->json('items')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->dateTime('applied_at')->nullable();
            $table->text('apply_summary')->nullable();
            $table->dateTime('pulled_at');
            $table->timestamps();
            $table->softDeletes();

            $table->unique('skolaris_request_id');
            $table->index('request_type_code');
            $table->foreign('employee_id')
                ->references('employee_id')
                ->on('tbl_employees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_employee_requests');
    }
};
