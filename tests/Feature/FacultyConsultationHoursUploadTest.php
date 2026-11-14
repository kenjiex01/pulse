<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeEmploymentInformation;
use App\Models\SysLog;
use App\Models\TeachingLoadSession;
use App\Models\TimekeepingEmployeeConsultationHour;
use App\Models\User;
use App\Support\TimekeepingEmployeeProfile;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FacultyConsultationHoursUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->user = User::query()->firstOrFail();
    }

    #[Test]
    public function upload_modal_has_type_dropdown_and_both_templates(): void
    {
        $this->actingAs($this->user)
            ->get(route(TimekeepingEmployeeProfile::routeName('index')))
            ->assertOk()
            ->assertSee('data-upload-type-select', false)
            ->assertSee('Employee Timekeeping Setup')
            ->assertSee('Consultation Hours (Faculty)')
            ->assertSee('upload/template?type=consultation', false);
    }

    #[Test]
    public function consultation_template_is_prefilled_with_faculty_only(): void
    {
        $faculty = $this->employee('EMP-UP-FAC', EmployeeEmploymentInformation::TYPE_FACULTY);
        $this->employee('EMP-UP-STF', EmployeeEmploymentInformation::TYPE_STAFF);

        TimekeepingEmployeeConsultationHour::query()->create([
            'employee_id' => $faculty->employee_id,
            'day_id' => 2,
            'time_start' => '14:00:00',
            'time_end' => '15:00:00',
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route(TimekeepingEmployeeProfile::routeName('upload.template'), ['type' => 'consultation']))
            ->assertOk();

        $content = $response->streamedContent();

        $this->assertStringStartsWith("emp_num,full_name,day,time_start,time_end\n", $content);
        $this->assertStringContainsString('EMP-UP-FAC,"Up faculty",Monday,"2:00 PM","3:00 PM"', $content);
        $this->assertStringNotContainsString('EMP-UP-STF', $content);
        $this->assertStringContainsString('consultation_hours_upload_template.csv', (string) $response->headers->get('Content-Disposition'));
    }

    #[Test]
    public function setup_template_download_still_works(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route(TimekeepingEmployeeProfile::routeName('upload.template')))
            ->assertOk();

        $this->assertStringContainsString('employee_profile_setup_template.csv', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('emp_num,', $response->streamedContent());
    }

    #[Test]
    public function consultation_upload_previews_validates_and_replaces_hours(): void
    {
        $faculty = $this->employee('EMP-UP-ONE', EmployeeEmploymentInformation::TYPE_FACULTY);
        $untouched = $this->employee('EMP-UP-TWO', EmployeeEmploymentInformation::TYPE_FACULTY);
        $this->employee('EMP-UP-STAFF', EmployeeEmploymentInformation::TYPE_STAFF);

        TimekeepingEmployeeConsultationHour::query()->create([
            'employee_id' => $faculty->employee_id,
            'day_id' => 6,
            'time_start' => '08:00:00',
            'time_end' => '09:00:00',
        ]);
        TimekeepingEmployeeConsultationHour::query()->create([
            'employee_id' => $untouched->employee_id,
            'day_id' => 3,
            'time_start' => '13:00:00',
            'time_end' => '14:00:00',
        ]);

        TeachingLoadSession::query()->create([
            'employee_id' => $faculty->employee_id,
            'session_date' => '2026-10-14',
            'employee_number' => 'EMP-UP-ONE',
            'subject_code' => 'OLHUM001',
            'schedule_day' => 'Wednesday',
            'time_in' => '11:30:00',
            'time_out' => '13:00:00',
            'date_from' => '2026-10-11',
            'date_to' => '2026-10-26',
        ]);

        $csv = implode("\n", [
            'emp_num,full_name,day,time_start,time_end',
            'Employee No.,Name,Day,Time Start,Time End',
            'EMP-UP-ONE,Up faculty,Monday,9:00 AM,10:00 AM',
            'EMP-UP-ONE,Up faculty,MON,14:00,15:00',
            'EMP-UP-ONE,Up faculty,Wednesday,11:00 AM,12:00 PM',
            'EMP-UP-TWO,Up faculty,,,',
            'EMP-UP-STAFF,Up staff,Monday,9:00 AM,10:00 AM',
            'EMP-UP-NONE,Nobody,Funday,25:00,10:00',
        ])."\n";

        $this->actingAs($this->user)
            ->post(route(TimekeepingEmployeeProfile::routeName('upload.process')), [
                'upload_type' => 'consultation',
                'upload_file' => UploadedFile::fake()->createWithContent('consultation.csv', $csv),
            ])
            ->assertRedirect(route(TimekeepingEmployeeProfile::routeName('index'), ['preview' => 1]))
            ->assertSessionHas('employee_profile_upload_type', 'consultation');

        $this->actingAs($this->user)
            ->get(route(TimekeepingEmployeeProfile::routeName('index'), ['preview' => 1]))
            ->assertOk()
            ->assertSee('Consultation Hours Upload Preview')
            ->assertSee('Monday:')
            ->assertSee('9:00 AM–10:00 AM, 2:00 PM–3:00 PM')
            ->assertSee('Wednesday 11:00 AM–12:00 PM conflicts with OLHUM001 (11:30 AM–1:00 PM).')
            ->assertSee('EMP-UP-STAFF is not a faculty employee')
            ->assertSee('Invalid employee number (EMP-UP-NONE)')
            ->assertSee('Invalid Day (Funday)');

        $this->actingAs($this->user)
            ->post(route(TimekeepingEmployeeProfile::routeName('upload.commit')), [
                'staging_token' => session('employee_profile_upload_staging_token'),
            ])
            ->assertRedirect(route(TimekeepingEmployeeProfile::routeName('index')))
            ->assertSessionHas('success', 'Consultation hours updated for 1 faculty (3 time range(s)).');

        $hours = TimekeepingEmployeeConsultationHour::query()
            ->where('employee_id', $faculty->employee_id)
            ->orderBy('day_id')
            ->orderBy('time_start')
            ->get(['day_id', 'time_start', 'time_end'])
            ->map(fn ($hour) => $hour->day_id.' '.$hour->time_start.'-'.$hour->time_end)
            ->all();

        $this->assertSame(['2 09:00:00-10:00:00', '2 14:00:00-15:00:00', '4 11:00:00-12:00:00'], $hours);
        $this->assertSame(1, TimekeepingEmployeeConsultationHour::query()->where('employee_id', $untouched->employee_id)->count());
        $this->assertTrue(SysLog::query()
            ->where('table_name', 'tbl_timekeeping_employee_consultation_hours')
            ->where('record_id', $faculty->employee_id)
            ->where('description', 'like', '%via bulk upload%')
            ->exists());
    }

    #[Test]
    public function overlapping_ranges_in_upload_are_rejected(): void
    {
        $this->employee('EMP-UP-OVER', EmployeeEmploymentInformation::TYPE_FACULTY);

        $csv = "emp_num,full_name,day,time_start,time_end\n"
            ."EMP-UP-OVER,x,Monday,9:00 AM,11:00 AM\n"
            ."EMP-UP-OVER,x,Monday,10:30 AM,12:00 PM\n";

        $this->actingAs($this->user)
            ->post(route(TimekeepingEmployeeProfile::routeName('upload.process')), [
                'upload_type' => 'consultation',
                'upload_file' => UploadedFile::fake()->createWithContent('consultation.csv', $csv),
            ]);

        $this->actingAs($this->user)
            ->get(route(TimekeepingEmployeeProfile::routeName('index'), ['preview' => 1]))
            ->assertSee('overlaps another consultation hour on the same day (line 2)')
            ->assertDontSee('Load to the Database');
    }

    #[Test]
    public function setup_template_uploaded_as_consultation_is_rejected(): void
    {
        $setupTemplate = $this->actingAs($this->user)
            ->get(route(TimekeepingEmployeeProfile::routeName('upload.template')))
            ->streamedContent();

        $this->actingAs($this->user)
            ->post(route(TimekeepingEmployeeProfile::routeName('upload.process')), [
                'upload_type' => 'consultation',
                'upload_file' => UploadedFile::fake()->createWithContent('setup.csv', $setupTemplate),
            ])
            ->assertRedirect(route(TimekeepingEmployeeProfile::routeName('index'), ['upload' => 1]))
            ->assertSessionHas('error', 'Fields from the uploaded file do not match the Consultation Hours template.');
    }

    private function employee(string $number, string $userType): Employee
    {
        $employee = Employee::query()->create([
            'employee_number' => $number,
            'first_name' => 'Up',
            'last_name' => $userType,
        ]);

        $employee->employmentInformations()->create([
            'employee_id' => $employee->employee_id,
            'user_type' => $userType,
            'sort_order' => 1,
        ]);

        return $employee;
    }
}
