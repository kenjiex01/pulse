<?php

namespace Tests\Unit;

use App\Models\RawTimekeepingInandout;
use App\Models\RawTimekeepingTransaction;
use App\Models\ShiftCode;
use App\Services\EmployeeOvertimeApprovalService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeOvertimeApprovalSecondsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function filed_minute_matches_a_punch_that_has_seconds(): void
    {
        $this->punch('2026-10-08 06:00:20', true);
        $this->punch('2026-10-08 19:07:12', false);

        $summary = app(EmployeeOvertimeApprovalService::class)->validateForStore(
            47,
            '2026-10-08',
            '06:00',
            '10:00',
            null,
            $this->shift(),
        );

        $this->assertSame('2026-10-08 06:00:00', $summary['ot_start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-08 10:00:00', $summary['ot_end']->format('Y-m-d H:i:s'));
        $this->assertSame(240, $summary['billable_minutes']);
    }

    #[Test]
    public function a_minute_before_the_punch_is_still_rejected(): void
    {
        $this->punch('2026-10-08 06:00:20', true);
        $this->punch('2026-10-08 19:07:12', false);

        $this->expectException(ValidationException::class);

        app(EmployeeOvertimeApprovalService::class)->validateForStore(
            47,
            '2026-10-08',
            '05:59',
            '10:00',
            null,
            $this->shift(),
        );
    }

    private function punch(string $datetime, bool $isIn): void
    {
        $transaction = RawTimekeepingTransaction::query()->firstOrCreate(
            ['batch_no' => 9001],
            [
                'timekeeping_transaction_type_id' => 1,
                'dt_from' => '2026-10-01',
                'dt_to' => '2026-10-31',
                'uploaded_by_id' => 1,
            ],
        );

        RawTimekeepingInandout::query()->create([
            'timekeeping_transaction_id' => $transaction->timekeeping_transaction_id,
            'employee_id' => 47,
            'dt_datetime' => Carbon::parse($datetime),
            'is_in' => $isIn,
            'timekeeping_trantype' => 1,
        ]);
    }

    private function shift(): ShiftCode
    {
        $shift = new ShiftCode;
        $shift->time_in = '10:00:00';
        $shift->time_out = '19:00:00';
        $shift->is_flexi_time = false;

        return $shift;
    }
}
