# Weekly Progress Report & Targets

**Progress Period:** August 17 – August 23, 2026  
**Targets Period:** August 24 – August 28, 2026  
**Apps:** Pulse (payroll / timekeeping) and Biometric Collector

---

## Campus rollout (last week)

**Biometric Collector installed** on campus PCs for:

| Campus | Status |
|--------|--------|
| **Antipolo** | Installed |
| **Binangonan** | Installed |
| **Taytay** | Installed |

These sites can collect device punches locally, set retention, and upload logs to S3 for Pulse Pull logs. Follow-up this week: confirm Collect now + S3 path + Pulse import for each of the three campuses.

---

## I. Pulse — August 17 – August 23, 2026

**ISKOLARIS Approve (Employee Management):** Pending profile updates from ISKOLARIS show with employee full name; **Approve** per row, **Approve selected** (checked rows only), and **Approve all** (all shown after search). Does not overwrite salary, credentials, or loans. Matched by Pulse employee ID / employee number.

**S3 Pull logs retry:** Partially imported biometric files are re-read on every pull; already-imported punches stay as duplicates; only new matches (e.g. newly added employees with campus biometric IDs) are inserted. No empty batch when a retry has nothing new.

**Payroll Register Excel:** One worksheet per campus (empty campuses omitted); names as **Last, First Middle**, sorted by last name. Worksheet uses **Main assignment** campus; optional **Under Campus** on campus master (e.g. Greenhills under Cainta) rolls employees onto the parent sheet.

**Main assignment + Employee Assignment upload:** Campus cards have a Main assignment checkbox; **Employee Assignment** upload template can bulk-change the main campus.

**Campus minimum wage:** Nullable minimum wage field on campus maintenance.

**HR Employee report:** Full employee details report (personal, gov IDs, contact, assignments, salary, credentials as Yes/blank columns); hybrid salary cells tagged Faculty/Staff/Admin when hybrid.

**Encrypted secrets:** Skolaris Pulse API key and S3 credentials can be stored encrypted in `.env` (`enc:…`); decrypt only in memory.

**Desktop build:** Pulse **0.1.59** (macOS + Windows) on S3 `payroll_installer/` — includes Payroll Register per-campus Excel and last-name format.

## II. Biometric Collector — August 17 – August 23, 2026

**Collect now Server Error (San Mateo / large devices):** Background worker for Collect now; chunked SQLite inserts and S3 gzip uploads; JSON errors instead of generic 500. Builds **0.1.6**.

**Attendance start date:** Dashboard start date skips punches older than the window after device fetch. Build **0.1.7**.

**Loading overlay:** Full-screen loader on navigation, forms, and Collect now (status polls do not flash it).

**Log retention (Months to keep):** Keep only the last N months in SQLite; older logs archived as local JSON then deleted. Collect blocked until months is set. Build **0.1.8**.

**Save retention 500 fix:** Saving months only stores the setting; prune runs on next collect. Build **0.1.9** (latest shipped installer on S3 `biometric_installer/`).

**Deleted log backups:** Nav to list/download retention JSON backups; **Upload backup JSON** restores `kind: deleted_attendance` punches (skips duplicates).

**Download DTR:** Date from / date to + multi-select enrolled users → Cainta-style CSV timesheet (`Employee: Name (id)` with Date / In / Out).

**Edit device:** Dashboard Edit beside Users / Logs / Remove (name, model, IP, port, comm key).

**Encrypt S3 API keys:** `DB_BACKUP_S3_KEY` / `DB_BACKUP_S3_SECRET` support `enc:…` at rest.

**Campus installs:** Antipolo, Binangonan, and Taytay PCs installed with the Collector app (see table above).

---

## Targets — August 24 – August 28, 2026

## I. Pulse

**UAT of 0.1.59:** Payroll Register per-campus sheets + Main assignment / Under Campus; Employee report; ISKOLARIS Approve (per row / selected / all).

**S3 Pull logs E2E with new campuses:** Confirm Antipolo, Binangonan, and Taytay collector uploads land under `biometric_logs/…` and Pulse Pull logs + Process apply punches (including retry of unmatched IDs after employee add).

**Web S3 documents → desktop viewing:** Pull employee documents uploaded via web S3 and view them on Pulse desktop (Credentials / Document Types: list, preview, download).

**Report / payroll fixes from UAT:** Apply feedback; ship a follow-up Pulse build only after sign-off.

## II. Biometric Collector

**Ship build with Aug 20 features:** Package **Download DTR**, deleted-log backup download/upload, Edit device, and encrypted S3 secrets into a new installer (next version after **0.1.9**) and upload to S3.

**Verify Antipolo / Binangonan / Taytay:** Set Months to keep, Collect now, confirm S3 objects, then Pulse pull.

**San Mateo follow-through:** Confirm collect + S3 + Pulse import still stable after 0.1.9 (or newer) update.

**Rollout next campuses:** Continue installs beyond Antipolo, Binangonan, and Taytay as scheduled.

**Stability:** Prefer field verification and installer ship over large new features until the three new campuses are green on collect → S3 → Pulse.

## III. Cross-app (end-to-end)

1. Collector on **Antipolo, Binangonan, Taytay** → collect → S3.  
2. Pulse Pull logs → Process for those campus paths.  
3. Ship Collector build that includes DTR + backup modules; update campus PCs as needed.  
4. Web S3 employee documents → Pulse desktop viewing.
