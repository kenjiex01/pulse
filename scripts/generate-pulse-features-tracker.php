<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require __DIR__.'/../vendor/autoload.php';

$headers = [
    'Item',
    'Module',
    'Feature / Task',
    'Description',
    'Type',
    'Priority',
    'Status',
    'Access',
    'Assigned To',
];

$rows = [
    [1, 'Database', 'SQL Backup', 'Create a backup and download of the current database.', 'Feature', 'High', 'Completed', 'Admin', 'Ken Tordillos'],
    [2, 'Database', 'SQL Restore', 'Upload a .sql file to restore and replace the current database.', 'Feature', 'High', 'Completed', 'Admin', 'Ken Tordillos'],
    [3, 'Database', 'Daily Cloud Backup', 'Automatically upload a gzipped database backup to S3 once per day (desktop app).', 'Feature', 'High', 'Completed', 'Admin', 'Ken Tordillos'],
    [4, 'Database', 'Cloud Backup Marker Reset', 'Clear today\'s cloud backup marker so the scheduled upload can retry.', 'Feature', 'Medium', 'Completed', 'Admin', 'Ken Tordillos'],
    [5, 'Administration', 'User Management', 'Create, edit, view, and deactivate system users.', 'Feature', 'High', 'Completed', 'Admin', 'Ken Tordillos'],
    [6, 'Administration', 'Role Management', 'Create roles and assign module/sub-module access permissions.', 'Feature', 'High', 'Completed', 'Admin', 'Ken Tordillos'],
    [7, 'Administration', 'Dashboard', 'Landing page after login with module overview.', 'Feature', 'Medium', 'Completed', 'Role-based', 'Ken Tordillos'],
    [8, 'Administration', 'Login / Logout', 'Secure sign-in and sign-out for all users.', 'Feature', 'High', 'Completed', 'All Users', 'Ken Tordillos'],
    [9, 'Human Resource', 'Employee List', 'Search, filter, and browse employee records.', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [10, 'Human Resource', 'Add Employee (Wizard)', 'Step-by-step registration: campus → details → review → role assignment.', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [11, 'Human Resource', 'Edit Employee Profile', 'Update personal, employment, salary, contact, and extended profile via tabbed form.', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [12, 'Human Resource', 'View Employee Profile', 'Read-only view of full employee record.', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [13, 'Human Resource', 'Delete Employee', 'Soft-delete employee records from the list.', 'Feature', 'Medium', 'Completed', 'Role-based', 'Ken Tordillos'],
    [14, 'Human Resource', 'Employee Bulk Upload', 'Upload .xlsx/.csv to preview and commit multiple employee records.', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [15, 'Human Resource', 'Employee Compliance Status', 'Track and filter employees by compliance status (pending / compliant).', 'Feature', 'Medium', 'Completed', 'Role-based', 'Ken Tordillos'],
    [16, 'Human Resource', 'Required Employee Names', 'Require first and last name; middle name required unless No middle name is checked.', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [17, 'Human Resource', 'Government ID Validation', 'Validate TIN, SSS, PhilHealth, and Pag-IBIG digit length on create, edit, and upload.', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [18, 'Human Resource', 'Government ID Formatting', 'Display dashed format in UI; store digits-only in the database.', 'Feature', 'Medium', 'Completed', 'Role-based', 'Ken Tordillos'],
    [19, 'Human Resource', 'Hybrid Employment', 'Support one Faculty + one Staff employment record for hybrid employees.', 'Feature', 'Medium', 'Completed', 'Role-based', 'Ken Tordillos'],
    [20, 'Human Resource', 'Campus Assignments', 'Assign biometric ID and org unit per campus for each employee.', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [21, 'Human Resource', 'Campuses', 'Maintain campus reference records.', 'Feature', 'Medium', 'Completed', 'Role-based', 'Ken Tordillos'],
    [22, 'Human Resource', 'Designations', 'Maintain designation reference records.', 'Feature', 'Medium', 'Completed', 'Role-based', 'Ken Tordillos'],
    [23, 'Human Resource', 'Positions', 'Maintain position reference records.', 'Feature', 'Medium', 'Completed', 'Role-based', 'Ken Tordillos'],
    [24, 'Human Resource', 'Ranks', 'Maintain rank reference records.', 'Feature', 'Medium', 'Completed', 'Role-based', 'Ken Tordillos'],
    [25, 'Human Resource', 'Employment Types', 'Maintain employment type reference records.', 'Feature', 'Medium', 'Completed', 'Role-based', 'Ken Tordillos'],
    [26, 'Human Resource', 'Employee Departments', 'Maintain department reference records.', 'Feature', 'Medium', 'Completed', 'Role-based', 'Ken Tordillos'],
    [27, 'Human Resource', 'Colleges', 'Maintain college records linked to campus.', 'Feature', 'Medium', 'Completed', 'Role-based', 'Ken Tordillos'],
    [28, 'Human Resource', 'Programs', 'Maintain program records linked to campus.', 'Feature', 'Medium', 'Completed', 'Role-based', 'Ken Tordillos'],
    [29, 'Payroll', 'Rate Groups', 'Define regular rate groups and day-type rates.', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [30, 'Payroll', 'ND Rate Groups', 'Define night differential rate groups.', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [31, 'Payroll', 'Day Types', 'Maintain day type definitions used in rate computation.', 'Feature', 'Medium', 'Completed', 'Role-based', 'Ken Tordillos'],
    [32, 'Payroll', 'Income Types', 'Maintain payroll income type reference records.', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [33, 'Payroll', 'Deduction Types', 'Maintain payroll deduction type reference records.', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [34, 'Payroll', 'Loan Types', 'Maintain loan type reference records.', 'Feature', 'Medium', 'Completed', 'Role-based', 'Ken Tordillos'],
    [35, 'Payroll', 'Leave Types', 'Maintain leave type reference records.', 'Feature', 'Medium', 'Completed', 'Role-based', 'Ken Tordillos'],
    [36, 'Payroll', 'Government Tables', 'Maintain Pag-IBIG, PhilHealth, SSS, and withholding tax tables.', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [37, 'Payroll', 'Payroll Calendar', 'Set up pay periods by pay type (daily, weekly, semi-monthly, monthly).', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [38, 'Payroll', 'Pay Type Priority', 'Configure processing priority order across pay types.', 'Feature', 'Medium', 'Completed', 'Role-based', 'Ken Tordillos'],
    [39, 'Payroll', 'Payroll Batch', 'Create payroll batches and add employees to a run.', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [40, 'Payroll', 'Process Payroll', 'Compute payroll amounts for a batch.', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [41, 'Payroll', 'Post / Unpost Payroll', 'Finalize or reverse posted payroll batches.', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [42, 'Payroll', 'Upload Payroll Transactions', 'Bulk upload payroll transaction files with preview and commit.', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [43, 'Payroll', 'Employee Payroll Detail', 'View and adjust per-employee income and deduction lines in a batch.', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [44, 'Timekeeping', 'Timekeeping Policy', 'Configure global timekeeping rules and policy settings.', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [45, 'Timekeeping', 'Shift Codes', 'Maintain shift schedules, breaks, flexi time, and related rules.', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [46, 'Timekeeping', 'Time Capture Formats', 'Define file formats for biometric/time log imports.', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [47, 'Timekeeping', 'Timekeeping Templates', 'Maintain reusable timekeeping setup templates.', 'Feature', 'Medium', 'Completed', 'Role-based', 'Ken Tordillos'],
    [48, 'Timekeeping', 'Holiday Settings', 'Manage holidays, holiday groups, and yearly holiday entries.', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [49, 'Timekeeping', 'Time Logs Upload', 'Upload, preview, and commit biometric time log files.', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [50, 'Timekeeping', 'Employee Profile Setup', 'Configure per-employee timekeeping settings (shift, policy, approval).', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [51, 'Timekeeping', 'Employee Profile — Attendance View', 'View and edit employee attendance logs.', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
    [52, 'Timekeeping', 'Employee Profile — Approval Settings', 'Configure approval workflow settings per employee.', 'Feature', 'Medium', 'Completed', 'Role-based', 'Ken Tordillos'],
    [53, 'Timekeeping', 'Employee Load Upload', 'Bulk upload employee teaching/load schedules.', 'Feature', 'High', 'Completed', 'Role-based', 'Ken Tordillos'],
];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Pulse Features');

foreach ($headers as $columnIndex => $header) {
    $cell = $sheet->getCell([$columnIndex + 1, 1]);
    $cell->setValue($header);
}

$sheet->fromArray($rows, null, 'A2');

$lastColumn = chr(ord('A') + count($headers) - 1);
$sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '0B318F'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true,
    ],
]);

$sheet->getStyle("A2:{$lastColumn}".(count($rows) + 1))->getAlignment()->setWrapText(true);
$sheet->getStyle("A2:A".(count($rows) + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

foreach (range('A', $lastColumn) as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

$sheet->freezePane('A2');

$outputPath = __DIR__.'/../docs/pulse-features-tracker.xlsx';
$writer = new Xlsx($spreadsheet);
$writer->save($outputPath);

echo "Wrote {$outputPath}\n";
