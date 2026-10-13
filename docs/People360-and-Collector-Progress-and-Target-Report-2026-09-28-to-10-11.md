# Weekly Progress Report & Targets

**Progress Period:** September 28 – October 4, 2026  
**Targets Period:** October 5 – October 11, 2026  
**Apps covered:** People360 (school payroll & timekeeping desktop app) and Pulse Biometric Collector (campus time clock helper)

---

## In plain words

**Last week** we moved campuses toward **People360 1.0.14 through 1.0.22**: **automatic memos when payroll is posted** (using Memo Setup counts), refreshed **HR letter and memo templates** with correct ICCT letterhead, and **fewer Windows firewall pop-ups** when using same-network database connect. **Employee Profile** gained **Staff/Faculty filter**, **different shift codes per weekday**, and smoother bulk upload. **HR Setup** is new: **HR email(s)**, **probationary-end reminders**, **notification history**, and a **daily 5 PM email** listing campuses with **no biometric upload today**. **Employee Load** templates now pull from **Skolaris class schedules** (faster monthly download; default time in/out from schedule). **Faculty memos** can count absences from **teaching load** even without a shift setup; **break tardiness** follows shift break windows when configured. **Pulse Collector 1.0.2** fixes the desktop **stuck “Loading… Please wait”** screen.

**This week** campuses should install or auto-update to **People360 1.0.22** and **Pulse 1.0.2**, then **test with HR**: posted-payroll memos, probationary emails (real inbox, not log-only mail), employee load monthly template, faculty memos, and break-late payroll. **Approved leave from People360 web into desktop payroll** remains the main build focus. **Collector rollout** continues beyond Antipolo.

---

## What we finished last week (September 28 – October 4)

### People360 — Payroll, memos, and time rules

**Automatic memos on payroll post**  
After a batch is posted, the app checks late, undertime, and absent counts in the pay period and **emails memos automatically** when each type meets the **Memo Setup Count** (e.g. 3 lates). Posting still succeeds if one email fails; HR sees a summary message.

**Break tardiness vs shift schedule**  
When a timekeeping policy deducts break tardiness and the shift has **Break Out / Break In** times, payroll compares actual lunch punches to that window (not only total break minutes).

**Faculty memos from teaching load**  
Faculty without timekeeping shift setup can appear on Memo when they have **Employee Load / teaching schedules**; absences follow scheduled classes without time in/out. Skolaris checker rows with **no mark count as Present** (only explicit absent marks count as absent).

### People360 — Company documents and HR letters

**HR letter and memo templates (nine forms)**  
Replaced legacy designer layouts with Word-aligned **Letters 1–5**, attendance, absences/tardiness, company property, and PhilHealth templates, including **ICCT logo** without stretched images.

**Web NTE (Notice to Explain) tracking**  
Sending a memo can open an **NTE case** with a **response due date** and portal link in email; HR can view cases and mark received. Pull from Skolaris web is prepared for when the API is live.

**Designer improvements**  
Letterhead alignment, **float left/right** in paragraphs (control no. vs date), and **Nature of offense** merge tag when configured.

### People360 — Employee Profile and Employee Load

**Staff / Faculty filter**  
Employee Profile list can filter **All, Staff, or Faculty** with search on one toolbar row.

**Weekly shift schedule**  
Each weekday can have its own **shift code** (rest days exempt); payroll, memos, and attendance use the correct shift per date.

**Profile bulk upload**  
UTF-8 BOM from Excel CSV fixed; **blank rows skipped**; rest days do not require shift codes; template documents weekly columns.

**Employee Load template from Skolaris**  
Download Template uses **Loading Attendance / daily-loads** data (faster full-month download, schedule-based **Time In/Out** defaults). Pull from Skolaris applies the same rules.

### People360 — HR Setup and notifications

**HR Setup screen**  
Admins set **comma-separated HR emails**, **days before probation ends** (e.g. 7, 14, 30), and **email subject/body** with merge tags. **Notification history** shows what was sent.

**Probationary end emails**  
On **first app open each day**, reminders go to **HR only** for probationary employees due on configured offsets; each employee/offset sends once. UI warns if mail is still **log-only** (not real SMTP).

**Missing biometric upload (5 PM)**  
After **5:00 PM Manila time**, HR receives one email listing campuses with **no upload today** (same logic as dashboard status).

### People360 — Desktop, network, and releases

**Same-network database (Windows)**  
Firewall approval runs on **Database → Refresh**, not on every app open; repeat **UAC** prompts reduced.

**Skolaris connection in installers**  
Desktop builds bundle encrypted **Pulse API** and **Skolaris login** settings needed for Employee Load and future web sync.

**Desktop releases 1.0.14 – 1.0.22**  
Paired **macOS and Windows** installers and auto-update through **1.0.22** (memos, HR Setup, employee load, faculty memo, break tardiness).

**Editable employment Effectivity To**  
Current employment can show an end date instead of read-only “Present,” with validation against Effectivity From.

### Pulse Biometric Collector — last week

**Pulse 1.0.2 released**  
Fixes the app **stuck on “Loading… Please wait”** by running **auto-collect in a background worker**, **not blocking the screen** on updater checks, and **resetting the loader** after navigation. Published to **GitHub auto-update** (macOS DMG + Windows setup).

**Rollout status**  
**Antipolo** remains the primary installed site; **Binangonan, Taytay, and other campuses** still pending first install or upgrade to **1.0.2**.

---

## What we plan to do this week (October 5 – October 11)

### People360 — rollout and HR testing

1. **Roll out People360 1.0.22** on all active campuses (auto-update or installer).  
2. **Test automatic memos on payroll post** with real Memo Setup counts; confirm PDF/email and NTE cases where used.  
3. **Configure HR Setup** (HR emails, probationary day list) and confirm **inbox delivery** with campus SMTP—not log-only mail.  
4. **Test 5 PM missing-upload email** against dashboard “no upload today” campuses.  
5. **Employee Load:** download a **full-month template**, edit, upload; confirm schedule times and payroll/memo behavior for faculty.  
6. **Test break tardiness** on a batch where shifts define Break Out/In and lunch is shifted.  
7. **Fix campus-reported issues** from 1.0.14–1.0.22 (templates, memos, load, network connect).

### People360 — approved leave from web into payroll

8. **Import approved filed leave** from People360 web (employee, type, dates, hours/days).  
9. **Apply approved leave in desktop payroll batch**; after post, mark web forms **used in payroll** so they are not applied twice.  
10. **HR test path:** approve on web → pull to desktop → batch → post.

### People360 — Company documents / NTE

11. **Pilot web NTE flow** with HR (send memo, due date in email, mark received); enable Skolaris pull when API is ready.

### Pulse Biometric Collector

12. **Upgrade Antipolo** to **Pulse 1.0.2** if not yet on auto-update (verify loader and auto-collect).  
13. **Schedule first install** on **Binangonan, Taytay**, and other planned campuses.  
14. **Confirm retention months** are saved before collect; monitor background collect without freezing the UI.

---

*Report date: 5 October 2026 · Technical detail: `pulse/docs/changelog/` and `biometric-collector/docs/changelog/`.*
