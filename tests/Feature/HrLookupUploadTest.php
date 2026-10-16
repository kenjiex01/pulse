<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\College;
use App\Models\Designation;
use App\Models\DocumentType;
use App\Models\EmployeeCategory;
use App\Models\EmployeeDepartment;
use App\Models\EmploymentType;
use App\Models\Position;
use App\Models\Program;
use App\Models\Rank;
use App\Models\Role;
use App\Models\User;
use App\Support\HrLookup;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class HrLookupUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            ModuleSeeder::class,
            SubModuleSeeder::class,
        ]);
    }

    public function test_each_lookup_page_has_an_upload_modal_with_a_template_download(): void
    {
        $user = $this->admin();

        foreach ([
            'campuses',
            'designations',
            'employee-categories',
            'positions',
            'ranks',
            'employment-types',
            'employee-departments',
            'colleges',
            'programs',
            'document-types',
        ] as $lookup) {
            $html = $this->actingAs($user)
                ->get(route(HrLookup::routeName($lookup)))
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString('hr-lookup-upload-'.$lookup, $html);
            $this->assertStringContainsString('Download Template', $html);
            $this->assertStringContainsString(route(HrLookup::routeName($lookup, 'upload.template')), $html);
        }
    }

    public function test_template_download_and_upload_creates_records(): void
    {
        $user = $this->admin();

        $this->uploadCsv($user, 'campuses', [
            'Campus Code' => 'ZZUP',
            'Campus Name' => 'PW Upload Campus',
            'Active' => 'Yes',
        ]);
        $this->assertNotNull(Campus::query()->where('campus_code', 'ZZUP')->first());

        $this->uploadCsv($user, 'designations', [
            'Designation Name' => 'PW Upload Designation',
            'Active' => 'Yes',
        ]);
        $this->assertNotNull(Designation::query()->where('designation_name', 'PW Upload Designation')->first());

        $this->uploadCsv($user, 'employee-categories', [
            'Category Name' => 'PW Upload Category',
            'Payroll Hours' => 'Logs',
            'Active' => 'Yes',
        ]);
        $category = EmployeeCategory::query()->where('name', 'PW Upload Category')->first();
        $this->assertNotNull($category);
        $this->assertSame(EmployeeCategory::BASIS_LOGS, $category->payroll_basis);

        $this->uploadCsv($user, 'positions', [
            'Position Name' => 'PW Upload Position',
            'Active' => 'Yes',
        ]);
        $this->assertNotNull(Position::query()->where('position_name', 'PW Upload Position')->first());

        $this->uploadCsv($user, 'ranks', [
            'Rank Name' => 'PW Upload Rank',
            'Active' => 'Yes',
        ]);
        $this->assertNotNull(Rank::query()->where('rank_name', 'PW Upload Rank')->first());

        $this->uploadCsv($user, 'employment-types', [
            'Type Code' => 'UPT',
            'Type Name' => 'PW Upload Type',
            'Active' => 'Yes',
        ]);
        $this->assertNotNull(EmploymentType::query()->where('type_name', 'PW Upload Type')->first());

        $this->uploadCsv($user, 'employee-departments', [
            'Department Code' => 'UPD',
            'Department Name' => 'PW Upload Department',
            'Active' => 'Yes',
        ]);
        $this->assertNotNull(EmployeeDepartment::query()->where('department_name', 'PW Upload Department')->first());

        $this->uploadCsv($user, 'colleges', [
            'Campus' => 'ZZUP',
            'College Code' => 'UPC',
            'College Name' => 'PW Upload College',
            'Active' => 'Yes',
        ]);
        $this->assertNotNull(College::query()->where('college_name', 'PW Upload College')->first());

        $this->uploadCsv($user, 'programs', [
            'Campus' => 'ZZUP',
            'Program Code' => 'UPP',
            'Program Name' => 'PW Upload Program',
            'Active' => 'Yes',
        ]);
        $this->assertNotNull(Program::query()->where('program_name', 'PW Upload Program')->first());

        $this->uploadCsv($user, 'document-types', [
            'Type Code' => 'UPDT',
            'Type Name' => 'PW Upload Document',
            'Required' => 'No',
            'Active' => 'Yes',
        ]);
        $this->assertNotNull(DocumentType::query()->where('type_name', 'PW Upload Document')->first());
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', Role::SLUG_ADMIN)->firstOrFail());

        return $user;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function uploadCsv(User $user, string $lookup, array $values): void
    {
        $download = $this->actingAs($user)
            ->get(route(HrLookup::routeName($lookup, 'upload.template')))
            ->assertOk();

        $csv = $download->streamedContent();
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;
        $headers = str_getcsv(strtok($csv, "\n") ?: '');
        $line = [];

        foreach ($headers as $header) {
            $line[] = $values[$header] ?? '';
        }

        $file = UploadedFile::fake()->createWithContent(
            $lookup.'_upload_template.csv',
            $csv.trim(implode(',', array_map(fn ($cell) => '"'.str_replace('"', '""', $cell).'"', $line)))."\n",
        );

        $this->actingAs($user)
            ->post(route(HrLookup::routeName($lookup, 'upload')), [
                'upload_file' => $file,
                'form_context' => 'upload-'.$lookup,
            ])
            ->assertRedirect(route(HrLookup::routeName($lookup)))
            ->assertSessionHas('success');
    }
}
