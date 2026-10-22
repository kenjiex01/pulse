# Weekly Progress Report & Targets

**Progress Period:** October 12 – October 18, 2026  
**Targets Period:** October 19 – October 25, 2026  
**Apps covered:** People360 (school payroll & timekeeping desktop app) and Pulse Biometric Collector (campus time clock helper)

---

## In plain words

**Last week** we shipped **People360 through version 1.0.36** with a **staff user guide** (screenshots and payroll workflow order), **payroll batch fixes** (add employees by pay-period category, paging, staff vs faculty hours by category), **loan deductions and history** (one deduction per loan per run, optional half per cutoff, clearer principal and interest on the employee loan form), **timekeeping fixes** (overnight shifts keep the correct time-out, overtime counted in whole minutes), **payslip year-to-date totals**, and a full refresh of **Company Documents** — **nine ICCT memos and letters** with designer preview, bordered offense tables, and email PDF matching the screen. **HR lookup lists** (campuses, positions, departments, and similar) gained **template download and CSV upload**. **Desktop template downloads** on Windows and Mac now save real Excel files to Downloads. **Pulse Collector 1.0.5** fixes **DTR export** so it saves a CSV instead of hanging on “Saving…”.

**This week** campuses should **update to People360 1.0.36**, run **HR tests** on memos (preview, send email, offense tags), **payslip YTD**, **loan deductions**, and **lookup uploads**, and **share the user guide** with payroll and HR staff. Continue **Pulse 1.0.5** rollout and **collector retention/storage** checks. **Approved leave from People360 web into desktop payroll** stays the main build focus when HR is ready to test.

---

## What we finished last week (October 12 – October 18)

### People360 — Staff guide and desktop reliability

**People360 User Guide (Word)**  
English guide with **123 screenshots**: sign-in, Add / Edit / Inactive / Delete pattern, setup order, then employees, timekeeping, payroll, and admin. Reordered around the **payroll workflow** (including employee loans and process-payroll steps).

**Excel templates on desktop**  
Employee upload templates work when the bundled PHP has no XMLWriter. **Download Template** on the desktop app saves **.xlsx or .csv to Downloads** (not HTML or a stuck save dialog), including fixes on Windows.

**Startup and dev**  
Full-screen “Loading…” overlay hides even if CSS fails to load. Dev server auto-start on Mac was fixed for permission issues under Documents.

### People360 — Payroll batch, categories, and salary uploads

**Add employees on a batch**  
List respects **categories chosen on the pay period** (not every employee with the same pay type). **25 per page** with Previous/Next; **Include all eligible** still adds everyone who matches.

**Employee categories**  
New **User Type / category** lookup; payroll can use **Logs** or **Teaching Loads** hours by category instead of a single hardcoded rule.

**Salary template**  
Download can filter by **category**; template includes **employee name** column; upload still matches by employee number.

### People360 — Loans and payslips

**Loan payroll**  
Processing writes **loan payment history** (eye icon on employee loans). Each loan deducts on **one batch only** unless rules allow **half per cutoff** and **1st or 2nd cutoff** choice. **Principal, interest, and payment count** on the loan form follow amortization rules. Deductions show on the batch **Deductions** tab.

**Payslips**  
**Year-to-date figures** under the signature on posted payslips (gross, statutory, net). PDF layout adjusted for email. Payslip PDF password uses **birth date MMDDYYYY** (from earlier 1.0.33 work in this period).

### People360 — Timekeeping and employee maintenance

**Overnight shifts**  
Time-out after midnight stays on the **same shift day** (fixes false long undertime).

**Overtime filing**  
Filed OT in the **same minute** as a punch is accepted; OT duration uses **whole minutes** (not seconds).

**Position and designation**  
Names from employee upload are **added to maintenance** so dropdowns stop showing “(Legacy)” for valid titles.

### People360 — Company Documents (HR memos)

**ICCT templates**  
Nine templates: **Absences & Tardiness**, **Attendance Pattern**, **Company Property**, **PhilHealth Number**, and **Letters 1–5** (NTE through dismissal). Same letterhead and merge tags as the absences memo; **Nature of offense** and **Disciplinary action** use a **bordered table** filled from the linked offense.

**Designer and email**  
Preview and emailed PDF use the **designer layout** (not a separate hardcoded memo page). **Table** element in the document designer. On app update, **old custom templates are replaced** by the current nine (revision 7 seeder).

**HR lookup uploads**  
Upload button on campuses, designations, positions, ranks, employment types, departments, colleges, programs, and document types — **download template, upload CSV**, update matching rows.

### People360 — Desktop releases (12–18 October)

Stepped releases **1.0.25 through 1.0.36** (paired **macOS and Windows**, automatic updates). Latest **1.0.36** ships company documents, lookup uploads, loan form fixes, and payslip YTD.

### Pulse Biometric Collector — last week

**Pulse 1.0.5**  
**Download DTR** saves a **CSV to Downloads** and closes the saving overlay (no stuck screen or print preview on Windows).

---

## What we plan to do this week (October 19 – October 25)

### People360 — rollout and HR testing

1. **Roll out People360 1.0.36** on active campuses (auto-update or installer).  
2. **Company Documents:** HR previews each memo, sends test email, confirms offense table and merge tags; confirm old templates were replaced after update.  
3. **Payslips:** spot-check **year-to-date** on a posted batch; confirm PDF password with HR.  
4. **Loans:** verify deduction once per loan, payment history, and half/cutoff settings on a sample employee.  
5. **HR lookup uploads:** download template and upload one row on a non-production or test campus per module.  
6. **Payroll batch:** add employees with **category-scoped** pay period; confirm paging and **Include all**.  
7. **Distribute People360 User Guide** to campus HR/payroll leads; collect gaps for a guide update.  
8. **Fix campus-reported issues** from 1.0.30–1.0.36 (memos, payslip, loans, uploads).

### People360 — approved leave from web into payroll

9. **Continue build/test:** approved filed leave from People360 web into desktop payroll; mark web forms used after post.  
10. **HR test path** when ready: approve on web → pull to desktop → batch → post.

### Pulse Biometric Collector

11. **Upgrade campuses to Pulse 1.0.5** (auto-update or installer).  
12. **Confirm DTR download** produces CSV in Downloads on Windows and Mac.  
13. **Keep 1.0.3 retention/storage practices:** 2-month default, device storage column, plan before terminals fill.  
14. **Schedule installs** on campuses still pending.

---

*Report date: 19 October 2026 · Technical detail: `pulse/docs/changelog/` and `biometric-collector/docs/changelog/`.*
