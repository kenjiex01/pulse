<?php

namespace Tests\Unit;

use App\Models\CompanyDocumentMemoSequence;
use App\Services\CompanyDocumentMemoControlNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CompanyDocumentMemoControlNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_numbers_increment_per_year_and_restart_the_next_year(): void
    {
        $service = app(CompanyDocumentMemoControlNumberService::class);

        Carbon::setTestNow(Carbon::parse('2026-10-22 08:00:00', 'Asia/Manila'));

        $this->assertSame('2026-INV/MM-001', $service->allocate());
        $this->assertSame('2026-INV/MM-002', $service->allocate());

        Carbon::setTestNow(Carbon::parse('2027-01-01 00:05:00', 'Asia/Manila'));

        $this->assertSame('2027-INV/MM-001', $service->allocate());
        $this->assertSame(2, CompanyDocumentMemoSequence::query()->where('year', 2026)->value('last_number'));
        $this->assertSame(1, CompanyDocumentMemoSequence::query()->where('year', 2027)->value('last_number'));

        Carbon::setTestNow();
    }

    public function test_format_pads_to_three_digits(): void
    {
        $service = app(CompanyDocumentMemoControlNumberService::class);

        $this->assertSame('2026-INV/MM-001', $service->format(2026, 1));
        $this->assertSame('2026-INV/MM-042', $service->format(2026, 42));
        $this->assertSame('2026-INV/MM-1107', $service->format(2026, 1107));
    }
}
