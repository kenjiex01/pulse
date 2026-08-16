<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_employee_credentials', function (Blueprint $table) {
            $table->id('employee_credential_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('description', 255);
            $table->string('original_filename', 255);
            $table->string('stored_path', 500);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('employee_id');
            $table->foreign('employee_id')
                ->references('employee_id')
                ->on('tbl_employees')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_employee_credentials');
    }
};
