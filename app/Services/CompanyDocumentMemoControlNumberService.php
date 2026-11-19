<?php

namespace App\Services;

use App\Models\CompanyDocumentMemoSequence;
use App\Models\CompanyDocumentSendLog;
use App\Models\TimekeepingMemoSendLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyDocumentMemoControlNumberService
{
    /**
     * @return array{year: int, last_number: int, next_number: int, last_control_no: ?string, next_control_no: string}
     */
    public function status(?Carbon $at = null): array
    {
        $year = $this->year($at);
        $sent = $this->highestSentNumber($year);
        $next = $this->sequenceNumber($year) + 1;

        return [
            'year' => $year,
            'last_number' => $sent,
            'next_number' => $next,
            'last_control_no' => $sent > 0 ? $this->format($year, $sent) : null,
            'next_control_no' => $this->format($year, $next),
        ];
    }

    public function allocate(?Carbon $at = null): string
    {
        $year = $this->year($at);

        $number = DB::transaction(function () use ($year): int {
            $row = $this->lockSequence($year);

            $row->last_number = (int) $row->last_number + 1;
            $row->save();

            return (int) $row->last_number;
        });

        return $this->format($year, $number);
    }

    public function assignNext(int $number, ?Carbon $at = null): void
    {
        $year = $this->year($at);

        DB::transaction(function () use ($year, $number): void {
            $row = $this->lockSequence($year);
            $last = $this->highestSentNumber($year);

            if ($last > 0 && $number <= $last) {
                throw ValidationException::withMessages([
                    'control_number' => sprintf(
                        'Control number %s was already sent. The last control number is %s.',
                        $this->format($year, $number),
                        $this->format($year, $last),
                    ),
                ]);
            }

            $row->last_number = $number - 1;
            $row->save();
        });
    }

    public function format(int $year, int $number): string
    {
        return $year.'-INV/MM-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }

    private function year(?Carbon $at = null): int
    {
        return (int) ($at ?? now('Asia/Manila'))->timezone('Asia/Manila')->year;
    }

    private function sequenceNumber(int $year): int
    {
        return (int) CompanyDocumentMemoSequence::query()
            ->where('year', $year)
            ->value('last_number');
    }

    private function lockSequence(int $year): CompanyDocumentMemoSequence
    {
        $now = now();

        CompanyDocumentMemoSequence::query()->insertOrIgnore([
            'year' => $year,
            'last_number' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        /** @var CompanyDocumentMemoSequence $row */
        $row = CompanyDocumentMemoSequence::query()
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();

        return $row;
    }

    private function highestSentNumber(int $year): int
    {
        $prefix = $year.'-INV/MM-';
        $highest = 0;

        foreach ([TimekeepingMemoSendLog::class, CompanyDocumentSendLog::class] as $model) {
            $controlNumbers = $model::query()
                ->where('control_no', 'like', $prefix.'%')
                ->pluck('control_no');

            foreach ($controlNumbers as $controlNo) {
                if (preg_match('/-(\d+)$/', (string) $controlNo, $matches) === 1) {
                    $highest = max($highest, (int) $matches[1]);
                }
            }
        }

        return $highest;
    }
}
