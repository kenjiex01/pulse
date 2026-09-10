# Weekly Progress Report & Targets

**Progress Period:** August 25 – August 31, 2026  
**Targets Period:** September 1 – September 7, 2026  
**Apps covered:** People360 (school payroll & timekeeping desktop app) and Biometric Collector (campus time clock helper)

---

## In plain words

**Last week** we renamed the desktop app to **People360**, shipped three installer updates, made the employee list faster and easier to filter, added a **memo layout designer** for HR, fixed slow time-log imports, and wrote planning reports that show which HR modules are already “ready.”

**This week** the focus is **testing** what we shipped (especially **payslips** and payroll reports), fixing anything HR finds wrong, and rolling out the **new Biometric Collector installer with automatic updates** — upgrade **Antipolo** (the only site installed so far), then install on other campuses for the first time.

---

## What we finished last week (August 25 – 31)

### People360 — app name, updates, and installers

**New name: People360**  
The desktop app no longer shows “Pulse” to users. Installers are now named People360. Existing employee and payroll data on each PC is kept when upgrading — nothing needs to be re-entered.

**Automatic updates**  
When a new version is published, the app can download and install it in the background. Campus PCs no longer need a special password file just to check for updates.

**Installers released:** version **1.0.0** (first People360 release), **1.0.1** (employee list improvements), **1.0.2** (easier updates on all PCs).

### Employee records — easier to find people

**New filters on the employee list**  
HR can now narrow the list by **campus**, **department or college**, and **faculty vs staff**, in addition to active/inactive and compliance status.

**Much faster employee list**  
The employee screen used to take about half a minute to open; it now loads quickly when filters change. Pending ISKOLARIS profile updates only load when you open the Approve screen, so everyday browsing is not slowed down.

**Cleaner college dropdown**  
Each college name appears once in the filter list (not repeated for every campus).

**HR Employee report simplified**  
The employee details report no longer mixes in document/credential columns — it focuses on personal info, assignments, employment, salary, shift, and loans.

### Company Documents — design memo layouts (HR)

**New screen under Human Resource**  
HR can create and edit **memo templates** using a visual designer (similar to the form builder used in Skolaris): drag fields, type sample text, add images, signatures, dates, and so on.

**What you can do in the designer**  
Move fields anywhere on the page, resize them, wrap long paragraph text, and upload logos or stamps with transparency.

**Current scope**  
For now this is **template design only** — building the layout HR needs. Sending memos for approval through the app is not turned on yet.

### Timekeeping

**Pulling biometric files from the cloud**  
Importing a full month of time clock files no longer crashes with a timeout error. If the connection fails, you see a clear message instead of a technical error page.

**Time Logs list — pages work**  
Long lists of time log batches now have **First / Previous / Next / Last** buttons so HR can browse hundreds of batches without staying stuck on page 1.

### HR planning documents (for management)

**HRIS module checklist**  
We mapped all **28 standard HR modules** (hire-to-retire blueprint) against what People360 desktop and web already have.

**Three modules marked “Ready” on desktop**  
Organization setup (M01), Time & Attendance processing (M05), and Leave for payroll (M06). Items like employee leave filing and mobile clock-in are documented as **web** work, not desktop.

**Progress summary for stakeholders**  
A simple progress view (3 of 28 modules ready) was prepared in Word and HTML for sharing with non-technical readers.

---

### Biometric Collector — last week

No new Collector installer was released this week.

**Installed so far:** Biometric Collector is on **Antipolo only** — using an **earlier version**, not yet the new installer with automatic updates. **Binangonan, Taytay, and other campuses do not have Collector installed yet.**

---

## What we plan to do this week (September 1 – 7)

### People360

1. **Test the latest app (1.0.2+) on campus PCs**  
   Try employee filters, the memo designer, and time log paging. Release **1.0.3** only if something important is broken.

2. **Payslip review**  
   Run payslips from **posted** payroll batches for staff, faculty, and hybrid employees — on screen, Excel, and PDF. Fix wrong layouts, rates, or deduction lines based on HR/payroll feedback.

3. **Other payroll reports**  
   Apply fixes from testing on Payroll Register and government reports (SSS, PhilHealth, Pag-IBIG, BIR) after HR review.

### Biometric Collector

4. **Antipolo — upgrade to the new installer**  
   Replace the current Collector on Antipolo with the **latest installer that has automatic updates** (this is the only campus that has Collector today).

5. **Other campuses — first-time install**  
   Install the **same new auto-update installer** on **Binangonan, Taytay**, and other scheduled campuses. These sites **do not have Collector yet** — this will be their first install.

---

*Report date: 1 September 2026 · For questions, refer to the technical changelog in `pulse/docs/changelog/`.*
