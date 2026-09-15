# Weekly Progress Report & Targets

**Progress Period:** September 8 – September 13, 2026  
**Targets Period:** September 14 – September 20, 2026  
**Apps covered:** People360 (school payroll & timekeeping desktop app) and Biometric Collector (campus time clock helper)

---

## In plain words

**Last week** we shipped **Company Documents (MEMO)** so HR can preview a memo on legal paper and email the same PDF the employee receives. Ready-made **ICCT disciplinary templates** are in the app, and **People360 1.0.3** was released. Teaching Loads can now pull uploaded faculty schedules from Skolaris, and employee Sex is stored as **Male** / **Female**.

**This week** we continue **Company Documents (MEMO) editing and additional features** — refine templates in the designer, add the next memo capabilities HR needs, and support campus testing of the memos already released.

---

## What we finished last week (September 8 – 13)

### People360 — Company Documents (MEMO)

**Preview matches the emailed memo**  
HR can open Preview and see the same legal-size page (8.5 × 14 in) that is attached to the employee email. Field boxes, stamps, and margins stay aligned so “what you preview is what gets sent.”

**Faster, more reliable send**  
Sending a memo no longer times out when the template has stamps or images. Batch send can finish without the app cutting off at 30 seconds.

**ICCT Code of Offenses templates**  
Eight ready templates are available for HR: Notice to Explain, Verbal Reprimand, Written Warning, Notice of Suspension, Notice of Dismissal, Return to Work, AWOL notice, and Uniform / ID / Nameplate. They follow the official offense headings and penalty table.

**Table of Penalties**  
The official ICCT penalty matrix (Categories A–D by frequency) is stored in People360 so those templates stay consistent.

**People360 1.0.3 released**  
macOS and Windows installers include the memo preview/email work and earlier payslip email. Campuses on auto-update can pick up **1.0.3**.

### Teaching Loads

**Uploaded faculty loading from Skolaris**  
Time Logs → Teaching Loads has an **Uploaded PDFs** tab. People360 pulls faculty loading that was already uploaded in Skolaris (no local PDF upload in the desktop app).

**Attendance Checker marks on pull**  
When HR pulls teaching loads from Skolaris, attendance marks already recorded in Attendance Checker (present, absent, late, and similar) come with the schedule.

### Employee records

**Sex saved as Male / Female**  
Employee Profile and Master File upload now store **Male** and **Female** (not lowercase). Existing records were updated. Upload still accepts `male` / `female` and converts them.

---

### Biometric Collector — last week

No new Collector installer was released.

**Installed so far:** Biometric Collector remains on **Antipolo only** (earlier version). **Binangonan, Taytay, and other campuses** do not have Collector installed yet.

---

## What we plan to do this week (September 14 – 20)

### People360 — continue Company Documents (MEMO)

1. **Keep editing MEMO templates**  
   Continue work in the Company Documents designer: layout, wording, stamps, signatures, and merge tags (employee name, late/absent dates, and similar) so templates match how HR writes memos on paper.

2. **Additional MEMO features**  
   Add the next memo capabilities HR still needs after last week’s send/preview release — for example more template options, easier editing, and any send/preview fixes found in campus use.

3. **Campus test of released memos**  
   Support HR on People360 **1.0.3**: open a memo, preview it, send a sample, and confirm the employee PDF matches the screen. Fix anything that looks wrong in Preview or email.

### Biometric Collector

4. **Antipolo — upgrade** to the auto-update installer if not done yet.  
5. **Other campuses — first-time install** on Binangonan, Taytay, and scheduled sites.

---

*Report date: 14 September 2026 · For questions, refer to the technical changelog in `pulse/docs/changelog/`.*
