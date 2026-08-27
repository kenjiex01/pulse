<?php

/**
 * Generates a user-friendly Word document:
 * People360 vs HRIS Blueprint — What's Ready / What's Next
 */

require __DIR__.'/../vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Language;
use PhpOffice\PhpWord\Style\Table;

$target = __DIR__.'/People360-HRIS-Blueprint-Status-2026-08-27.docx';

$phpWord = new PhpWord();
$phpWord->getSettings()->setThemeFontLang(new Language(Language::EN_US));
$phpWord->setDefaultFontName('Calibri');
$phpWord->setDefaultFontSize(11);

$phpWord->addTitleStyle(1, ['bold' => true, 'size' => 20, 'color' => '0B318F'], ['spaceAfter' => 120]);
$phpWord->addTitleStyle(2, ['bold' => true, 'size' => 14, 'color' => '0B318F'], ['spaceBefore' => 280, 'spaceAfter' => 100]);
$phpWord->addTitleStyle(3, ['bold' => true, 'size' => 12, 'color' => '1e40af'], ['spaceBefore' => 200, 'spaceAfter' => 80]);

$section = $phpWord->addSection([
    'marginTop' => 720,
    'marginBottom' => 720,
    'marginLeft' => 900,
    'marginRight' => 900,
]);

$p = ['spaceAfter' => 120, 'lineHeight' => 1.2];
$pTight = ['spaceAfter' => 60, 'lineHeight' => 1.15];

$tableStyle = [
    'borderSize' => 4,
    'borderColor' => 'CBD5E1',
    'cellMargin' => 60,
];
$phpWord->addTableStyle('StatusTable', $tableStyle);
$phpWord->addTableStyle('ModuleTable', $tableStyle);

$headerCell = ['bgColor' => '0B318F', 'valign' => 'center'];
$headerFont = ['bold' => true, 'color' => 'FFFFFF', 'size' => 10];
$cellFont = ['size' => 10];
$partialBg = ['bgColor' => 'FEF3C7'];
$pendingBg = ['bgColor' => 'FEE2E2'];
$readyBg = ['bgColor' => 'DCFCE7'];

// ----- Cover -----
$section->addTitle('People360', 1);
$section->addText(
    'HRIS Blueprint Status Report',
    ['bold' => true, 'size' => 16, 'color' => '00A3E6'],
    $p
);
$section->addText(
    'What is already working · What is still incomplete · What is not yet built',
    ['size' => 11, 'color' => '64748B', 'italic' => true],
    $p
);
$section->addTextBreak(1);

$meta = $section->addTable('StatusTable');
$meta->addRow();
$meta->addCell(2800, $headerCell)->addText('Document', $headerFont);
$meta->addCell(6200)->addText('People360 vs HRIS Software Module Blueprint', $cellFont);
$meta->addRow();
$meta->addCell(2800, $headerCell)->addText('App', $headerFont);
$meta->addCell(6200)->addText('People360 (ISKOLARIS desktop)', $cellFont);
$meta->addRow();
$meta->addCell(2800, $headerCell)->addText('Date', $headerFont);
$meta->addCell(6200)->addText('27 August 2026', $cellFont);
$meta->addRow();
$meta->addCell(2800, $headerCell)->addText('Audience', $headerFont);
$meta->addCell(6200)->addText('Management, HR, and project stakeholders', $cellFont);

$section->addTextBreak(1);
$section->addText(
    'This report compares the Philippine HRIS Software Module Blueprint (28 modules) with what People360 can do today. It is a planning guide — not a legal review. Government rates and labor rules must still be checked with HR/Legal before go-live.',
    ['size' => 10, 'color' => '475569'],
    $p
);

// ----- How to read -----
$section->addTitle('How to read this report', 2);
$section->addText('We use three simple statuses:', $p);

$legend = $section->addTable('StatusTable');
$legend->addRow();
$legend->addCell(2200, array_merge($readyBg, ['valign' => 'center']))->addText('Ready', ['bold' => true, 'size' => 10, 'color' => '166534']);
$legend->addCell(6800)->addText('Fully matches the blueprint for day-to-day use. (None of the 28 modules are 100% ready yet.)', $cellFont);
$legend->addRow();
$legend->addCell(2200, array_merge($partialBg, ['valign' => 'center']))->addText('Partial', ['bold' => true, 'size' => 10, 'color' => '92400E']);
$legend->addCell(6800)->addText('People360 already has useful screens and workflows, but important blueprint items are still missing.', $cellFont);
$legend->addRow();
$legend->addCell(2200, array_merge($pendingBg, ['valign' => 'center']))->addText('Not yet built', ['bold' => true, 'size' => 10, 'color' => '991B1B']);
$legend->addCell(6800)->addText('No matching module or workflow in People360 yet.', $cellFont);

// ----- Snapshot -----
$section->addTitle('Snapshot — where we are today', 2);
$section->addText(
    'People360 is strongest as a school payroll and timekeeping system (faculty + staff), with employee records and government/BIR reporting. It is not yet a full hire-to-retire HRIS.',
    $p
);

$snap = $section->addTable('StatusTable');
$snap->addRow();
$snap->addCell(3000, $headerCell)->addText('Status', $headerFont);
$snap->addCell(2000, $headerCell)->addText('Count', $headerFont);
$snap->addCell(4000, $headerCell)->addText('Meaning for stakeholders', $headerFont);
$snap->addRow();
$snap->addCell(3000, $readyBg)->addText('Fully ready', ['bold' => true, 'size' => 10]);
$snap->addCell(2000, $readyBg)->addText('0 of 28', $cellFont);
$snap->addCell(4000, $readyBg)->addText('No module is complete vs the full blueprint.', $cellFont);
$snap->addRow();
$snap->addCell(3000, $partialBg)->addText('Partial (in use)', ['bold' => true, 'size' => 10]);
$snap->addCell(2000, $partialBg)->addText('12 of 28', $cellFont);
$snap->addCell(4000, $partialBg)->addText('Already helping daily operations; still has gaps.', $cellFont);
$snap->addRow();
$snap->addCell(3000, $pendingBg)->addText('Not yet built', ['bold' => true, 'size' => 10]);
$snap->addCell(2000, $pendingBg)->addText('16 of 28', $cellFont);
$snap->addCell(4000, $pendingBg)->addText('Needs new design and development.', $cellFont);

$section->addTextBreak(1);
$section->addText('In plain words:', ['bold' => true], $pTight);
$section->addListItem('You can manage employees, run payroll, pull time logs, and generate BIR / SSS / PhilHealth / Pag-IBIG reports.', 0, null, null, $pTight);
$section->addListItem('You cannot yet run recruitment, employee self-service leave filing, contractor management, discipline cases, or full clearance/separation.', 0, null, null, $pTight);
$section->addListItem('Faculty teaching load and faculty payroll are already supported for schools.', 0, null, null, $pTight);

// ----- What works well -----
$section->addTitle('What already works well', 2);
$section->addText('These areas are the strongest parts of People360 today:', $p);

$strong = [
    ['Payroll', 'Pay periods, compute and post payroll, overtime / night differential / holiday pay, payslips, payroll register, BIR forms (1601-C, 2316, Alphalist).'],
    ['Government contributions', 'SSS, PhilHealth, Pag-IBIG tables and contribution reports; employee loans.'],
    ['Timekeeping', 'Policies, shifts, holidays, time log upload, biometric log pull, attendance view/edit, overtime for payroll.'],
    ['Employee records', 'One employee profile for faculty, staff, and admin (including hybrid); campus assignments; salary history; document uploads.'],
    ['Faculty load (schools)', 'Pull teaching loads from Skolaris, upload loads, and pay faculty from load hours.'],
    ['Access & audit', 'Login, roles with module permissions, and system logs for create/update/delete actions.'],
];

foreach ($strong as [$title, $desc]) {
    $section->addText($title, ['bold' => true, 'size' => 11, 'color' => '0B318F'], $pTight);
    $section->addText($desc, ['size' => 10], $p);
}

// ----- What's incomplete -----
$section->addTitle('What is partial (usable, but incomplete)', 2);
$section->addText('These modules exist in People360 but do not yet cover the full blueprint:', $p);

$partialModules = [
    ['M01', 'Organization setup', 'Campuses, departments, positions, ranks, calendar, holidays are in. Missing: job grades, cost centers, org chart, approval matrices.'],
    ['M02', 'Employee master', 'Rich employee profile is in. Missing: dependents as records, bank accounts, consultant/contractor as a separate worker type.'],
    ['M04', 'Onboarding & documents', 'You can define document types and upload files. Missing: onboarding checklist, e-contracts, acknowledgments, equipment/access requests.'],
    ['M05', 'Time & attendance', 'Import and process attendance is strong. Missing: live clock-in app, QR/mobile punch, official business, employee self-service corrections.'],
    ['M06', 'Leave', 'Leave types affect payroll. Missing: leave filing, balances, accrual, manager approval.'],
    ['M07', 'Payroll', 'Core payroll is strong. Missing: maker-checker, bank/GL disbursement file, dedicated final-pay and 13th-month runs.'],
    ['M08', 'Benefits', 'Statutory tables and reports exist. Missing: HMO enrollment, remittance reconciliation, effective-dated contribution tables.'],
    ['M15', 'Privacy & records', 'Audit logs and soft delete exist. Missing: retention rules, legal hold, data-subject request workflow.'],
    ['M17', 'Legal rules', 'Rate groups and government tables exist. Missing: version history when rates change (old payroll must still use old rates).'],
    ['M18', 'Dashboards & reports', 'Operational reports exist. Missing: turnover, time-to-hire, and executive KPI dashboards.'],
    ['M19', 'Faculty personnel', 'Faculty/staff/admin and academic lookups exist. Missing: PRC licenses, subjects qualified to teach.'],
    ['M20', 'Faculty load', 'Load pull and faculty pay exist. Missing: schedule conflict checks and overload approval.'],
];

$modTable = $section->addTable('ModuleTable');
$modTable->addRow();
$modTable->addCell(900, $headerCell)->addText('ID', $headerFont);
$modTable->addCell(2400, $headerCell)->addText('Area', $headerFont);
$modTable->addCell(5700, $headerCell)->addText('What you should know', $headerFont);

foreach ($partialModules as [$id, $area, $note]) {
    $modTable->addRow();
    $modTable->addCell(900, $partialBg)->addText($id, ['bold' => true, 'size' => 9]);
    $modTable->addCell(2400, $partialBg)->addText($area, ['bold' => true, 'size' => 9]);
    $modTable->addCell(5700)->addText($note, ['size' => 9]);
}

// ----- Not yet built -----
$section->addTitle('What is not yet built', 2);
$section->addText('These blueprint modules have no People360 screens or workflows yet.', $p);

$section->addTitle('Needed for a complete MVP (recommended soon)', 3);
$mvpPending = [
    ['M03', 'Recruitment', 'Job requests, posting, applicants, interviews, offers.'],
    ['M11', 'Discipline & grievance', 'NTE, hearings, sanctions, appeals.'],
    ['M12', 'Separation & clearance', 'Resignation/termination, clearance, COE, final pay, exit interview.'],
    ['M13', 'Contractors / outsourced staff', 'Agency accreditation, deployed workers, compliance evidence.'],
    ['M14', 'Occupational Safety & Health', 'Incidents, PPE, drills, corrective actions.'],
    ['M16', 'Employee self-service', 'Employees/faculty request leave, OT, certificates, and get approvals in-app.'],
];

$mvpTable = $section->addTable('ModuleTable');
$mvpTable->addRow();
$mvpTable->addCell(900, $headerCell)->addText('ID', $headerFont);
$mvpTable->addCell(2800, $headerCell)->addText('Module', $headerFont);
$mvpTable->addCell(5300, $headerCell)->addText('Why it matters', $headerFont);
foreach ($mvpPending as [$id, $name, $why]) {
    $mvpTable->addRow();
    $mvpTable->addCell(900, $pendingBg)->addText($id, ['bold' => true, 'size' => 9]);
    $mvpTable->addCell(2800, $pendingBg)->addText($name, ['bold' => true, 'size' => 9]);
    $mvpTable->addCell(5300)->addText($why, ['size' => 9]);
}

$section->addTitle('Later phases (can wait)', 3);
$section->addListItem('Phase 2 — Performance, Learning & Development, richer analytics, faculty evaluation/promotion, faculty research, student-safeguarding link.', 0, null, null, $pTight);
$section->addListItem('Phase 3 — Succession planning, AI assistance, SDG/ESG reporting.', 0, null, null, $pTight);
$section->addListItem('Optional (private business) — Sales commissions; field/branch workforce ops.', 0, null, null, $pTight);

// ----- Full checklist -----
$section->addTitle('Full checklist (all 28 modules)', 2);
$section->addText('Quick reference for the whole blueprint:', $p);

$all = [
    ['M01', 'Organization & HR Configuration', 'CORE', 'MVP', 'Partial'],
    ['M02', 'Worker / Employee Master Data', 'CORE', 'MVP', 'Partial'],
    ['M03', 'Recruitment & Applicant Tracking', 'CORE', 'MVP', 'Not yet built'],
    ['M04', 'Onboarding & Employment Documents', 'CORE', 'MVP', 'Partial'],
    ['M05', 'Time, Attendance & Scheduling', 'CORE', 'MVP', 'Partial'],
    ['M06', 'Leave & Absence Management', 'CORE', 'MVP', 'Partial'],
    ['M07', 'Payroll & Statutory Pay', 'CORE', 'MVP', 'Partial'],
    ['M08', 'Benefits & Government Contributions', 'CORE', 'MVP', 'Partial'],
    ['M09', 'Performance Management', 'CORE', 'Phase 2', 'Not yet built'],
    ['M10', 'Learning & Development', 'CORE', 'Phase 2', 'Not yet built'],
    ['M11', 'Employee Relations, Grievance & Discipline', 'CORE', 'MVP', 'Not yet built'],
    ['M12', 'Separation, Clearance & Retirement', 'CORE', 'MVP', 'Not yet built'],
    ['M13', 'Contractor & Outsourced Workforce', 'OUTSOURCE', 'MVP', 'Not yet built'],
    ['M14', 'Occupational Safety & Health', 'CORE/OUTSOURCE', 'MVP', 'Not yet built'],
    ['M15', 'Data Privacy, Documents & Records', 'CORE', 'MVP', 'Partial'],
    ['M16', 'Self-Service, Requests & Approvals', 'CORE', 'MVP', 'Not yet built'],
    ['M17', 'HR Compliance & Legal Rules Engine', 'CORE', 'MVP', 'Partial'],
    ['M18', 'HR Analytics, Dashboards & Audit', 'CORE', 'Phase 2', 'Partial'],
    ['M19', 'Faculty & Academic Personnel', 'EDU', 'MVP (schools)', 'Partial'],
    ['M20', 'Faculty Load, Schedule & Overload', 'EDU', 'MVP (schools)', 'Partial'],
    ['M21', 'Faculty Evaluation, Rank & Promotion', 'EDU', 'Phase 2', 'Not yet built'],
    ['M22', 'Faculty Development, Research & Credentials', 'EDU', 'Phase 2', 'Not yet built'],
    ['M23', 'Student-Safeguarding / Faculty Conduct', 'EDU', 'Phase 2', 'Not yet built'],
    ['M24', 'Sales / Commission & Incentive', 'BUS', 'Optional', 'Not yet built'],
    ['M25', 'Shift, Field & Branch Workforce', 'BUS', 'Optional', 'Not yet built'],
    ['M26', 'Succession, Talent & Workforce Planning', 'CORE/BUS/EDU', 'Phase 3', 'Not yet built'],
    ['M27', 'AI Assistance & Automation', 'CORE', 'Phase 3', 'Not yet built'],
    ['M28', 'SDG / ESG Workforce Reporting', 'CORE', 'Phase 3', 'Not yet built'],
];

$allTable = $section->addTable('ModuleTable');
$allTable->addRow();
$allTable->addCell(800, $headerCell)->addText('ID', $headerFont);
$allTable->addCell(3600, $headerCell)->addText('Module', $headerFont);
$allTable->addCell(1600, $headerCell)->addText('Priority', $headerFont);
$allTable->addCell(2000, $headerCell)->addText('Status', $headerFont);

foreach ($all as [$id, $name, $scope, $priority, $status]) {
    $bg = $status === 'Partial' ? $partialBg : $pendingBg;
    $allTable->addRow();
    $allTable->addCell(800, $bg)->addText($id, ['bold' => true, 'size' => 8]);
    $allTable->addCell(3600)->addText($name, ['size' => 8]);
    $allTable->addCell(1600)->addText($priority, ['size' => 8]);
    $allTable->addCell(2000, $bg)->addText($status, ['bold' => true, 'size' => 8]);
}

// ----- Suggested next steps -----
$section->addTitle('Suggested next steps', 2);
$section->addText(
    'If we follow the blueprint from where People360 is today, this order closes the biggest MVP gaps first:',
    $p
);

$steps = [
    '1. Employee self-service & approvals (M16) — unlocks leave, OT, and profile requests for faculty/staff.',
    '2. Leave filing & balances (M06) — build on the leave types already in payroll.',
    '3. Effective-dated government rates (M17 / M08) — so old payroll stays correct after rate changes.',
    '4. Onboarding checklist (M04) — extend the documents already on the employee record.',
    '5. Separation & clearance (M12) — resignation, clearance, final pay, COE.',
    '6. Recruitment (M03) — if hire-to-retire is in scope.',
    '7. Complete faculty gaps (M19 / M20) — licenses, qualified subjects, overload approval.',
    '8. Risk modules (M11 / M13 / M14) — discipline, contractors, safety.',
];

foreach ($steps as $step) {
    $section->addListItem($step, 0, null, null, $pTight);
}

// ----- Architecture in simple terms -----
$section->addTitle('Architecture checklist (simple view)', 2);
$section->addText('The blueprint also lists design rules. Here is the status in everyday language:', $p);

$arch = [
    ['One employee record for everyone (including contractors)', 'Partial — employees/faculty yes; contractors as separate workers no.'],
    ['Role-based access (many named roles)', 'Partial — Admin / Staff / Viewer + custom permissions; not all blueprint roles.'],
    ['Approvals with audit trail', 'Partial — logs and some approvals exist; no full request/approval engine.'],
    ['Extra protection for payroll / medical / discipline data', 'Partial — payroll is separate; medical/discipline modules not built.'],
    ['Turn Education vs Business modules on/off', 'Not yet built'],
    ['Keep old rates when laws change (effective dating)', 'Partial — salary history and holidays yes; government tables not versioned.'],
];

$archTable = $section->addTable('ModuleTable');
$archTable->addRow();
$archTable->addCell(4200, $headerCell)->addText('Blueprint idea', $headerFont);
$archTable->addCell(4800, $headerCell)->addText('People360 today', $headerFont);
foreach ($arch as [$idea, $today]) {
    $archTable->addRow();
    $archTable->addCell(4200)->addText($idea, ['size' => 9]);
    $archTable->addCell(4800)->addText($today, ['size' => 9]);
}

// ----- Footer -----
$section->addTextBreak(1);
$section->addText(
    '— End of report —',
    ['size' => 10, 'color' => '94A3B8', 'italic' => true],
    ['alignment' => Jc::CENTER, 'spaceBefore' => 200]
);
$section->addText(
    'Source blueprint: HRIS Software Module Blueprint for Philippine Educational Institutions and Private Businesses. Product assessed: People360 desktop. Technical detail also lives in pulse/docs/hris-blueprint-gap-analysis.md.',
    ['size' => 8, 'color' => '94A3B8'],
    ['alignment' => Jc::CENTER, 'spaceBefore' => 80]
);

$phpWord->save($target, 'Word2007');

echo "Wrote {$target}\n";
