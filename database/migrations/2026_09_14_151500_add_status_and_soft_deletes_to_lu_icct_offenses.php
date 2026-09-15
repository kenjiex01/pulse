<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lu_icct_offenses')) {
            return;
        }

        Schema::table('lu_icct_offenses', function (Blueprint $table) {
            if (! Schema::hasColumn('lu_icct_offenses', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }

            if (! Schema::hasColumn('lu_icct_offenses', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        $this->dropSectionCodeUnique();

        Schema::table('lu_icct_offenses', function (Blueprint $table) {
            $indexNames = collect(Schema::getIndexes('lu_icct_offenses'))->pluck('name')->all();

            if (! in_array('lu_icct_offenses_section_code_idx', $indexNames, true)) {
                $table->index('section_code', 'lu_icct_offenses_section_code_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lu_icct_offenses')) {
            return;
        }

        Schema::table('lu_icct_offenses', function (Blueprint $table) {
            $indexNames = collect(Schema::getIndexes('lu_icct_offenses'))->pluck('name')->all();

            if (in_array('lu_icct_offenses_section_code_idx', $indexNames, true)) {
                $table->dropIndex('lu_icct_offenses_section_code_idx');
            }

            if (Schema::hasColumn('lu_icct_offenses', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            if (Schema::hasColumn('lu_icct_offenses', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }

    private function dropSectionCodeUnique(): void
    {
        foreach (Schema::getIndexes('lu_icct_offenses') as $index) {
            $columns = $index['columns'] ?? [];
            $isSectionCodeUnique = ($index['unique'] ?? false)
                && $columns === ['section_code'];

            if (! $isSectionCodeUnique) {
                continue;
            }

            Schema::table('lu_icct_offenses', function (Blueprint $table) use ($index) {
                $table->dropUnique($index['name']);
            });
        }
    }
};
