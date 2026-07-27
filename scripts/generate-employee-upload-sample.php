<?php

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Campus;

$service = app(App\Services\EmployeeUploadService::class);
$aliases = $service->aliases();

$campusCode = Campus::query()->orderBy('campus_id')->value('campus_code');

if (! is_string($campusCode) || $campusCode === '') {
    fwrite(STDERR, "No campuses in database. Run: php artisan db:seed --class=CampusSeeder\n");
    exit(1);
}

$employees = [
    [
        'employee_number' => '2026-00101',
        'first_name' => 'Juan',
        'middle_name' => 'M.',
        'last_name' => 'Dela Cruz',
        'birth_date' => '1990-05-15',
        'place_of_birth' => 'Manila',
        'gender' => 'male',
        'civil_status' => 'single',
        'nationality' => 'Filipino',
        'campus_code' => $campusCode,
        'biometric_id' => '1001',
        'college' => 'Human Resource',
        'department' => 'Human Resource',
        'is_hybrid' => 'no',
        'compliance_status' => 'pending',
        'user_type' => 'staff',
        'position' => 'HR Officer',
        'hire_date' => '2026-01-15',
        'salary_date_effective_from' => '2026-01-15',
        'salary_pay_type' => 'Daily',
        'salary_basic_computation' => 'Time-In/Time-Out',
        'salary_rate_group' => '1',
        'salary_hours_per_day' => '8',
        'salary_basic_taxable' => '25000',
        'email' => 'juan.upload.sample@icct.edu.ph',
        'phone' => '09171234501',
        'country' => 'Philippines',
        'employment_status' => 'active',
        'role' => 'staff',
    ],
    [
        'employee_number' => '2026-00102',
        'first_name' => 'Maria',
        'middle_name' => 'L.',
        'last_name' => 'Santos',
        'birth_date' => '1992-08-20',
        'place_of_birth' => 'Quezon City',
        'gender' => 'female',
        'civil_status' => 'single',
        'nationality' => 'Filipino',
        'campus_code' => $campusCode,
        'biometric_id' => '1002',
        'college' => 'Accounting',
        'department' => 'Accounting',
        'is_hybrid' => 'no',
        'compliance_status' => 'pending',
        'user_type' => 'staff',
        'position' => 'Accountant',
        'hire_date' => '2026-02-01',
        'salary_date_effective_from' => '2026-02-01',
        'salary_pay_type' => 'Daily',
        'salary_basic_computation' => 'Time-In/Time-Out',
        'salary_rate_group' => '1',
        'salary_hours_per_day' => '8',
        'salary_basic_taxable' => '22000',
        'email' => 'maria.upload.sample@icct.edu.ph',
        'phone' => '09171234502',
        'country' => 'Philippines',
        'employment_status' => 'active',
        'role' => 'staff',
    ],
    [
        'employee_number' => '2026-00103',
        'first_name' => 'Pedro',
        'middle_name' => 'R.',
        'last_name' => 'Reyes',
        'birth_date' => '1988-11-03',
        'place_of_birth' => 'Cavite',
        'gender' => 'male',
        'civil_status' => 'married',
        'nationality' => 'Filipino',
        'campus_code' => $campusCode,
        'biometric_id' => '1003',
        'college' => 'College of Engineering',
        'department' => 'Engineering',
        'is_hybrid' => 'no',
        'compliance_status' => 'pending',
        'user_type' => 'faculty',
        'position' => 'Instructor III',
        'hire_date' => '2025-06-01',
        'salary_date_effective_from' => '2025-06-01',
        'salary_pay_type' => 'Daily',
        'salary_basic_computation' => 'Time-In/Time-Out',
        'salary_rate_group' => '1',
        'salary_hours_per_day' => '8',
        'salary_basic_taxable' => '35000',
        'email' => 'pedro.upload.sample@icct.edu.ph',
        'phone' => '09171234503',
        'country' => 'Philippines',
        'employment_status' => 'active',
        'role' => 'staff',
    ],
];

$lines = explode("\n", trim($service->buildTemplateContent()));
$out = fopen(base_path('docs/samples/employee-upload-sample.csv'), 'w');
// Keep official header rows only — skip built-in sample row (2026-00099) to avoid duplicate import errors.
foreach (array_slice($lines, 0, 2) as $line) {
    fwrite($out, $line."\n");
}
foreach ($employees as $employee) {
    $row = array_map(fn (string $alias) => $employee[$alias] ?? '', $aliases);
    $buffer = fopen('php://temp', 'r+');
    fputcsv($buffer, $row, ',', '"', '\\');
    rewind($buffer);
    fwrite($out, rtrim((string) stream_get_contents($buffer))."\n");
    fclose($buffer);
}
fclose($out);

$xlsxPath = base_path('docs/samples/employee-upload-sample.xlsx');
$handle = fopen(base_path('docs/samples/employee-upload-sample.csv'), 'r');
$rows = [];
while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
    $rows[] = array_map(fn ($cell) => (string) ($cell ?? ''), $row);
}
fclose($handle);

$columnCount = max(array_map('count', $rows));
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Employee Upload');

foreach ($rows as $rowIndex => $rowData) {
    foreach ($rowData as $colIndex => $value) {
        $coordinate = Coordinate::stringFromColumnIndex($colIndex + 1).($rowIndex + 1);
        $sheet->setCellValueExplicit($coordinate, $value, DataType::TYPE_STRING);
    }
}

$lastColumn = Coordinate::stringFromColumnIndex($columnCount);
$sheet->getStyle('A4:'.$lastColumn.'150')
    ->getNumberFormat()
    ->setFormatCode(NumberFormat::FORMAT_TEXT);
$sheet->freezePane('A4');

(new Xlsx($spreadsheet))->save($xlsxPath);
$spreadsheet->disconnectWorksheets();

echo "Wrote employee-upload-sample.csv with ".count($employees)." employees (campus: {$campusCode})\n";
echo "Wrote {$xlsxPath}\n";
