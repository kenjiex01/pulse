<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pulse_faculty_load_uploads')) {
            return;
        }

        Schema::table('pulse_faculty_load_uploads', function (Blueprint $table) {
            if (! Schema::hasColumn('pulse_faculty_load_uploads', 'skolaris_upload_id')) {
                $table->unsignedBigInteger('skolaris_upload_id')->nullable()->unique()->after('upload_id');
            }
            if (! Schema::hasColumn('pulse_faculty_load_uploads', 'skolaris_updated_at')) {
                $table->timestamp('skolaris_updated_at')->nullable()->after('parse_message');
            }
            if (! Schema::hasColumn('pulse_faculty_load_uploads', 'skolaris_uploader_name')) {
                $table->string('skolaris_uploader_name')->nullable()->after('skolaris_updated_at');
            }
            if (! Schema::hasColumn('pulse_faculty_load_uploads', 'skolaris_uploader_email')) {
                $table->string('skolaris_uploader_email')->nullable()->after('skolaris_uploader_name');
            }
            if (! Schema::hasColumn('pulse_faculty_load_uploads', 'pulled_at')) {
                $table->timestamp('pulled_at')->nullable()->index()->after('uploaded_by_id');
            }
            if (! Schema::hasColumn('pulse_faculty_load_uploads', 'pulled_by_id')) {
                $table->foreignId('pulled_by_id')->nullable()->after('pulled_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pulse_faculty_load_uploads')) {
            return;
        }

        Schema::table('pulse_faculty_load_uploads', function (Blueprint $table) {
            if (Schema::hasColumn('pulse_faculty_load_uploads', 'pulled_by_id')) {
                $table->dropConstrainedForeignId('pulled_by_id');
            }
            foreach (['pulled_at', 'skolaris_uploader_email', 'skolaris_uploader_name', 'skolaris_updated_at', 'skolaris_upload_id'] as $column) {
                if (Schema::hasColumn('pulse_faculty_load_uploads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
