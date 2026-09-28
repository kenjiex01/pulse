# Weekly Progress Report & Targets

**Progress Period:** September 21 – September 27, 2026  
**Targets Period:** September 28 – October 4, 2026  
**Apps covered:** People360 (school payroll & timekeeping desktop app) and Biometric Collector (campus time clock helper)

---

## In plain words

**Last week** we shipped several **payroll and employee-record** improvements: **holiday pay** on time-log payroll, a **Fixed Rate** option so some employees keep full basic pay without late/undertime/absent cuts, and clearer **salary and employment history** with effectivity dates. The **dashboard** opens faster and shows skeleton placeholders while data loads. **People360 1.0.8 through 1.0.13** went out with **same-network discovery** so one computer can list other People360 desktops and **connect to another campus database** for support or consolidation. **Time Logs** can **auto-pull new biometric files from cloud backup** while the app is open. **Memo Setup** now has a **Count** per violation type (late, undertime, absent) so HR sets how many occurrences are needed before a memo is sent.

**This week** campuses should move to **People360 1.0.13** and test **automatic memos when payroll is posted** (using those Memo Setup counts), plus holiday pay and fixed-rate payroll on real batches. The main build carry-over is still **approved filed leave from People360 web into desktop payroll**. Collector rollout beyond Antipolo continues.

---

## What we finished last week (September 21 – 27)

### People360 — Payroll & holidays

**Holiday pay (HOLI income)**  
When rules apply, employees can receive holiday income on legal or special holidays— including pay when they did not work the holiday but had time logs before and after, and premium rates when they did work the holiday.

**Fixed Rate salary**  
HR can mark a salary as **Fixed Rate**. Payroll keeps the configured **Basic Income** and skips late, undertime, and absent reductions for that employee. Government deductions still run when the batch is set up for them.

**Last Payroll Date**  
If an employee has a **Last Payroll Date**, payroll computation stops at that date even when the batch period runs longer— so separated or capped employees are not paid for days after their last payroll.

### People360 — Employee records

**Salary history effectivity**  
Saving a salary with a new **Effectivity From** closes the previous salary row the **day before** the new date (not on the same day by mistake).

**Employment history**  
Employment changes archive to **Previous Employment** with effectivity dates, similar to salary. **Separation Date** follows the last time log and cannot go later than **Last Payroll Date**.

### People360 — Dashboard & desktop experience

**Faster dashboard**  
The home screen appears first; **Biometric Collector / cloud backup status** loads afterward instead of blocking the whole page.

**Skeleton loading**  
Tables and the dashboard status panel show a **skeleton placeholder** while rows load (search, filters, pagination, and modals)— less “blank waiting” across the app.

**Desktop releases 1.0.8 – 1.0.13**  
Paired **macOS and Windows** installers and auto-update feeds through **1.0.13**, including performance UI, network features, and time-log auto-pull fixes.

### People360 — Same-network People360 (support / multi-PC)

**Find other People360 computers**  
On the same office network, **Database** can list other open People360 desktops (with Windows firewall handling improved in later builds).

**Connect to another computer’s database**  
An admin can **connect** to a chosen desktop’s database, work in the app against that data, then **disconnect** and save back to that computer— login stays on the local machine.

### People360 — Timekeeping & memos

**Auto-pull biometric logs from cloud backup**  
When enabled on **Time Logs**, new biometric files in cloud backup can import **automatically in the background** while People360 is open (without freezing the login page).

**Memo Setup — Count**  
For **Late**, **Undertime**, and **Absent**, HR sets a **Count** (e.g. 3 lates). Manual memo sending and the memo list respect that minimum.

**Send Document — extra emails**  
When sending a company document, HR can add **additional To addresses** beyond the employee email.

### Biometric Collector — last week

No new Collector installer was released.

**Installed so far:** Collector remains on **Antipolo** (earlier version). **Binangonan, Taytay, and other campuses** do not have Collector installed yet. Desktop **auto-pull from cloud backup** reduces manual import work where backup is configured.

---

## What we plan to do this week (September 28 – October 4)

### People360 — rollout & HR testing

1. **Roll out People360 1.0.13**  
   Campuses install or auto-update so everyone has holiday pay, Fixed Rate, faster dashboard, LAN database connect, S3 auto-pull, and Memo Setup counts.

2. **Test automatic memos on payroll post**  
   Set Memo Setup counts (e.g. late = 3). Post a payroll batch where employees meet those counts. Confirm late / undertime / absent memos email with the right PDF (and NTE Word when required). Fix anything HR reports.

3. **Test holiday pay on a real batch**  
   Run payroll for a period with legal or special holidays; confirm **HOLI** or expected amounts match campus rules.

4. **Test Fixed Rate employees**  
   Confirm basic pay is unchanged and late/undertime/absent lines do not reduce pay for marked Fixed Rate salaries.

5. **Fix findings from campus use of 1.0.8–1.0.13**  
   Templates, memos, network connect, or time logs— adjust based on HR/IT feedback.

### People360 — approved leave from web into payroll

6. **Pull approved filed leave from People360 web**  
   Employees file leave on the web and managers approve it there. Desktop should **import only approved** forms (employee, leave type, dates, hours/days) so payroll does not re-type them.

7. **Process those leaves in the desktop payroll batch**  
   Approved leave should appear on the employee’s payroll (paid or unpaid per leave type). After post, web forms should be marked **used in payroll** so they are not applied twice.

8. **Test with HR**  
   Approved leave on web → pull into desktop → open batch → post. Fix wrong dates, leave type, or missing employee.

### People360 — same-network database (IT / central HR)

9. **Pilot connect between two PCs**  
   Two People360 desktops on the same LAN: list each other, connect, verify data, disconnect and save. Document any firewall or Wi‑Fi issues for campuses.

### Biometric Collector

10. **Antipolo — upgrade** to the current auto-update path if not done yet.  
11. **Other campuses — first-time install** on Binangonan, Taytay, and scheduled sites.

---

*Report date: 28 September 2026 · For questions, refer to the technical changelog in `pulse/docs/changelog/`.*
