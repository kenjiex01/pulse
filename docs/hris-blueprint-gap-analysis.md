# People360 vs HRIS Software Module Blueprint

**Source:** HRIS Software Module Blueprint for Philippine Educational Institutions and Private Businesses (14 pages).  
**Products assessed:**
- **Desktop** — People360 app (`pulse/`)
- **Web** — Skolaris People360 shell (`skolaris-fe` + `skolaris-be`, routes under `/people360`)

**Date:** 27 August 2026 (updated same day to include web).

This is a gap analysis, not a legal review. Statutory rates and rules must still be validated before production use.

## Status legend

| Status | Meaning |
|---|---|
| **Existing** | Usable for daily operations on that product (may still need polish). |
| **Partial** | Some of the blueprint scope is built; major gaps remain. |
| **Pending** | Not built as a module / workflow on that product. |

**Combined** = best of Desktop + Web for that blueprint module (what the People360 product family can do overall).

## Snapshot (Combined — Desktop + Web)

| Status | Count | IDs |
|---|---|---|
| Full match | **0** | — |
| Partial | **13** | M01, M02, M04, M05, M06, M07, M08, M15, **M16**, M17, M18, M19, M20 |
| Pending | **15** | M03, M09, M10, M11, M12, M13, M14, M21, M22, M23, M24, M25, M26, M27, M28 |

**Where People360 is today**
- **Desktop:** Stage 1 foundation + Stage 3 payroll + EDU Stage 5 faculty load — without recruitment, leave self-service, contractors, OSH, or discipline.
- **Web:** Employee self-service (ESS), document review, attendance checker / timekeeping ops, Job Order (WRF) requests & approvals, faculty-load PDF upload, biometric/payroll backup restore — **no live payroll compute**.
- **Combined:** Closest to blueprint **Stage 3 payroll (desktop)** plus **Stage 2 partial ESS (web)** — still not a full hire-to-retire HRIS.

---

## 1. Architecture (blueprint §1)

| Blueprint rule | Desktop | Web | Combined |
|---|---|---|---|
| One worker master for employees, faculty, consultants, contractors | **Partial** — one employee master; no consultant/contractor type | **Partial** — local restored employees + ESS profile; same worker model | **Partial** |
| RBAC (named blueprint roles) | **Partial** — custom roles + module CRUD; seeded Admin/Staff/Viewer | **Partial** — Pulse roles: Admin, HR Staff, Checker, Employee | **Partial** |
| Workflow approvals + audit | **Partial** — `sys_logs`; attendance/payroll post | **Partial** — Job Order approval trail; Pulse audit trail; privacy consent | **Partial** |
| Stricter access for payroll / medical / discipline | **Partial** — payroll module + confidential flag | **Partial** — no live payroll UI; module-gated ESS/ops | **Partial** |
| Enable/disable modules by org type (EDU / BUS) | **Pending** | **Pending** | **Pending** |
| Contractor distinct from employee HR | **Pending** | **Pending** | **Pending** |
| Effective-dated rates, contrib, holidays, policies | **Partial** — salary history + holidays-by-year | **Pending** — consumes restored/desktop data | **Partial** |

---

## 2. Module map (M01–M28) — Desktop · Web · Combined

| ID | Module | Priority | Desktop | Web | Combined | Notes |
|---|---|---|---|---|---|---|
| M01 | Organization & HR Configuration | MVP | **Partial** | **Partial** | **Partial** | Desktop: campuses, depts, positions, calendar, holidays. Web: users/roles + API keys only (org masters live on desktop / shared SIS). |
| M02 | Worker / Employee Master Data | MVP | **Partial** | **Partial** | **Partial** | Desktop: full employee CRUD + salary/loans/credentials. Web: browse/edit restored local employees + ESS profile edit + desktop sync APIs. |
| M03 | Recruitment & Applicant Tracking | MVP | **Pending** | **Pending** | **Pending** | |
| M04 | Onboarding & Employment Documents | MVP | **Partial** | **Partial** | **Partial** | Desktop: document types + credentials on record. Web: ESS upload + HR document review queue + soft onboarding gate. No full onboarding case / e-contract. |
| M05 | Time, Attendance & Scheduling | MVP | **Partial** | **Partial** | **Partial** | Desktop: policy, shifts, import, OT for payroll. Web: attendance checker, timekeeping (load-based), ESS biometric/loading attendance (read), biometric S3 restore. No live clock-in. |
| M06 | Leave & Absence Management | MVP | **Partial** | **Pending** | **Partial** | Desktop: leave types for payroll only. Web: no leave filing. |
| M07 | Payroll & Statutory Pay | MVP | **Partial** | **Pending** | **Partial** | Desktop: compute/post, payslips, BIR. Web: payroll SQL backup browse/restore only. |
| M08 | Benefits & Government Contributions | MVP | **Partial** | **Pending** | **Partial** | Desktop: SSS/PhilHealth/Pag-IBIG tables + reports. Web: none. |
| M09 | Performance Management | Phase 2 | **Pending** | **Pending** | **Pending** | |
| M10 | Learning & Development | Phase 2 | **Pending** | **Pending** | **Pending** | |
| M11 | Employee Relations, Grievance & Discipline | MVP | **Pending** | **Pending** | **Pending** | |
| M12 | Separation, Clearance & Retirement | MVP | **Pending** | **Pending** | **Pending** | Desktop: inactive / soft-delete only. |
| M13 | Contractor & Outsourced Workforce | MVP | **Pending** | **Pending** | **Pending** | |
| M14 | Occupational Safety & Health | MVP | **Pending** | **Pending** | **Pending** | |
| M15 | Data Privacy, Documents & Records Retention | MVP | **Partial** | **Partial** | **Partial** | Desktop: `sys_logs`, soft delete, credential files. Web: privacy policy + consent gate, Pulse audit trail. |
| M16 | Self-Service, Requests & Approvals | MVP | **Pending** | **Partial** | **Partial** | **Web has ESS** (dashboard, profile, docs, attendance, Job Order/WRF + approvals). Desktop is HR-only. Leave ESS still missing on both. |
| M17 | HR Compliance & Legal Rules Engine | MVP | **Partial** | **Pending** | **Partial** | Desktop: rate groups, govt tables, holidays, timekeeping policy. |
| M18 | HR Analytics, Dashboards & Audit | Phase 2 | **Partial** | **Partial** | **Partial** | Desktop: dashboard + operational reports. Web: checker analytics + audit list. |
| M19 | Faculty & Academic Personnel | MVP (schools) | **Partial** | **Partial** | **Partial** | Desktop: faculty/staff/admin/hybrid + rank. Web: uses same people model via local/ESS; no PRC module. |
| M20 | Faculty Load, Schedule & Overload | MVP (schools) | **Partial** | **Partial** | **Partial** | Desktop: Skolaris pull + upload + faculty pay. Web: uploaded faculty-load PDF module + loading attendance. |
| M21 | Faculty Evaluation, Rank & Promotion | Phase 2 | **Pending** | **Pending** | **Pending** | |
| M22 | Faculty Development, Research & Credentials | Phase 2 | **Pending** | **Pending** | **Pending** | Credentials = file storage only. |
| M23 | Student-Safeguarding / Faculty Conduct | Phase 2 | **Pending** | **Pending** | **Pending** | |
| M24 | Sales / Commission & Incentive | Optional | **Pending** | **Pending** | **Pending** | |
| M25 | Shift, Field & Branch Workforce | Optional | **Pending** | **Pending** | **Pending** | Shifts live under M05 (desktop), not a field ops module. |
| M26 | Succession, Talent & Workforce Planning | Phase 3 | **Pending** | **Pending** | **Pending** | |
| M27 | AI Assistance & Automation | Phase 3 | **Pending** | **Pending** | **Pending** | |
| M28 | SDG / ESG Workforce Reporting | Phase 3 | **Pending** | **Pending** | **Pending** | |

---

## 3. Web People360 — what already exists (`skolaris-fe` / `skolaris-be`)

Canonical UI brand: **People360** (`/people360/*`). Code and APIs still use **Pulse** names.

### Strongest web areas

| Area | Existing on web |
|---|---|
| **M16 ESS** | Login / OTP registration; employee dashboard; profile view/edit; own attendance + loading attendance; My Requests (Job Order / WRF) with draft → approval → processed → posted; request approvals; HR admin request queue |
| **M04 Documents** | ESS document upload / N/A; HR document review (verify, remap type, approve/reject N/A) |
| **M05 Time ops** | Timekeeping (load-based); Attendance Checker (campus daily Present/Absent/Late, offline queue); biometric log S3 browse/restore |
| **M02 Local employees** | `/people360/local-data/employees` browse/detail/edit restored desktop JSON; desktop sync APIs (`pulse-api/v1/employees`, local-employee-updates) |
| **M20 Faculty load** | Uploaded faculty loading PDFs (upload/parse/CRUD) — People360-only |
| **M15 Privacy / audit** | Privacy policy + required consent gate; Pulse audit trail |
| **M01 Access** | People360 users & roles; desktop API key management |
| **Ops / restore** | Payroll SQL backups browse/restore; restored data tables (read-only) |

### Not on web (desktop-only or nowhere)

- Live payroll compute, payslips, BIR, SSS/PhilHealth/Pag-IBIG remittance UI
- Leave filing / balances
- Recruitment, discipline, clearance, contractors, OSH
- Org masters (campuses, departments, positions, holidays) as People360 web modules
- Live employee clock-in / QR punch

### Key web routes (FE)

**Ops:** `/people360`, `user-management`, `timekeeping`, `attendance-checker`, `audit-trail`, `uploaded-faculty-loading`, `employee-requests`, `biometric-logs`, `payroll-backups`, `data-tables`, `local-data/employees`, `document-review`, `api-management`

**ESS:** `/people360/employee/{dashboard,profile,documents,attendance,loading-attendance,requests,request-approvals}`

### Key web APIs (BE)

| Prefix | Audience |
|---|---|
| `pulse-auth` | Employee registration / login |
| `pulse-employee-portal/*` | ESS me / documents / attendances |
| `pulse-employee-requests/*` | Job Order / WRF |
| `pulse-local-employees/*`, `pulse-document-review/*`, `pulse-data-tables/*` | Restored local data |
| `pulse-biometric-logs/*`, `pulse-payroll-backups/*` | S3 backups |
| `pulse-uploaded-faculty-loading/*` | Faculty PDF loads |
| `pulse-user-management/*`, `pulse-api-keys/*` | Admin |
| `pulse-api/v1/*` | Desktop API-key clients (employees, timekeeping, attendance-checker, local updates) |
| `employees/timekeeping/*` | Web timekeeping / checker |

---

## 4. Desktop People360 — what already exists (`pulse/`)

### M01 — Organization & HR Configuration (Partial)

**Existing:** Campuses (min wage), colleges, programs, departments, positions, designations, ranks, employment types, payroll calendar, holiday catalog, role + module permissions, users.  
**Pending:** Job grades, cost centers, org chart, approval matrices, EDU vs BUS packaging.

### M02 — Worker / Employee Master (Partial)

**Existing:** Full profile, multi-campus, employment (faculty/staff/admin/hybrid), salary history, loans, credentials, bulk upload, Skolaris Approve/sync, confidential + compliance status.  
**Pending:** Dependents as records, bank accounts, consultant/contractor worker, ESS profile (that lives on **web**).

### M04 — Onboarding & documents (Partial)

**Existing:** HR Document Types; upload/preview/download on employee.  
**Pending:** Onboarding case, e-contract, acknowledgments (web soft gate helps but is not a case workflow).

### M05 — Time, Attendance & Scheduling (Partial)

**Existing:** Timekeeping policy, shifts, holidays, time log upload, biometric S3 pull, attendance view/edit/approve, OT for payroll, faculty load hours.  
**Pending:** Live clock-in, QR punch, official business, employee-initiated correction (web has checker + read-only ESS attendance).

### M06 — Leave & Absence (Partial)

**Existing:** Leave types + payroll/policy mapping.  
**Pending:** Filing, balances, accrual, manager approval (also **Pending** on web).

### M07 / M08 — Payroll & benefits (Partial — strongest desktop)

**Existing:** Rate groups, incomes/deductions, compute/post, payslips, register, BIR 1601-C / 2316 / Alphalist, SSS/PhilHealth/Pag-IBIG tables + reports, loans.  
**Pending:** Maker-checker, bank/GL file, final-pay workflow, dedicated 13th-month run, effective-dated contrib tables, HMO.

### M15 / M17 / M18 (Partial)

As in prior analysis — audit + soft delete; rate/govt tables; operational reports. Web adds consent + Pulse audit list.

### M19 / M20 — Education (Partial)

Faculty/staff/admin/hybrid, rank, teaching load + faculty payroll (desktop); web adds faculty-load PDF module and loading attendance.

---

## 5. Pending modules (nothing on Desktop or Web)

| ID | Module | Priority | Why it matters now |
|---|---|---|---|
| M03 | Recruitment & ATS | MVP | Hire-to-retire starts here. |
| M09 | Performance | Phase 2 | Can wait. |
| M10 | L&D | Phase 2 | Can wait. |
| M11 | Grievance & discipline | MVP | High-risk; not started. |
| M12 | Separation & clearance | MVP | Only inactive/soft-delete. |
| M13 | Contractors / outsource | MVP | Blueprint covers security/janitorial. |
| M14 | OSH | MVP | Not started. |
| M21 | Faculty eval / promotion | Phase 2 | Rank lookup only. |
| M22 | Faculty development / research | Phase 2 | |
| M23 | Student-safeguarding link | Phase 2 | Needs SIS case system. |
| M24 | Sales commission | Optional | Out of school MVP. |
| M25 | Field / branch workforce | Optional | Shifts under M05 only. |
| M26 | Succession | Phase 3 | |
| M27 | AI | Phase 3 | |
| M28 | SDG / ESG | Phase 3 | |

*(M16 is no longer in this list — Partial via web ESS.)*

---

## 6. Blueprint roadmap vs People360 (Combined)

| Blueprint stage | Modules | Combined status |
|---|---|---|
| 1 Foundation | M01, M02, RBAC, audit, documents | **Mostly there** (desktop org + employee; web users/roles + docs + consent) |
| 2 Daily HR | M03, M04, M05, M06, M16 | **M05 + M16 partial (web ESS).** Recruitment, leave filing, full onboarding = pending |
| 3 Payroll & compliance | M07, M08, M17 | **Strong on desktop.** Web = backups only |
| 4 Risk & outsourcing | M11–M15 | **M15 partial.** Rest pending |
| 5 Education | M19, M20, then M21–M23 | **M19/M20 partial** (desktop pay + web load PDFs) |
| 6 Intelligence | M09, M10, M18, M26 | Reports / audit only |
| 7 Advanced | M27, M28 | Pending |

---

## 7. Developer non-negotiables (blueprint §6)

| Rule | Desktop | Web | Combined |
|---|---|---|---|
| Audit who / what / when | **Partial** — `sys_logs` | **Partial** — Pulse audit trail | **Partial** |
| Effective-dated wages / contrib / holidays | **Partial** | **Pending** | **Partial** |
| Never delete finalized payroll | **Partial** — post/unpost | N/A (no compute) | **Partial** |
| Raw logs immutable vs approved attendance | **Partial** | **Partial** — checker statuses | **Partial** |
| Maker-checker for high-risk | **Pending** | **Partial** — Job Order approvals only | **Partial** |
| Permissions by sensitivity | **Partial** | **Partial** — module codes | **Partial** |
| Retention + legal hold | **Partial** — soft delete | **Partial** — consent only | **Partial** |
| Contractor IDs ≠ employees | **Pending** | **Pending** | **Pending** |
| Configurable statutory rules | **Partial** | **Pending** | **Partial** |
| AI advisory only | **N/A** | **N/A** | **N/A** |

---

## 8. Suggested build order (from Combined position)

1. **M06** — Leave filing, balances, accrual (web ESS shell exists; desktop has leave types)  
2. **M16 completion** — Extend web ESS beyond Job Order (OT corrections, certificates, leave) + optional desktop parity  
3. **M17 / M08** — Effective-date + version government tables (desktop)  
4. **M04** — Real onboarding checklist / acknowledgments (extend web soft gate + desktop credentials)  
5. **M12** — Separation, clearance, final pay  
6. **M03** — Recruitment (if hire-to-retire is in scope)  
7. **M19 / M20 completion** — Licenses, qualified subjects, overload approval  
8. **M11 / M13 / M14** — Discipline, contractors, OSH  

---

## 9. Integrations (blueprint §8)

| Integration | Desktop | Web | Combined |
|---|---|---|---|
| Biometric / access | **Partial** — S3 pull | **Partial** — S3 browse/restore + ESS attendance read | **Partial** |
| Bank / payment file | **Pending** | **Pending** | **Pending** |
| BIR / government files | **Existing** (generate) | **Pending** | **Existing** |
| SSS / PhilHealth / Pag-IBIG | **Partial** — tables + reports | **Pending** | **Partial** |
| Email / SMS / push | **Pending** (desktop mail often log) | **Partial** — OTP / mail for auth | **Partial** |
| E-signature | **Pending** | **Partial** — WRF signatures | **Partial** |
| SIS / Registrar / LMS | **Partial** — Skolaris load + Approve | **Partial** — same ecosystem; faculty-load PDFs | **Partial** |
| Finance / ERP | **Pending** | **Pending** | **Pending** |
| Document storage | **Partial** — local credentials | **Partial** — ESS + review APIs | **Partial** |
| Desktop ↔ Web sync | **Partial** — `pulse-api/v1` | **Partial** — employees, local updates, timekeeping | **Partial** |

---

## Document control

| Item | Value |
|---|---|
| Blueprint | HRIS Software Module Blueprint (PH) — planning only |
| Desktop assessed | People360 (`pulse/`) |
| Web assessed | Skolaris People360 (`skolaris-fe` + `skolaris-be`, `/people360`) |
| Friendly report | `People360-HRIS-Blueprint-Status-2026-08-27.docx` (+ `.html`) |
| Next update | After each module that closes a row in §2 |
