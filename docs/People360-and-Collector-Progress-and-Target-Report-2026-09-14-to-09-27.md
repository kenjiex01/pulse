# Weekly Progress Report & Targets

**Progress Period:** September 14 – September 20, 2026  
**Targets Period:** September 21 – September 27, 2026  
**Apps covered:** People360 (school payroll & timekeeping desktop app) and Biometric Collector (campus time clock helper)

---

## In plain words

**Last week** we continued **Company Documents (MEMO)** so HR can send a memo from the template card, attach a fillable **Notice to Explain in Word**, keep a send history, and download a **Memo report** (who received which document, when, and which offense number). Offense categories and penalties can now be maintained in the app. **People360 1.0.7** was released. Master File upload also became safer: it matches by employee number, does not wipe blank cells, and no longer fails when an employee has more than one campus.

**This week** campuses should **install or auto-update to People360 1.0.7** and test memos. The main new build work is **pulling approved filed leave from People360 web into desktop payroll**, so HR does not re-encode leave that managers already approved. We will also continue Collector rollout beyond Antipolo.

---

## What we finished last week (September 14 – 20)

### People360 — Company Documents (MEMO)

**Send from the template card**  
HR can open Company Documents, click **Send** on an active memo, pick employees (search and **Select all**), and email the PDF. A progress bar shows how many have been sent (for example 27 / 250).

**Send history**  
After sending, the same screen lists who already received that document, how many times, and on which dates. Re-sending the same person adds a new history row.

**Notice to Explain (NTE)**  
A memo can be marked **Requires NTE** or **Set as NTE**. Only one active template can be the NTE itself. When a memo requires NTE, the employee email includes the memo PDF **and** a **Word (.docx)** Notice to Explain that they can fill in — not a locked PDF.

**Nature of Offense catalog**  
HR can maintain the ICCT Code of Offenses list (add, edit, activate, deactivate). Memo templates can pick the offense from that list instead of typing it freehand.

**Offense categories and penalties**  
**Offense Categories** now follows the official A–D penalty table (1st through later offenses). HR can edit penalty text and add a category or frequency when needed. Memo tags can fill **Disciplinary action** and **Offense frequency** (First Offense, Second Offense, and so on) based on how many times that memo was already sent to the same employee.

**Memo report**  
Under Reports → Human Resource → **Memo**, HR picks a date range and one or more company documents (with **Select all**). The download lists employee, document name, when it was sent, who sent it, and **offense frequency**. Sends from Company Documents and from Timekeeping are both included.

**Designer polish**  
HR can set canvas and text colors. The emailed PDF fills the full legal-size page. Empty field labels stay empty after save. Timekeeping Send asks for confirmation before emailing.

**People360 1.0.4 through 1.0.7 released**  
The latest paired installers are **1.0.7** (macOS and Windows). Campuses on auto-update can pick up **1.0.7**.

### Employee records

**Safer Master File upload**  
Upload matches existing people by **employee number**. Only filled Excel columns change — blank cells do not erase what is already on file. Email can be updated if it is not used by another employee.

**Upload changes in history**  
Master File updates now show on Employee History and in the Historical Data report (old value → new value).

**Multi-campus employees**  
Uploading campuses no longer errors when the employee already has more than one campus. Unlisted campuses are kept; a new primary campus demotes the others.

### Desktop app

**One window on Windows**  
Opening reports, uploads, or links no longer pops a second People360 window. Outside websites still open in the normal browser.

---

### Biometric Collector — last week

No new Collector installer was released.

**Installed so far:** Biometric Collector remains on **Antipolo only** (earlier version). **Binangonan, Taytay, and other campuses** do not have Collector installed yet.

---

## What we plan to do this week (September 21 – 27)

### People360 — campus use of MEMO on 1.0.7

1. **Roll out People360 1.0.7**  
   Campuses install or auto-update so everyone is on the build that includes Send, NTE Word, send history, offense tags, and the Memo report.

2. **HR test of send and NTE**  
   Open a memo, preview it, send a sample employee, and confirm: PDF matches the screen; if NTE is required, the Word file opens and can be filled in; send history shows the person and date.

3. **HR test of the Memo report**  
   Run Reports → Human Resource → Memo for a date range and selected documents (including Select all). Confirm the list shows the right employees, documents, send times, and offense number. Fix anything that looks wrong.

4. **Fix findings from campus use**  
   Adjust templates, tags, or send/report behavior based on what HR reports after testing 1.0.7.

### People360 — approved leave from web into payroll

5. **Pull approved filed leave from People360 web**  
   Employees already file leave on the web, and managers approve it there. Desktop People360 should **get only the approved leave forms** (employee, leave type, dates, hours/days) so payroll does not re-type what is already approved.

6. **Process those leaves in the desktop payroll batch**  
   When HR runs payroll, the approved leave should show on the employee’s payroll (leave pay or unpaid leave, matching the leave type). After the batch is posted, those web leave forms should be marked as already used in payroll so they are not applied twice.

7. **Test with HR**  
   Use sample **approved** leave on web → pull into desktop → confirm it appears on the open payroll batch → post. Fix anything that does not match (wrong dates, wrong leave type, or missing employee).

### Biometric Collector

8. **Antipolo — upgrade** to the auto-update installer if not done yet.  
9. **Other campuses — first-time install** on Binangonan, Taytay, and scheduled sites.

---

*Report date: 21 September 2026 · For questions, refer to the technical changelog in `pulse/docs/changelog/`.*
