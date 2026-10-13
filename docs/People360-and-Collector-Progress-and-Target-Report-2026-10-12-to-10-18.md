# Weekly Progress Report & Targets

**Progress Period:** October 5 – October 11, 2026  
**Targets Period:** October 12 – October 18, 2026  
**Apps covered:** People360 (school payroll & timekeeping desktop app) and Pulse Biometric Collector (campus time clock helper)

---

## In plain words

**Last week** we shipped **People360 1.0.23 and 1.0.24** with stronger **faculty teaching-load payroll** (pull from Skolaris is faster and lists every selected faculty, including those with zero rows; **Present** days pay even without biometric punches; **red names** on the batch follow teaching-load **days**, not hours). **PhilHealth and other statutory deductions** now use **month-to-date** pay only through the cutoff being processed (fixes wrong amounts when a later batch in the same month is not posted yet). **Timekeeping Policy → Overtime** now has separate rules for **regular days** and **non-regular days**, and payroll respects **“require filing”** before auto overtime from punches. **Desktop database restore** is safer (failed imports roll back instead of leaving an empty database) and accepts **larger SQL backup files** (up to about 512 MB). **Pulse Collector 1.0.3** sets **2 months** as the default log retention and shows **attendance log used / capacity / free** on each device when online.

**This week** campuses should **auto-update or install People360 1.0.24** and **Pulse 1.0.3**, then **re-test payroll with HR**: faculty batches, PhilHealth on first vs second cutoff, and staff overtime when policy requires **OT filing**. **Collector sites** should confirm **Months to keep = 2** (or their chosen value) and watch **device storage** before terminals fill up. **Approved leave from People360 web into desktop payroll** stays a priority build item if not yet in production testing.

---

## What we finished last week (October 5 – October 11)

### People360 — Faculty teaching load and payroll batch

**Teaching load pull from Skolaris**  
Bulk pull is **faster** (more employees per step, fewer duplicate lookups). Users can **cancel** a long pull. The batch list shows **every selected faculty member**, including **0 rows pulled** (highlighted in **red**), with **search** in the batch modal. Pull uses Skolaris **Employee Attendance** (same data as the web attendance page).

**Faculty pay when marked Present**  
Class days marked **Present** (or not marked absent) can **pay from the schedule** even when there is **no time in/out** on that date.

**Red names on posted batch**  
For faculty, **expected days** follow **distinct teaching-load dates** in the pay period (aligned with Skolaris “Days”), not full calendar length or total hours. Faculty with **no load and zero basic** stay red; those who met their load days no longer show red incorrectly.

**Desktop releases 1.0.23 and 1.0.24**  
Paired **macOS and Windows** installers and **automatic updates** through **1.0.24**.

### People360 — Statutory deductions and overtime policy

**PhilHealth / SSS / Pag-IBIG month-to-date**  
When processing an **earlier cutoff** in a calendar month, deductions no longer include **basic pay from a later batch** that is not processed yet. Second cutoff can **true up** against what was already withheld.

**Overtime policy — regular vs non-regular days**  
The Overtime settings screen has **For Regular Days** (new) and **For Non-Regular Days** (below it), each with the same three choices as before (auto compute, require forms for all, or require forms for OT only). Payroll **does not auto-count OT from excess punches** when the policy says **filing is required** for that day type.

### People360 — Database and dev reliability

**Safer SQL restore on desktop → MySQL (dev)**  
If a desktop SQLite dump **fails to import**, the system **restores the previous database** instead of leaving tables empty. Duplicate role names during import are **renamed** so import can finish.

**Larger SQL backup uploads**  
Restore uploads can go up to about **512 MB** on dev and desktop (with clear message if the server limit is lower).

### Pulse Biometric Collector — last week

**Pulse 1.0.3 released**  
Default **Months to keep = 2** for collector SQLite (older punches archived then removed after each collect). Dashboard shows **device storage (attendance)** as **used / capacity (free slots)** when the terminal is **online** (not during collect). Published to **automatic app update** (macOS DMG + Windows setup).

---

## What we plan to do this week (October 12 – October 18)

### People360 — rollout and HR testing

1. **Roll out People360 1.0.24** on all active campuses (auto-update or installer).  
2. **Reprocess or spot-check** recent batches where **PhilHealth** looked high on the **first cutoff** (e.g. same gross as a colleague but different PhilHealth).  
3. **Faculty payroll QA:** teaching-load pull batch, **red/black names**, and pay for **Present** days without punches.  
4. **Staff overtime:** set **For Regular Days** on the Staff timekeeping policy (e.g. require OT filing if that is campus rule), reprocess a sample employee, and confirm **OVRT** matches filings vs auto excess.  
5. **Fix campus-reported issues** from 1.0.23–1.0.24 (load pull, deductions, OT policy).  
6. **Optional:** restore a large campus **SQL backup** in a test environment to confirm **512 MB** upload and **rollback** behavior.

### People360 — approved leave from web into payroll

7. **Continue build/test:** import **approved filed leave** from People360 web into desktop payroll and mark web forms **used after post** so leave is not applied twice.  
8. **HR test path** when ready: approve on web → pull to desktop → batch → post.

### Pulse Biometric Collector

9. **Upgrade campuses to Pulse 1.0.3** (auto-update or installer).  
10. **Confirm Log retention** is set (**2 months** default) before collect; verify **old punches** prune after collect.  
11. **Use Device storage column** to plan clears or retention before terminals report **low free slots** (collector still does **not** erase logs on the physical device automatically).  
12. **Schedule first install** on campuses still pending (beyond Antipolo).

---

*Report date: 12 October 2026 · Technical detail: `pulse/docs/changelog/` and `biometric-collector/docs/changelog/`.*
