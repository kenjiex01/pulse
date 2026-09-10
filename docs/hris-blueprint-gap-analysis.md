# People360 vs HRIS Software Module Blueprint

**Source:** [HRIS Software Modules Blueprint (PH)](HRIS-Software-Modules-Blueprint-PH.pdf) — Philippine educational institutions and private businesses (14 pages).  

**Products assessed:**
- **Desktop** — People360 app (`pulse/`)
- **Web** — Skolaris People360 shell (`skolaris-fe` + `skolaris-be`, routes under `/people360`)

**Date:** 27 August 2026 (updated **4 September 2026** — §10 delivery timeline; M06 **Web Ready / Desktop Partial**; desktop M01, M05 marked Existing).

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
| Full match | **4** | **M01**, **M05**, **M07**, **M16** (M01/M07 desktop only; M16 web only) |
| Partial | **9** | M02, M04, **M06**, M08, M15, M17, M18, M19, M20 |
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

| ID | Module | Priority | Target date | Desktop | Web | Combined | Notes |
|---|---|---|---|---|---|---|---|
| M01 | Organization & HR Configuration | MVP | **Done** | **Existing** | **N/A** | **Existing** | **Desktop Ready:** campuses, colleges, programs, depts, positions, designations, ranks, employment types, calendar, holidays, users/roles. **Web N/A** — org masters are desktop-only; org chart deferred. Web admin (users/roles, API keys) is access ops, not M01 org configuration UI. |
| M02 | Worker / Employee Master Data | MVP | Dec 2026 | **Existing** | **Partial** | **Partial** | Desktop: full employee CRUD + salary/loans/credentials + **bank accounts**. Web: browse/edit restored local employees + ESS profile edit + desktop sync APIs. |
| M03 | Recruitment & Applicant Tracking | MVP | Dec 2026 | **Pending** | **Pending** | **Pending** | |
| M04 | Onboarding & Employment Documents | MVP | *Deferred* | **Partial** | **Partial** | **Partial** | Desktop: document types + credentials on record. Web: ESS upload + HR document review queue + soft onboarding gate. No full onboarding case / e-contract. |
| M05 | Time, Attendance & Scheduling | MVP | **14 Sep 2026** | **Existing** | **Partial** | **Existing** | Desktop ready: policy, shifts, holidays, time log upload, biometric S3 pull, HR attendance edit, payroll OT, faculty load hours. W2b: apply synced forms to batch. Live clock-in, QR punch, OB, ESS corrections, and attendance approval workflow are **web** (`iskolaris-fe` / `iskolaris-be`), not desktop. |
| M06 | Leave & Absence Management | MVP | Oct 2026 | **Partial** | **Existing** | **Partial** | **Web Ready:** leave filing, balances, accrual, manager approval on ESS. **Desktop Partial:** leave types, policy mapping, attendance-derived leave in payroll, manual/upload in batch — not full blueprint scope yet. |
| M07 | Payroll & Statutory Pay | MVP | **Done** | **Existing** | **N/A** | **Existing** | **Desktop Ready:** compute/post, payslips, BIR, register, payslip email. **Web N/A** — live payroll is desktop-only; web has SQL backup restore (ops), not M07 payroll UI. |
| M08 | Benefits & Government Contributions | MVP | Nov–Dec 2026 | **Partial** | **Pending** | **Partial** | Desktop: SSS/PhilHealth/Pag-IBIG tables + reports. Web: none. |
| M09 | Performance Management | Phase 2 | Dec 2026 | **Pending** | **Pending** | **Pending** | |
| M10 | Learning & Development | Phase 2 | Dec 2026 | **Pending** | **Pending** | **Pending** | |
| M11 | Employee Relations, Grievance & Discipline | MVP | Dec 2026 | **Pending** | **Pending** | **Pending** | |
| M12 | Separation, Clearance & Retirement | MVP | Nov 2026 | **Pending** | **Pending** | **Pending** | Desktop: inactive / soft-delete only. |
| M13 | Contractor & Outsourced Workforce | MVP | Dec 2026 | **Pending** | **Pending** | **Pending** | |
| M14 | Occupational Safety & Health | MVP | Dec 2026 | **Pending** | **Pending** | **Pending** | |
| M15 | Data Privacy, Documents & Records Retention | MVP | Dec 2026 | **Partial** | **Partial** | **Partial** | Desktop: `sys_logs`, soft delete, credential files. Web: privacy policy + consent gate, Pulse audit trail. |
| M16 | Self-Service, Requests & Approvals | MVP | **Done** | **N/A** | **Existing** | **Existing** | **Web Ready:** ESS login, dashboard, profile, docs, attendance, Job Order/WRF + approvals, HR request queue. **Desktop N/A** — self-service lives on web only. W2 (Sep): sync approved forms → payroll batch (M05/M07 integration, not M16 desktop). |
| M17 | HR Compliance & Legal Rules Engine | MVP | Nov–Dec 2026 | **Partial** | **Pending** | **Partial** | Desktop: rate groups, govt tables, holidays, timekeeping policy. |
| M18 | HR Analytics, Dashboards & Audit | Phase 2 | Dec 2026 | **Partial** | **Partial** | **Partial** | Desktop: dashboard + operational reports. Web: checker analytics + audit list. |
| M19 | Faculty & Academic Personnel | MVP (schools) | Dec 2026 | **Partial** | **Partial** | **Partial** | Desktop: faculty/staff/admin/hybrid + rank. Web: uses same people model via local/ESS; no PRC module. |
| M20 | Faculty Load, Schedule & Overload | MVP (schools) | Dec 2026 | **Partial** | **Partial** | **Partial** | Desktop: Skolaris pull + upload + faculty pay. Web: uploaded faculty-load PDF module + loading attendance. |
| M21 | Faculty Evaluation, Rank & Promotion | Phase 2 | Dec 2026 | **Pending** | **Pending** | **Pending** | |
| M22 | Faculty Development, Research & Credentials | Phase 2 | Dec 2026 | **Pending** | **Pending** | **Pending** | Credentials = file storage only. |
| M23 | Student-Safeguarding / Faculty Conduct | Phase 2 | Dec 2026 | **Pending** | **Pending** | **Pending** | |
| M24 | Sales / Commission & Incentive | Optional | Optional | **Pending** | **Pending** | **Pending** | |
| M25 | Shift, Field & Branch Workforce | Optional | Optional | **Pending** | **Pending** | **Pending** | Shifts live under M05 (desktop), not a field ops module. |
| M26 | Succession, Talent & Workforce Planning | Phase 3 | Dec 2026 | **Pending** | **Pending** | **Pending** | |
| M27 | AI Assistance & Automation | Phase 3 | Dec 2026 | **Pending** | **Pending** | **Pending** | |
| M28 | SDG / ESG Workforce Reporting | Phase 3 | Dec 2026 | **Pending** | **Pending** | **Pending** | |

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

### M01 — Organization & HR Configuration (Desktop Ready · Web N/A)

**Existing (desktop):** Campuses (min wage), colleges, programs, departments, positions, designations, ranks, employment types, payroll calendar, holiday catalog, role + module permissions, users, company documents, BIR company TIN/name.  
**Web:** **Not applicable** — org masters and HR configuration UI are desktop-only. Web has People360 users/roles and API keys (access admin), not M01 org setup modules.  
**Deferred:** Org chart (do not build yet). Approval matrices are a separate web feature, not M01 org configuration.

### M02 — Worker / Employee Master (Desktop Ready · Web Partial)

**Existing (desktop):** Full profile, multi-campus, employment (faculty/staff/admin/hybrid), salary history, loans, credentials, bulk upload, Skolaris Approve/sync, confidential + compliance status, **bank account (payroll disbursement)**.  
**Partial (web):** Restored local employees, ESS profile edit, desktop sync APIs — not full master parity.

### M04 — Onboarding & documents (Partial)

**Existing:** HR Document Types; upload/preview/download on employee.  
**Pending:** Onboarding case, e-contract, acknowledgments (web soft gate helps but is not a case workflow).

### M05 — Time, Attendance & Scheduling (Existing on desktop)

**Existing (desktop):** Timekeeping policy, shift codes, time capture formats, holidays, time log upload, biometric S3 pull, employee timekeeping setup (rest days), attendance view/edit, calendar, faculty load hours, manual OT for payroll.  
**Out of desktop scope:** Live clock-in, QR/mobile punch, official business, employee-initiated time corrections, full attendance approval workflow — to be built on **web** (`iskolaris-fe` / `iskolaris-be`); not this desktop owner.  
**Web still Partial:** Attendance checker, load-based timekeeping, ESS read-only attendance; above gaps pending on web.

### M06 — Leave & Absence (Web Ready · Desktop Partial)

**Existing (web):** Leave filing, balances, accrual, manager approval on ESS (`iskolaris-fe` / `iskolaris-be`).  
**Partial (desktop):** Leave types (maintenance table), timekeeping policy leave mappings (tardiness/undertime/AWOL), attendance-derived leave lines in payroll, manual/upload leave in payroll batch, employee `is_leave` setup — usable but not marked Ready on desktop scope.

### M07 — Payroll & Statutory Pay (Desktop Ready · Web N/A)

**Existing (desktop):** Rate groups, incomes/deductions, compute/post, payslips, register, BIR 1601-C / 2316 / Alphalist, payslip send by email, loans integration.  
**Web:** **Not applicable** for live payroll — backup browse/restore only (ops), not payroll compute/post.

### M08 — Benefits & Government Contributions (Partial)

**Existing:** SSS/PhilHealth/Pag-IBIG tables + reports on desktop.  
**Pending:** Effective-dated contrib tables, HMO; web has none.

### M15 / M17 / M18 (Partial)

As in prior analysis — audit + soft delete; rate/govt tables; operational reports. Web adds consent + Pulse audit list.

### M19 / M20 — Education (Partial)

Faculty/staff/admin/hybrid, rank, teaching load + faculty payroll (desktop); web adds faculty-load PDF module and loading attendance.

### M16 — Self-Service, Requests & Approvals (Web Ready · Desktop N/A)

**Existing (web):** Login/OTP, employee dashboard, profile edit, own attendance + loading attendance, My Requests (Job Order/WRF), approval workflow (draft → approved → processed → posted), manager approvals, HR admin request queue.  
**Desktop:** **Not applicable** — ESS is web-only (`skolaris-fe` / `iskolaris-be`). Desktop integrates via W2 sync of approved forms into payroll (M05/M07), not an M16 UI module.

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
| 1 Foundation | M01, M02, RBAC, audit, documents | **M01 desktop Ready (web N/A).** M02 Existing on desktop. Web consent Partial |
| 2 Daily HR | M03, M04, M05, M06, M16 | **M05 Existing on desktop.** **M06 + M16 Ready on web**; desktop leave/payroll lines Partial |
| 3 Payroll & compliance | M07, M08, M17 | **M07 Existing on desktop** (web N/A). M08/M17 partial |
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

1. **M05 / M07 integration (W2)** — Sync approved web forms (OT, Job Order/WRF) into desktop payroll batch  
2. **M17 / M08** — Effective-date + version government tables (desktop)  
3. **M12** — Separation, clearance, final pay  
4. **M03** — Recruitment (if hire-to-retire is in scope)  
5. **M19 / M20 completion** — Licenses, qualified subjects, overload approval  
6. **M11 / M13 / M14** — Discipline, contractors, OSH  

**Deferred (not in current plan):** **M04** Onboarding checklist / e-contract — existing document upload + review on web is enough for now.

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

## 10. Delivery timeline

Planning dates assume **one desktop + web squad**, campus UAT with HR/payroll, and no major blockers (AWS SES verification, API keys, campus network).

**Status key:** **Done** · **In progress** · **Planned** · **Blocked** (needs external action)

### Last week completed — week of 1–7 September 2026

| # | Deliverable | Status | Notes |
|---|---|---|---|
| **W1** | **Payslip send by email** | **Done** | Payroll Transaction → Payslip tab; PDF attach; AWS SES API (`noreply@skolaris.icct.edu.ph`, ap-southeast-2). Bulk send from posted batch with confirm + progress bar. |

Also shipped: **M02 bank accounts** on employee master (create/edit/upload).

### Sprint target — week of 8–14 September 2026

| # | Deliverable | Blueprint | Product | Target dates | Depends on | Notes |
|---|---|---|---|---|---|---|
| **W2a** | **Pull approved ESS forms from People360 web** | M05 · M07 · §9 Desktop↔Web | **Desktop** + **Web BE** | **8–11 Sep** | Pulse API key with `employee_requests` scope | BE exposes `GET /pulse-api/v1/employee-requests/sync`. Desktop: extend `SkolarisApiService`, local store + “Sync from People360 web” action. |
| **W2b** | **Apply synced forms to payroll batch** | M05 · M07 | **Desktop** | **11–14 Sep** | W2a | Map `amend_attendance` → OT / attendance lines; `job_order` / WRF → batch adjustments. Mark rows `posted` on web after payroll post. |

**This-week outcome (plain words):** Payroll can **import approved web forms** (OT amendment, Job Order/WRF, etc.) into the desktop batch instead of re-keying.

**Out of scope for 8–14 Sep:** Recruitment (M03), onboarding workflow (M04 — deferred), new HR form types beyond what Skolaris already publishes.

---

### September 2026 (after next week)

| Window | Focus | Modules | Outcome |
|---|---|---|---|
| **15–21 Sep** | Payslip + forms hardening | M07, M05 | Fix UAT findings; idempotent sync; audit log per imported form; desktop installer **1.0.x** if needed |
| **22–28 Sep** | Leave ↔ payroll tie-in | M06, M05 | Approved web leave visible when processing desktop batch; desktop leave lines hardening |
| **29 Sep – 5 Oct** | Forms + leave polish | M06, M05 | UAT fixes; approved leave import to batch |

---

### Q4 2026 — MVP gaps (blueprint §5 pending)

| Priority | Module | Target window | Notes |
|---|---|---|---|
| MVP | **M12** Separation / clearance | Nov 2026 | Clearance workflow + final pay hooks |
| MVP | **M17 / M08** Effective-dated govt tables | Nov–Dec 2026 | Version SSS / PhilHealth / Pag-IBIG by effectivity date |
| MVP | **M03** Recruitment | Dec 2026 | If hire-to-retire is in scope |
| MVP | **M11 / M13 / M14** Discipline, contractors, OSH | Oct–Dec 2026 | Risk modules |
| Phase 2 | M09, M10, M21–M23 | Dec 2026 | Performance, L&D, faculty eval |
| Phase 3 | M26–M28 | Dec 2026 | Succession, AI advisory, ESG (stretch backlog) |
| *Deferred* | *M04 Onboarding* | *TBD* | *Not in current plan — ESS document upload/review stays as-is* |

---

### Combined roadmap vs blueprint stages (high level)

| Blueprint stage | Target “substantially complete” | Key blockers removed by |
|---|---|---|
| 1 Foundation | **Done** (desktop M01) | — |
| 2 Daily HR | **Dec 2026** | M05/M07 forms bridge (**Sep 2026** W2); M06 + M16 web **Ready**; desktop leave/payroll Partial |
| 3 Payroll & compliance | **Dec 2026** | M07 desktop **Ready**; M17 effective dates (**Nov 2026**) |
| 4 Risk & outsourcing | **Dec 2026** | M11–M14 greenfield |
| 5 Education | **Dec 2026** | M19/M20 completion; M21+ phase 2 stretch |
| 6–7 Intelligence / advanced | **Dec 2026** | Reporting first; AI/ESG if capacity |

---

### External checklist (week of 8–14 Sep)

| Item | Owner | Needed for |
|---|---|---|
| Pulse API key: `employee_requests` **list + sync** | Skolaris admin | W2a |
| HR: sample **approved** OT + Job Order rows in staging | HR | W2b UAT |
| Campus PCs on People360 **1.0.2+** with outbound HTTPS | Campus IT | W2a |

---

## Document control

| Item | Value |
|---|---|
| Blueprint | `docs/HRIS-Software-Modules-Blueprint-PH.pdf` — planning only |
| Desktop assessed | People360 (`pulse/`) |
| Web assessed | Skolaris People360 (`skolaris-fe` + `skolaris-be`, `/people360`) |
| Friendly report | `People360-HRIS-Blueprint-Status-2026-08-27.docx` (+ `.html`) |
| Progress dashboard | `People360-HRIS-Blueprint-Progress.html` (+ `.docx` via `generate-hris-blueprint-progress-docx.py`; dated copy `People360-HRIS-Blueprint-Progress-2026-09-04.docx`) |
| Last change | 4 Sep 2026 — M01 **Desktop Ready / Web N/A**; M07 **Desktop Ready / Web N/A**; M16 **Web Ready / Desktop N/A**; M06 web Ready; M02 desktop Ready. |
| Next update | After W2 sprint (14 Sep 2026) or when a §2 module status changes |
