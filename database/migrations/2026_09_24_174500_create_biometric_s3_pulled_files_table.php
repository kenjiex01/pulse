<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tbl_biometric_s3_pulled_files')) {
            return;
        }

        Schema::create('tbl_biometric_s3_pulled_files', function (Blueprint $table) {
            $table->id('biometric_s3_pulled_file_id');
            $table->string('s3_key', 512)->unique();
            $table->timestamp('pulled_at');
            $table->unsignedBigInteger('pulled_by_user_id')->nullable();
            $table->unsignedBigInteger('timekeeping_transaction_id')->nullable();
            $table->string('status', 32)->default('imported');
            $table->timestamps();

            $table->foreign('pulled_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('timekeeping_transaction_id', 'bio_s3_pulled_txn_fk')
                ->references('timekeeping_transaction_id')
                ->on('raw_timekeeping_transactions')
                ->nullOnDelete();
        });

        if (! Schema::hasTable('raw_timekeeping_transactions')) {
            return;
        }

        $rows = DB::table('raw_timekeeping_transactions')
            ->whereNotNull('filename')
            ->where(function ($query) {
                $query->where('filename', 'like', '%biometric_logs/%')
                    ->where(function ($inner) {
                        $inner->where('filename', 'like', '%.json.gzip')
                            ->orWhere('filename', 'like', '%.json.gz');
                    });
            })
            ->select('filename', 'dt_uploaded', 'uploaded_by_id', 'timekeeping_transaction_id')
            ->get();

        $now = now();

        foreach ($rows as $row) {
            $key = (string) ($row->filename ?? '');
            if ($key === '') {
                continue;
            }

            DB::table('tbl_biometric_s3_pulled_files')->insertOrIgnore([
                's3_key' => $key,
                'pulled_at' => $row->dt_uploaded ?? $now,
                'pulled_by_user_id' => ((int) ($row->uploaded_by_id ?? 0)) > 0 ? (int) $row->uploaded_by_id : null,
                'timekeeping_transaction_id' => $row->timekeeping_transaction_id,
                'status' => 'imported',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_biometric_s3_pulled_files');
    }
};
