<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameGender('male', 'Male');
        $this->renameGender('female', 'Female');
        $this->renameGender('other', 'Other');
    }

    public function down(): void
    {
        $this->renameGender('Male', 'male');
        $this->renameGender('Female', 'female');
        $this->renameGender('Other', 'other');
    }

    private function renameGender(string $from, string $to): void
    {
        DB::table('tbl_employees')
            ->whereRaw('LOWER(gender) = ?', [strtolower($from)])
            ->update(['gender' => $to]);
    }
};
