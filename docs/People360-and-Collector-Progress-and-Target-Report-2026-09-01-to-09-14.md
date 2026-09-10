# Weekly Progress Report & Targets

**Progress Period:** September 1 – September 7, 2026  
**Targets Period:** September 8 – September 14, 2026  
**Apps covered:** People360 (school payroll & timekeeping desktop app) and Biometric Collector (campus time clock helper)

---

## In plain words

**Last week** we shipped **payslip email from Payroll Transaction** — HR can pick a posted batch and employees, and each person receives their payslip PDF by email through **AWS SES** (`noreply@skolaris.icct.edu.ph`).

**This week** the focus is **pulling approved forms from People360 web** (OT amendments, Job Order / WRF, and similar ESS requests) **into the desktop payroll batch** — so payroll no longer re-keys what managers already approved on web.

---

## What we finished last week (September 1 – 7)

### People360 — payslip email

**New Payslip tab under Payroll Transaction**  
After a batch is **posted**, HR opens **Payroll Transaction → Payslip**, selects the batch and one or more employees, and clicks **Send Email**. A confirmation dialog and progress bar show send status (e.g. 3 / 10).

**Email content**  
Each employee receives a short message plus their **payslip PDF** attachment. Net pay is shown in the email body. Employees must have an **email address** on their master record.

**AWS SES (not Gmail SMTP)**  
Sending uses the **SES API** (`MAIL_MAILER=ses`) with sender **`noreply@skolaris.icct.edu.ph`** in region **ap-southeast-2**. Campus `.env` must have valid SES keys and a verified sender; errors show clearer messages when AWS auth or sender verification fails.

**What HR can do today**  
Bulk-send payslips from any **posted** batch to employees with email on file. Campus UAT and HR sign-off on live batches are the remaining operational steps.

---

### Biometric Collector — last week

No new Collector installer was released.

**Installed so far:** Biometric Collector remains on **Antipolo only** (earlier version). **Binangonan, Taytay, and other campuses** do not have Collector installed yet.

---

## What we plan to do this week (September 8 – 14)

### People360 — approved web forms → payroll batch

1. **Pull approved ESS forms from People360 web**  
   Extend desktop sync to call the People360 web API for approved employee requests (Pulse API key with `employee_requests` scope). Store approved rows locally and add a **“Sync from People360 web”** action for payroll.

   **Includes:** OT / attendance amendments, Job Order, WRF, and other approved request types already processed on web.

2. **Apply synced forms to the payroll batch**  
   Map synced rows into the open payroll batch — e.g. attendance amendments → OT or attendance lines; Job Order / WRF → batch adjustments. Mark corresponding web rows **posted** after payroll post where applicable.

3. **UAT with HR**  
   Run end-to-end on staging: approved sample forms on web → sync → visible/adjusted in desktop batch → post. Fix findings; log each imported form in audit trail.

### Dependencies (need from IT / HR / Skolaris admin)

| Item | Owner | Needed for |
|------|-------|------------|
| Pulse API key: `employee_requests` list + sync | Skolaris admin | Web form sync |
| HR: sample **approved** OT + Job Order rows in staging | HR | Payroll batch UAT |
| Campus PCs People360 **1.0.2+** with outbound HTTPS | Campus IT | Web sync |

### Biometric Collector

4. **Antipolo — upgrade to the new auto-update installer** (if not done earlier).  
5. **Other campuses — first-time install** on Binangonan, Taytay, and scheduled sites.

---

*Report date: 7 September 2026 · For questions, refer to the technical changelog in `pulse/docs/changelog/`.*
