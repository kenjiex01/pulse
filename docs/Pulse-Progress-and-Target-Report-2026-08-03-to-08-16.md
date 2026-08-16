# Weekly Progress Report & Targets

**Progress Period:** August 3 – August 11, 2026  
*(Plus carry-over completions from July 28 – August 1, 2026)*

## I. Pulse — August 3 – August 8, 2026

**Payslip Daily Rate:** Changed faculty/hybrid payslip footer from Hourly Rate to Daily Rate (`hourly × hours per day` from salary setup); applied to on-screen/PDF and Excel.

**Posted batches in reports:** Payroll Register, SSS, PhilHealth, and Pag-IBIG batch pickers now include PROCESSED and POSTED batches (Posted label on options).

**Payroll Register Daily Rate column:** Added Daily Rate beside Rate per hour on the ICCT register layout (preview, Excel, PDF).

**Government ID digits-only on reports:** SSS / PhilHealth / Pag-IBIG contribution reports now export and display ID numbers without hyphens.

**Payroll Register column cleanup:** Adjusted the Payroll Register columns based on instruction — removed unused fields and retained the active ICCT columns, including Daily Rate.

**New campuses:** Seeded Greenhills (`GH`), N. Domingo (`ND`), Washington Residences (`WR`), 108 (`O8`), and 225 6th Floor (`F6`) for campus maintenance and Timelogs DTR.

**Is Above minimum wage earner:** Added salary-level MWE flag on employee master (including hybrid), uploads, and sync for BIR/tax classification.

**BIR Employees’ Tax Withheld report:** Delivered BIR tax withheld reporting using MWE vs taxable placement and TRAIN threshold handling.

**BIR Forms Setup:** Added Payroll submodule for employer TIN/name/address/RDO/ZIP, signatory, ATC, and SMW rates used by BIR forms.

**BIR Form 1601-C:** Implemented official blank-PDF stamp flow; fixed signatory (#27) stamp position to match intended layout.

**BIR Form 2316:** Implemented certificate generation with official blank PDF stamp; options use **payroll year** (YTD sum of all posted batches) with employee selection by year.

**Desktop installer auto-update:** S3-based installer check (`latest.json`), forced update modal (no remind-later), optional every-request check, versioned installer object names, and note that SQLite data is preserved on upgrade.

**BIR Alphalist report:** Ported Alphalist under Posted Batch Reports (Excel) with Schedules **7.1 / 7.3 / 7.4 / 7.5**, year-based posted payroll YTD via BIR 2316 income mapping, schedule multi-select, Output label fix, PhpSpreadsheet 5 Excel generation fix, and Schedule selector UI height fixes.

## I. Pulse — August 10 – August 11, 2026

**Weekly Progress & Targets report:** Produced Markdown + Word progress/targets pack for the sprint window (updated Aug 10, then expanded again Aug 11).

**Master File upload — multi-campus slots:** Template/upload now supports campus assignments **1–5** (primary required; slots 2–5 optional) via config + `EmployeeUploadRowMapper`, with regenerated `employee_upload_template.xlsx`.

**Master File upload cleanup:** Removed JSON collection columns (Family Members, Employment History, Exams, Seminars, Awards, References) from master-file upload and template.

**Employee Credentials tab:** New **Credentials** tab after Access on the employee form — per-employee file list, Add modal (Description + attachment), download, soft delete, cascade on employee soft-delete, and `sys_logs` audit; tab visible on create with “save first” guidance.

**Credential file preview:** Eye icon in Actions opens a preview modal; images/PDF inline; Excel/CSV via PhpSpreadsheet HTML; Word via PhpWord (including legacy `.doc` / MsDoc reader); text preview; audio/video players; desktop-safe blob/`srcdoc` path for Electron.

**LibreOffice Office preview (charts/images):** Complex Word/Excel (charts/OLE) convert to PDF when LibreOffice is available; optional first-use runtime download into app storage for machines without LO; PHP fallback retained.

**Desktop build 0.1.50:** Paired macOS DMG + Windows EXE; uploaded to S3 `payroll_installer/` with `latest.json` → **0.1.50**; hardened multipart S3 upload (longer timeouts + retries).

**Installer download load screen:** Update modal **Download update** now shows the Pulse full-screen loader while the pre-signed download is prepared/started (iframe stream; no blank Electron window).

## I. Pulse — July 28 – August 1, 2026 (also finished last sprint)

**Contribution & payslip reports:** Added PhilHealth and Pag-IBIG contribution reports (Pag-IBIG with Staff/Faculty sections), and Payslip report with staff/faculty layouts plus Excel/PDF one-page-per-employee landscape.

**Posted batch read-only viewing:** Posted payroll batches open as read-only for review.

**Payroll Register ICCT layout:** Per-hour Excel layout unified across preview, Excel, and PDF; Job Order columns mapped to Overtime (OVRT); static dates/yellow highlights cleaned up.

**Hybrid & timekeeping payroll rules:** Hybrid Faculty + Staff computation per calendar category; staff Time-In/Out respects “Use Basic Income as Hourly Rate”; Auto compute Excess as OT; Leaves basic preserved for hybrid staff.

**Shift code & upload hours/days:** Restored duty schedule section; Break Out/In window fields; payroll upload Hours and Days columns; batch Days on incomes/deductions; manual Add Income/Deduction hour/day handling.

**Employee uploads & history:** Employee upload type dropdown (Master File + Employee Salary); Employee Profile bulk upload; Employee History details tab; HR Historical Data report.

**Reports PDF output:** Added PDF as an output option across payroll reports pipeline.

**Desktop stability & speed:** Excel download on desktop without XMLWriter crash; blank-screen / stuck-loader fixes; SQL restore hardening (Windows lock, MySQL dump → SQLite); OPcache/boot-once bootstrap; government table seeder on launch; Mac install helper / notarization path.

---

**Targets Period:** August 11 – August 16, 2026

## I. Pulse

**Payroll reports testing:** Continue end-to-end testing of payroll reports (Payroll Register, Payslip, SSS, PhilHealth, Pag-IBIG, BIR Tax Withheld, BIR 1601-C, BIR 2316, and Alphalist) using posted/year-based scenarios.

**Report adjustments from feedback:** Collect UAT/user feedback on report options, layouts, amounts, and Excel/PDF output, then apply fixes and refinements accordingly.

**BIR / Alphalist feedback pass:** Retest BIR Forms Setup + 1601-C / 2316 / Alphalist after adjustments; confirm schedule classification, YTD totals, and export usability.

**Employee Credentials UAT:** Smoke-test Credentials upload / preview / soft delete on browser and desktop (images, PDF, `.doc`/`.docx`, Excel with charts when LibreOffice is present or after optional runtime download).

**Desktop 0.1.50 adoption & parity:** Verify forced update flow from older installs, installer download load screen, and smoke-test Credentials + recent report changes on the 0.1.50 DMG/EXE; ship a follow-up build if feedback requires it.
