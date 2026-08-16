<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\RawTimekeepingInandout;
use App\Models\User;
use App\Support\TimekeepingEmployeeProfile;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeAttendanceLogCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function can_add_edit_and_delete_manual_attendance_logs(): void
    {
        $user = User::query()->firstOrFail();
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-ATT-CRUD',
            'first_name' => 'Attendance',
            'last_name' => 'Tester',
            'email' => 'attendance.tester@example.com',
        ]);

        // ADD
        $add = $this->actingAs($user)->post(
            route(TimekeepingEmployeeProfile::routeName('attendance-store'), $employee->employee_id),
            [
                'form_context' => 'create-attendance-log-2026-08-14',
                'log_date' => '2026-08-14',
                'log_time' => '08:00',
                'is_in' => '1',
                'view_tab' => 'attendance',
                'year' => 2026,
                'month' => 8,
                'day' => '2026-08-14',
            ],
        );

        $add->assertRedirect();
        $add->assertSessionHas('success');

        $log = RawTimekeepingInandout::query()
            ->where('employee_id', $employee->employee_id)
            ->whereNull('reference_number')
            ->where('is_edited', true)
            ->orderByDesc('timekeeping_inandout_id')
            ->first();

        $this->assertNotNull($log);
        $this->assertTrue((bool) $log->is_in);
        $this->assertSame('2026-08-14 08:00:00', $log->dt_datetime?->format('Y-m-d H:i:s'));
        $this->assertTrue((bool) $log->is_edited);

        // EDIT
        $edit = $this->actingAs($user)->put(
            route(TimekeepingEmployeeProfile::routeName('attendance-update'), [
                $employee->employee_id,
                $log->timekeeping_inandout_id,
            ]),
            [
                'form_context' => 'edit-attendance-log-'.$log->timekeeping_inandout_id,
                'log_date' => '2026-08-14',
                'log_time' => '17:30',
                'is_in' => '0',
                'view_tab' => 'attendance',
                'year' => 2026,
                'month' => 8,
                'day' => '2026-08-14',
            ],
        );

        $edit->assertRedirect();
        $edit->assertSessionHas('success');

        $log->refresh();
        $this->assertFalse((bool) $log->is_in);
        $this->assertSame('2026-08-14 17:30:00', $log->dt_datetime?->format('Y-m-d H:i:s'));

        // DELETE
        $delete = $this->actingAs($user)->delete(
            route(TimekeepingEmployeeProfile::routeName('attendance-destroy'), [
                $employee->employee_id,
                $log->timekeeping_inandout_id,
            ]),
            [
                'view_tab' => 'attendance',
                'year' => 2026,
                'month' => 8,
                'day' => '2026-08-14',
            ],
        );

        $delete->assertRedirect();
        $delete->assertSessionHas('success');

        $this->assertNull(
            RawTimekeepingInandout::query()->find($log->timekeeping_inandout_id)
        );
    }

    #[Test]
    public function attendance_view_tab_loads_daily_summary(): void
    {
        $user = User::query()->firstOrFail();
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-ATT-VIEW',
            'first_name' => 'View',
            'last_name' => 'Summary',
            'email' => 'view.summary@example.com',
        ]);

        $response = $this->actingAs($user)->get(
            route(TimekeepingEmployeeProfile::routeName('attendance'), [
                'employee' => $employee->employee_id,
                'year' => 2026,
                'month' => 8,
            ]),
        );

        $response->assertOk();
        $response->assertSee('Date From', false);
        $response->assertSee('Date To', false);
        $response->assertSee('data-attendance-date-from', false);
        $response->assertSee('data-attendance-date-to', false);
        $response->assertSee('data-attendance-range-apply', false);
        $response->assertSee('Day Type', false);
        $response->assertSee('Time In', false);
        $response->assertSee('Break Late', false);
        $response->assertSee('Show all', false);
        $response->assertSee('data-client-paginate', false);
        $response->assertSee('data-paginate-per-page', false);
        $response->assertDontSee('>Sot<', false);
        $response->assertDontSee('>Ndsot<', false);
        $response->assertSee('>PDF</a>', false);
        $response->assertDontSee('Process Attendance', false);
    }

    #[Test]
    public function attendance_view_tab_filters_by_date_range(): void
    {
        $user = User::query()->firstOrFail();
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-ATT-RANGE',
            'first_name' => 'Range',
            'last_name' => 'Filter',
            'email' => 'range.filter@example.com',
        ]);

        $response = $this->actingAs($user)->get(
            route(TimekeepingEmployeeProfile::routeName('attendance'), [
                'employee' => $employee->employee_id,
                'date_from' => '2026-08-10',
                'date_to' => '2026-08-12',
            ]),
        );

        $response->assertOk();
        $response->assertSee('08/10/2026', false);
        $response->assertSee('08/11/2026', false);
        $response->assertSee('08/12/2026', false);
        $response->assertDontSee('08/09/2026', false);
        $response->assertDontSee('08/13/2026', false);
        $response->assertSee('value="2026-08-10"', false);
        $response->assertSee('value="2026-08-12"', false);
    }

    #[Test]
    public function attendance_view_pdf_downloads_with_matching_table_content(): void
    {
        $user = User::query()->firstOrFail();
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-ATT-PDF',
            'first_name' => 'Pdf',
            'last_name' => 'Tester',
            'email' => 'pdf.tester@example.com',
        ]);

        $this->actingAs($user)->post(
            route(TimekeepingEmployeeProfile::routeName('attendance-store'), $employee->employee_id),
            [
                'form_context' => 'create-attendance-log-2026-08-14',
                'log_date' => '2026-08-14',
                'log_time' => '08:00',
                'is_in' => '1',
                'view_tab' => 'attendance',
                'year' => 2026,
                'month' => 8,
                'day' => '2026-08-14',
            ],
        )->assertRedirect();

        $this->actingAs($user)->post(
            route(TimekeepingEmployeeProfile::routeName('attendance-store'), $employee->employee_id),
            [
                'form_context' => 'create-attendance-log-2026-08-14',
                'log_date' => '2026-08-14',
                'log_time' => '17:00',
                'is_in' => '0',
                'view_tab' => 'attendance',
                'year' => 2026,
                'month' => 8,
                'day' => '2026-08-14',
            ],
        )->assertRedirect();

        $expected = app(\App\Services\EmployeeAttendanceViewService::class)
            ->pdfResultForEmployee($employee->fresh(), '2026-08-01', '2026-08-31');

        $this->assertCount(31, $expected->rows);
        $this->assertSame('Attendance View', $expected->title);
        $this->assertContains('Date', $expected->headers);
        $this->assertContains('OT', $expected->headers);
        $this->assertContains('Break Late', $expected->headers);
        $this->assertNotContains('SOT', $expected->headers);
        $this->assertNotContains('NDSOT', $expected->headers);

        $aug14 = collect($expected->rows)->first(fn (array $row) => ($row[0] ?? '') === '08/14/2026');
        $this->assertIsArray($aug14);
        $this->assertSame('08:00', $aug14[3]);
        $this->assertSame('17:00', $aug14[4]);

        $response = $this->actingAs($user)->get(
            route(TimekeepingEmployeeProfile::routeName('attendance-pdf'), [
                'employee' => $employee->employee_id,
                'date_from' => '2026-08-01',
                'date_to' => '2026-08-31',
            ]),
        );

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertDownload('Attendance_View_EMP-ATT-PDF_2026-08-01_2026-08-31.pdf');

        $pdf = $response->streamedContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));

        $text = $this->extractPdfText($pdf);
        $this->assertStringContainsString('Attendance View', $text);
        $this->assertStringContainsString('EMP-ATT-PDF', $text);
        $this->assertStringContainsString('August 2026', $text);
        $this->assertStringContainsString('08/14/2026', $text);
        $this->assertStringContainsString('08:00', $text);
        $this->assertStringContainsString('17:00', $text);
        $this->assertStringContainsString('Date', $text);
        $this->assertStringContainsString('Break Late', $text);
    }

    private function extractPdfText(string $binary): string
    {
        $payload = $binary;

        if (preg_match_all('/stream\s*\r?\n(.+?)\r?\nendstream/s', $binary, $matches)) {
            foreach ($matches[1] as $stream) {
                $plain = @gzuncompress($stream) ?: @gzinflate($stream);
                if (is_string($plain) && $plain !== '') {
                    $payload .= "\n".$plain;
                }
            }
        }

        $chunks = [];

        if (preg_match_all('/\\[\\((.*?)\\)\\]\\s*TJ/s', $payload, $matches)) {
            foreach ($matches[1] as $raw) {
                $raw = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $raw);
                $chunks[] = $this->pdfLiteralToUtf8($raw);
            }
        }

        if (preg_match_all('/\\((.*?)\\)\\s*Tj/s', $payload, $matches)) {
            foreach ($matches[1] as $raw) {
                $raw = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $raw);
                $chunks[] = $this->pdfLiteralToUtf8($raw);
            }
        }

        return implode("\n", $chunks);
    }

    private function pdfLiteralToUtf8(string $raw): string
    {
        if ($raw === '') {
            return '';
        }

        $isUtf16Be = str_starts_with($raw, "\x00")
            || (strlen($raw) >= 2 && (ord($raw[0]) === 0 || ord($raw[1]) === 0));

        if ($isUtf16Be) {
            $converted = @mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE');

            return is_string($converted) ? $converted : $raw;
        }

        return $raw;
    }
}
