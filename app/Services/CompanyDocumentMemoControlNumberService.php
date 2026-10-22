<?php

namespace App\Services;

use App\Models\CompanyDocumentMemoSequence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CompanyDocumentMemoControlNumberService
{
    public function allocate(?Carbon $at = null): string
    {
        $year = (int) ($at ?? now('Asia/Manila'))->timezone('Asia/Manila')->year;

        $number = DB::transaction(function () use ($year): int {
            $now = now();

            CompanyDocumentMemoSequence::query()->insertOrIgnore([
                'year' => $year,
                'last_number' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            CompanyDocumentMemoSequence::query()
                ->where('year', $year)
                ->update([
                    'last_number' => DB::raw('last_number + 1'),
                    'updated_at' => $now,
                ]);

            return (int) CompanyDocumentMemoSequence::query()
                ->where('year', $year)
                ->value('last_number');
        });

        return $this->format($year, $number);
    }

    public function format(int $year, int $number): string
    {
        return $year.'-INV/MM-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }
}
