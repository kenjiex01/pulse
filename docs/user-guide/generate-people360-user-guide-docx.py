#!/usr/bin/env python3
"""People360 user guide for campus staff, with screenshots of each screen."""

import json
from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor

ROOT = Path(__file__).resolve().parent
IMG = ROOT / "images"
OUT = ROOT / "People360-User-Guide.docx"
MANIFEST = json.loads((ROOT / "manifest.json").read_text())

NOTES = {
    "Sign in": "Double-click the People360 icon. Type the email and password given by your administrator, then click Sign in. Tick Remember me only on a computer that other people do not share.",
    "Dashboard": "This is the home screen after you sign in. Use the blue menu on the left. Search modules is at the top of that menu. The cards summarize people and payroll. There is no Add or Delete on this page.",
    "Users": "Add User creates a login. The pencil opens Edit so you can change the name, email, or role. People360 does not show a trash icon here. To stop someone from signing in, edit the user and turn access off, or remove the role.",
    "Roles": "Add Role creates a permission set. Edit (pencil) changes which menus that role can open. Assign the role on the user record. Do not delete a role that people are still using.",
    "Database": "Use this page to back up or restore the People360 database. This is not a list of employees. Restore replaces data, so take a backup first and do it only when an administrator asks you to.",
    "Employees": "Add Employee opens a step-by-step window on this same page (campus, personal details, employment, salary). Upload brings in many employees from the spreadsheet template. Approve is for records waiting for compliance. On each row, the eye is View, the pencil is Edit, and the red trash is Delete. Delete is for a record you are allowed to remove. Active people should be set Inactive instead of deleted when they leave.",
    "Company Documents": "Add Company Documents creates a form. Edit changes it. Delete removes a document you no longer send. Sending and approval are separate actions on the same row.",
    "Document Approvals": "This list is for documents waiting for a decision. Open a row to review it. You do not add a new lookup here.",
    "HR Setup": "Campus notification and HR setup options. Save the form. This page is settings, not a row you add and delete.",
    "Timekeeping Policy": "Add Policy creates a policy. Edit (pencil) opens it. Inside a policy, use the settings pages (tardiness, overtime, breaks, night differential, general) and click Save. Do not delete a policy that employees still use. Set it inactive instead.",
    "Shift Codes": "Add Shift Code sets the time-in and time-out pattern. Edit changes an existing shift. Delete removes a shift that is not assigned. If people still use it, leave it and stop assigning it.",
    "Time Capturing Settings": "Add a time-capture format so uploads and biometric files match the columns People360 expects. Edit changes a format. Delete removes one you no longer use.",
    "Holiday Settings": "Add a holiday, holiday group, or year entry from the buttons on this page. Edit changes a row. Delete removes a holiday that should not be paid or observed.",
    "Policy — Tardiness and Undertime": "Open the policy, then this settings page. Change the rules and save. This is an edit of the policy, not a new list row. Delete, when shown, removes an extra rule row after you confirm.",
    "Policy — Overtime": "Set what happens to extra hours. For Regular Days and For Non-Regular Days each have their own choice: compute overtime automatically, require a form for regular and overtime, or compute regular hours but require a form for overtime. Save when you are done.",
    "Policy — Breaks": "Set break rules for this policy, then save. There is no separate employee list on this page.",
    "Policy — Night Differential": "Set night-differential hours for this policy, then save.",
    "Policy — General": "Set the general policy options, then save.",
    "Time Logs — Time In/Out": "This is where biometric punches from Pulse are pulled in, and where a file can be uploaded. Use the upload or pull action on the page. Purge or delete removes a bad upload batch after you confirm. It does not erase the time clock.",
    "Time Logs — DTR": "Daily time record view of the logs already in People360. Filter by person and date. Correct a row only when you are allowed to edit attendance.",
    "Time Logs — Teaching Loads": "Pull faculty teaching loads, or open the uploaded-load list. Delete removes an uploaded load file you do not want to keep, after you confirm.",
    "Employee Profile": "Search the person, then open the row. Setup or Edit is where shift, policy, and timekeeping details are saved. Attendance view shows the punches. Changes here affect payroll computation.",
    "Employee Load": "Faculty load used for faculty pay. Upload a file or edit a row. Delete removes a load entry that should not be paid.",
    "Memo Setup": "Set the memo template and how it is sent. Save. This is a settings form.",
    "Memo": "Pick the people and the period, preview the memo, then send. You are not creating a lookup row.",
    "Rate Groups": "Add a rate group and the day-type rates inside it. Edit changes the rates. Delete removes a group that no salary uses. If a salary still points at it, do not delete it.",
    "Night Differential Rate Groups": "Same as rate groups, for night differential. Add, edit, then delete only when nothing uses the group.",
    "Day Types": "Add a day type (regular day, rest day, holiday, and so on). Edit changes the name or pay treatment. Delete removes a type that is not used on a rate group.",
    "Income Types": "Add an income (allowance, bonus, and similar). Edit changes it. Turn it inactive when you should stop using it. Delete is for an inactive type that no payroll still needs.",
    "Deduction Types": "Add a deduction. Edit changes it. Inactive first, then delete only if it is unused.",
    "Loan Types": "Add a loan type. Edit changes it. Inactive first, then delete only if it is unused.",
    "Leave Types": "Add a leave type. Edit changes it. Inactive first, then delete only if it is unused.",
    "Pag-IBIG": "Add or edit a contribution row when the official table changes. Delete only a wrong inactive row. Do not delete the current official row.",
    "PhilHealth": "Add or edit a contribution bracket. Delete only a wrong inactive row.",
    "PhilHealth Minimum": "Edit the minimum table when the official amount changes. Save. Do not delete the live row.",
    "SSS": "Add or edit an SSS bracket. Delete only a wrong inactive row.",
    "Withholding Tax": "Open the frequency (daily, weekly, semi-monthly, monthly, or annual) and edit the official BIR table. Save. Do not delete the current table.",
    "Payroll Calendar": "Add a pay period for the pay type (daily, weekly, semi-monthly, or monthly). Edit the dates or the payroll schedule. Delete removes a period that has no posted payroll. Use the tabs to switch pay type.",
    "Calendar Priority": "Set which calendar People360 uses first when more than one pay type applies. Edit the order. Delete only an extra priority you added by mistake.",
    "Payroll Batches": "Add a payroll batch for a campus and period. Open the batch to add employees, review pay, process, and post. Unpost is on the Unpost Batches tab, not a trash icon on a posted batch.",
    "Upload Transactions": "Upload incomes, deductions, loans, or hours from the template. Preview, then commit. Purge or delete removes an upload batch that was not supposed to be kept.",
    "Unpost Batches": "Find a posted batch and unpost it when payroll must be corrected. Unpost is the reverse of post. Do this only when you are sure, then reprocess.",
    "Payslip": "Open a posted batch and send or print payslips. You do not add a new employee here.",
    "BIR Forms": "Prepare BIR form data from posted payroll. Follow the buttons on the page (generate or export). This is a report step, not a lookup you delete.",
    "Reports": "Choose the report, the period, and the output, then generate. Nothing is deleted from this page.",
}

VERSION = "2.0"
UPDATED = "14 October 2026"
OWNER = "People360 HR and Payroll operations"

LOOKUP_TITLES = {
    "Campuses",
    "Designations",
    "Positions",
    "Ranks",
    "Employment Types",
    "Employee Departments",
    "Colleges",
    "Programs",
    "Document Types",
    "Offense Categories",
    "Offense Frequencies",
    "Category Penalties",
    "Nature of Offense",
    "Income Types",
    "Deduction Types",
    "Loan Types",
    "Leave Types",
    "Day Types",
}

# Workflow order. Every manifest title must appear exactly once.
PARTS = [
    (
        "Part 1 — Introduction and Getting Started",
        "Sign in, find your way around, and learn the buttons that repeat on every list.",
        ["Sign in", "Dashboard"],
    ),
    (
        "Part 2 — Initial System Setup",
        "Create the reference lists before you add people. Later screens only offer values that exist here.",
        [
            "Campuses",
            "Employee Departments",
            "Designations",
            "Positions",
            "Ranks",
            "Employment Types",
            "Colleges",
            "Programs",
            "Document Types",
            "Offense Categories",
            "Offense Frequencies",
            "Category Penalties",
            "Nature of Offense",
        ],
    ),
    (
        "Part 3 — User and Employee Management",
        "Who can sign in, and the employee record that timekeeping and payroll will use.",
        ["Users", "Roles", "HR Setup", "Employees"],
    ),
    (
        "Part 4 — Timekeeping Setup and Operations",
        "Shifts and policies first, then assign them on the employee profile, then bring in attendance.",
        [
            "Shift Codes",
            "Holiday Settings",
            "Time Capturing Settings",
            "Timekeeping Policy",
            "Policy — Tardiness and Undertime",
            "Policy — Overtime",
            "Policy — Breaks",
            "Policy — Night Differential",
            "Policy — General",
            "Employee Profile",
            "Employee Load",
            "Time Logs — Time In/Out",
            "Time Logs — DTR",
            "Time Logs — Teaching Loads",
        ],
    ),
    (
        "Part 5 — Payroll Configuration",
        "Day types come before rate groups, because a rate group uses day types. Government tables and the payroll calendar come after the income, deduction, loan, and leave types.",
        [
            "Day Types",
            "Rate Groups",
            "Night Differential Rate Groups",
            "Income Types",
            "Deduction Types",
            "Loan Types",
            "Leave Types",
            "Pag-IBIG",
            "PhilHealth",
            "PhilHealth Minimum",
            "SSS",
            "Withholding Tax",
            "Payroll Calendar",
            "Calendar Priority",
        ],
    ),
    (
        "Part 6 — Payroll Processing",
        "This is the regular pay cycle: upload extras, create the batch, process, review, then post.",
        ["Upload Transactions", "Payroll Batches", "Unpost Batches"],
    ),
    (
        "Part 7 — Payslips, Reports, and Documents",
        "Do these after payroll is posted, or when you send HR letters and memos.",
        [
            "Payslip",
            "BIR Forms",
            "Reports",
            "Company Documents",
            "Document Approvals",
            "Memo Setup",
            "Memo",
        ],
    ),
    (
        "Part 8 — Administration and Troubleshooting",
        "Backup, restore, and what to check when a screen or a pay result looks wrong. Restore and unpost are administrator tasks.",
        ["Database"],
    ),
]


def set_run_font(run, *, size=11, bold=False, italic=False, color=None):
    run.font.name = "Calibri"
    run._element.rPr.rFonts.set(qn("w:eastAsia"), "Calibri")
    run.font.size = Pt(size)
    run.bold = bold
    run.italic = italic
    if color is not None:
        run.font.color.rgb = color


def add_heading(doc, text, level=1):
    p = doc.add_heading(text, level=level)
    p.paragraph_format.space_before = Pt(16 if level == 1 else 12)
    p.paragraph_format.space_after = Pt(4)
    color = RGBColor(0x1F, 0x4E, 0x79) if level < 3 else RGBColor(0x2E, 0x75, 0xB6)
    size = {1: 20, 2: 16}.get(level, 13)
    for run in p.runs:
        set_run_font(run, size=size, bold=True, color=color)
    return p


def add_para(doc, text):
    p = doc.add_paragraph()
    p.paragraph_format.space_after = Pt(6)
    p.paragraph_format.line_spacing = 1.12
    run = p.add_run(text)
    set_run_font(run, size=11)
    return p


def add_bullet(doc, text):
    p = doc.add_paragraph(style="List Bullet")
    p.paragraph_format.space_after = Pt(2)
    run = p.add_run(text)
    set_run_font(run, size=11)
    return p


def add_caption(doc, text):
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Pt(2)
    p.paragraph_format.space_after = Pt(8)
    run = p.add_run(text)
    set_run_font(run, size=9, italic=True, color=RGBColor(0x64, 0x74, 0x8B))


def add_shot(doc, filename, caption, figures):
    path = IMG / filename
    if not path.exists():
        return figures
    figures[0] += 1
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.add_run().add_picture(str(path), width=Inches(6.3))
    add_caption(doc, f"Figure {figures[0]}. {caption}")
    return figures


def add_callout(doc, label, text):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(4)
    p.paragraph_format.space_after = Pt(6)
    label_run = p.add_run(f"{label}. ")
    set_run_font(label_run, size=11, bold=True, color=RGBColor(0x9A, 0x34, 0x12))
    body = p.add_run(text)
    set_run_font(body, size=11)
    return p


def add_steps(doc, lines):
    for index, line in enumerate(lines, start=1):
        p = doc.add_paragraph()
        p.paragraph_format.left_indent = Inches(0.25)
        p.paragraph_format.space_after = Pt(2)
        run = p.add_run(f"{index}. {line}")
        set_run_font(run, size=11)


def add_page_number(paragraph):
    run = paragraph.add_run()
    set_run_font(run, size=9, color=RGBColor(0x64, 0x74, 0x8B))
    fld_begin = OxmlElement("w:fldChar")
    fld_begin.set(qn("w:fldCharType"), "begin")
    instr = OxmlElement("w:instrText")
    instr.set(qn("xml:space"), "preserve")
    instr.text = " PAGE "
    fld_end = OxmlElement("w:fldChar")
    fld_end.set(qn("w:fldCharType"), "end")
    run._r.append(fld_begin)
    run._r.append(instr)
    run._r.append(fld_end)


def add_toc(doc):
    paragraph = doc.add_paragraph()
    run = paragraph.add_run()
    fld_begin = OxmlElement("w:fldChar")
    fld_begin.set(qn("w:fldCharType"), "begin")
    instr = OxmlElement("w:instrText")
    instr.set(qn("xml:space"), "preserve")
    instr.text = r' TOC \o "1-2" \h \z \u '
    fld_separate = OxmlElement("w:fldChar")
    fld_separate.set(qn("w:fldCharType"), "separate")
    placeholder = OxmlElement("w:t")
    placeholder.text = "Right-click this line and choose Update Field to fill the contents."
    fld_end = OxmlElement("w:fldChar")
    fld_end.set(qn("w:fldCharType"), "end")
    run._r.append(fld_begin)
    run._r.append(instr)
    run._r.append(fld_separate)
    run._r.append(placeholder)
    run._r.append(fld_end)


def enable_update_fields(doc):
    settings = doc.settings.element
    update = OxmlElement("w:updateFields")
    update.set(qn("w:val"), "true")
    settings.append(update)


def apply_header_footer(section):
    header = section.header
    header.is_linked_to_previous = False
    hp = header.paragraphs[0]
    hp.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    run = hp.add_run(f"People360 User Guide  ·  Version {VERSION}")
    set_run_font(run, size=9, color=RGBColor(0x64, 0x74, 0x8B))

    footer = section.footer
    footer.is_linked_to_previous = False
    fp = footer.paragraphs[0]
    fp.alignment = WD_ALIGN_PARAGRAPH.CENTER
    left = fp.add_run(f"Updated {UPDATED}  ·  {OWNER}  ·  Page ")
    set_run_font(left, size=9, color=RGBColor(0x64, 0x74, 0x8B))
    add_page_number(fp)


def write_front_matter(doc):
    add_heading(doc, "People360 — User Guide", 1)
    add_para(doc, "For campus HR staff, payroll processors, approvers, and system administrators.")
    add_para(
        doc,
        "People360 is the desktop app for employees, timekeeping, and payroll. "
        "Pulse Biometric Collector is a different app on the campus PC. Pulse only reads the time clock and sends the punches. "
        "People360 is where you pull those punches, check attendance, and compute pay.",
    )
    add_para(doc, f"Version {VERSION}. Last updated {UPDATED}. Document owner: {OWNER}.")

    add_heading(doc, "Who this guide is for", 2)
    add_bullet(doc, "HR staff — campuses, employee records, documents, and memos.")
    add_bullet(doc, "Payroll processor — calendars, uploads, batches, review, and payslips.")
    add_bullet(doc, "Approver — document approvals and a posted batch that still needs a decision.")
    add_bullet(doc, "System administrator — users, roles, database backup, and restore. Restore is not a daily HR task.")
    add_para(doc, "A button you cannot see is hidden for your role. Ask an administrator. Do not share a login to reach a menu you do not have.")

    add_heading(doc, "Contents", 2)
    add_para(doc, "The contents follow the work, not the order of the left menu. Setup comes before the screens that depend on it.")
    add_toc(doc)

    add_heading(doc, "Quick start checklist", 2)
    add_para(doc, "Finish this list before the first live payroll. Tick each item only after you can open the screen and see your own data.")
    add_steps(doc, [
        "Sign in and confirm you can see Employees, Time Logs, and Payroll Batches.",
        "Campuses, departments, positions, employment types, colleges, and programs exist.",
        "At least one employee is saved, with a campus and an employment record.",
        "A shift code and a timekeeping policy exist, and that policy is on the employee profile.",
        "Day types exist, then a rate group that uses them, and the salary points at that rate group.",
        "Income, deduction, and loan types exist. Government tables (SSS, PhilHealth, Pag-IBIG, withholding tax) are the current official rows.",
        "A payroll calendar period covers the pay dates you will process.",
        "Pulse Biometric Collector is running on the campus PC, and People360 can pull time logs.",
        "You have a database backup before the first post.",
    ])

    add_heading(doc, "End-to-end workflow", 2)
    add_para(doc, "Use this path for a normal pay cycle. Parts 2 and 5 are setup. You do not repeat them every cutoff unless a rate, a holiday, or a government table changed.")
    add_steps(doc, [
        "Employee setup — add or upload the person, then complete employment, salary, and the Loans tab if a loan should be deducted.",
        "Timekeeping assignment — shift, holiday group, and timekeeping policy on the employee profile.",
        "Biometric logs — Pulse Collector stays open on the campus PC. In People360, pull or upload time logs.",
        "Attendance check — open Time Logs and the employee attendance view. Fix only the rows you are allowed to edit.",
        "Payroll inputs — upload hours, incomes, deductions, or loans that are not already on the employee record. Tick the loan types on the payroll calendar for this period.",
        "Process — create the payroll batch, add employees, and process.",
        "Review — gross pay, deductions (including the loan line), net pay, and missing attendance.",
        "Post — only after the authorized reviewer confirms the batch.",
        "Outputs — payslips, BIR forms, and other reports. Do this after the batch status is Posted.",
    ])

    add_heading(doc, "Common buttons", 2)
    add_para(doc, "Almost every list stays on the same page. A window opens on top of it. Closing the window does not save.")
    add_bullet(doc, "Add — the blue button at the top right. Fill in the window and save.")
    add_bullet(doc, "Edit — the pencil on the row.")
    add_bullet(doc, "View — the eye, when the row has one. It does not change the record.")
    add_bullet(doc, "Inactive — the green switch. Turn it off when you must stop using a row but keep the history. This is the label on the screen. Do not look for Deactivate or Disable.")
    add_bullet(doc, "Delete — the red trash. On many lists it appears only after the row is Inactive. Read the question. Cancel if you are not sure.")
    add_callout(
        doc,
        "Important",
        "Delete, Unpost, and Restore are not the same action. Delete removes an unused setup row. Unpost reverses a posted payroll batch. Restore replaces the whole database. Each one is described in its own section.",
    )

    add_heading(doc, "Words used in this guide", 2)
    add_bullet(doc, "DTR — daily time record. The attendance view of punches already stored in People360.")
    add_bullet(doc, "Pulse Biometric Collector — the campus app that reads the time clock. It is not People360.")
    add_bullet(doc, "Day type — regular day, rest day, holiday, and similar. Rate groups use day types.")
    add_bullet(doc, "Rate group — the pay rates for each day type. A salary points at one rate group.")
    add_bullet(doc, "Payroll batch — one campus and one pay period, processed and then posted.")
    add_bullet(doc, "Payroll calendar — the pay period dates, plus which loan and deduction types apply to that period.")
    add_bullet(doc, "Posted — the batch is final for payslips and reports until an authorized user unposts it.")


def write_employee_loans(doc):
    add_heading(doc, "Employee loans", 3)
    add_para(
        doc,
        "Loans live on the employee record, not on the employee list. Open the person, click Edit, then open the Loans tab. "
        "The list shows loan date, loan type, payment scheme, loan amount, amortization, and balance.",
    )
    add_callout(doc, "Before you begin", "Save the employee first. The Loans tab stays visible, but Add is available only after the employee exists. Loan types must already exist under Payroll.")
    add_steps(doc, [
        "On Employees, open the person with the pencil (Edit).",
        "Open the Loans tab.",
        "Click Add. The window stays on the employee page.",
        "Choose Loan Type and Loan Date. Both are required.",
        "Choose the payment scheme. User-entered amortization: you type the amortization amount. Based on number of payments: enter the number of payments and People360 computes amortization as loan amount divided by that number. You cannot type the amortization in that scheme.",
        "Enter Loan Amount. Payment Start Date is the first pay period that may deduct this loan. Interest, paid from previous, deducted from new loan, and purpose are optional.",
        "Save. The eye on the row opens the payment history (batch, pay period, batch status, date, payment, and penalty). The pencil edits the loan. The trash asks you to confirm before the loan is removed.",
    ])
    add_para(doc, "Expected result. The row shows a balance. The eye shows no payroll deduction until a batch actually deducts it.")
    add_callout(
        doc,
        "Important",
        "A tick on the payroll calendar does not deduct the loan by itself. All of these must be true: the loan type is ticked on that pay period, the employee has this loan, and the payment start date is on or before the period end. Each employee loan is deducted on one batch only. A later period does not deduct it again, even if the calendar stays ticked. To recalculate the amount, reprocess that same batch. Changing the amortization after the first deduction does not create a second deduction.",
    )
    add_para(doc, "After process, open the employee on the batch. The loan appears on the Deductions tab and in net pay. The same amount is on the payslip and the payroll register. The eye on the employee loan shows the batch status, including Posted after you post.")


def write_payroll_procedure(doc):
    add_heading(doc, "Process payroll", 2)
    add_para(doc, "Follow this procedure every cutoff. The screen notes under Payroll Batches name the buttons. This section is the order of work.")
    add_callout(
        doc,
        "Before you begin",
        "Employee profiles have a shift and a timekeeping policy. Attendance for the period is in Time Logs. The payroll calendar covers the dates. Rate groups and government tables are current. Loan types for this period are ticked on the calendar, and employee loans have a payment start date on or before the period end.",
    )
    add_steps(doc, [
        "Open Payroll Batches.",
        "Create a batch for the campus and the pay period, then save.",
        "Add the employees who should be paid in this batch.",
        "Upload hours, incomes, or deductions first if those amounts are not already on the employee record. Preview the upload and commit only the valid rows.",
        "Process the batch.",
        "Review each person: gross pay, deductions, loan lines, and net pay. Investigate missing attendance and amounts you did not expect.",
        "Post only after the authorized reviewer confirms the results.",
        "Confirm the batch status is Posted before you print payslips or generate BIR forms.",
    ])
    add_para(doc, "Expected result. The batch shows Posted. Payslip and BIR Forms can use it. An employee loan that was deducted shows that batch in the loan payment history.")
    add_callout(
        doc,
        "Important",
        "Do not delete a posted batch from a list. Unpost is a separate screen and a separate permission. Unpost only when payroll must be corrected. Then fix the data, process again, review, and post.",
    )


def write_time_log_checks(doc):
    add_heading(doc, "Before and after time logs", 2)
    add_para(doc, "Do these checks around every pull or upload. They prevent an empty or doubled attendance file from reaching payroll.")
    add_steps(doc, [
        "Before: Pulse Biometric Collector is open on the campus PC and has collected punches for these dates.",
        "Before: The date range in People360 matches the pay period, not a wider range than you intend to pay.",
        "Before: You know whether this is a new pull or a replacement. A second upload of the same file can duplicate punches if you do not purge the bad batch first.",
        "Pull or upload. Wait until the page finishes. Read the result count.",
        "After: Open the list and confirm the employees and dates you expect are present.",
        "After: Open one person on Employee Profile attendance, or on Time Logs — DTR, and confirm in and out times.",
        "After: If the file was wrong, purge or delete that upload batch only. That does not erase the time clock.",
    ])


def write_upload_reference(doc):
    add_heading(doc, "Upload reference", 2)
    add_para(doc, "Employee master data, employee salary, and payroll transactions each have their own template. Download the template from the upload window in People360. Do not reuse an old file after the columns change.")
    add_bullet(doc, "Employee upload — Employees, then Upload. Excel (.xlsx) is the usual file. Dates are YYYY-MM-DD or an Excel date such as 1/1/2000. The match key is employee number plus email. One campus is required unless Disable required fields is ticked for a partial update. Blank, when ticked, downloads headers only. Leave Blank unticked to download every existing employee.")
    add_bullet(doc, "Payroll uploads — Upload Transactions. Pick the upload type (incomes, deductions, loans, or hours). Download the template for that type, fill from row 4, and do not rename the header rows. Preview shows valid rows and errors. Commit only after you accept the preview.")
    add_bullet(doc, "If Save or Commit shows a red message, a required column is empty, a code is unknown, or a date is not valid. Correct the file and upload again. The window stays open.")
    add_para(doc, "Expected result. The preview count matches the rows you meant to load, and a spot-check employee shows the new value on the profile or on the batch.")


def render_screen(doc, item, figures):
    title = item["title"]
    if title == "Payroll Batches":
        write_payroll_procedure(doc)
    if title == "Time Logs — Time In/Out":
        write_time_log_checks(doc)

    add_heading(doc, title, 3)
    note = NOTES.get(title)
    if note:
        add_para(doc, note)
    if title in LOOKUP_TITLES:
        add_para(doc, "Use the common Add, Edit, Inactive, and Delete steps in Part 1. This section only adds what is specific to this list.")
    elif title == "Database":
        add_callout(
            doc,
            "Important",
            "Backup and restore are administrator actions. Restore replaces the data in this People360 installation. Take a backup first. Do it only when an administrator has told you to, and confirm you are restoring the file you intend.",
        )
    elif title == "Unpost Batches":
        add_callout(
            doc,
            "Important",
            "Unpost reverses a posted batch. Payslips and reports that used that post are no longer final. Only an authorized user should unpost. After unpost, fix the source data, process again, review, and post.",
        )
    elif title == "Payroll Batches":
        add_callout(
            doc,
            "Important",
            "Post is final for payslips and BIR forms until someone unposts the batch. Review gross pay, deductions, loans, and net pay before you post.",
        )

    list_caption = {
        "Sign in": "People360 sign-in screen",
        "Dashboard": "People360 dashboard and main navigation",
    }.get(title, f"{title} screen")
    if item.get("list"):
        add_shot(doc, item["list"], list_caption, figures)
    if item.get("add"):
        add_shot(doc, item["add"], f"{title} — Add window. Use the blue Add button, then save in this window.", figures)
    if item.get("edit"):
        add_shot(doc, item["edit"], f"{title} — Edit window. Open it with the pencil on the row.", figures)
    if item.get("hasDelete") and title not in LOOKUP_TITLES:
        add_para(doc, "Delete, when shown, is the red trash on the row. Read the question. Confirm only when this row should be removed. Leave a row Inactive when history must be kept.")

    if title == "Employees":
        write_employee_loans(doc)


def write_closing(doc):
    add_heading(doc, "Monthly payroll checklist", 2)
    add_steps(doc, [
        "Attendance for the period is pulled or uploaded, and a sample of employees was checked.",
        "Uploads for hours, incomes, deductions, and loans are committed, or you have confirmed none are due.",
        "The payroll calendar period is correct, including loan types ticked for this cutoff.",
        "The batch is processed and reviewed (gross, deductions, loans, net).",
        "The authorized reviewer has agreed, and the batch is Posted.",
        "Payslips are sent or printed from the posted batch.",
        "BIR forms and other reports are generated from the posted batch.",
    ])

    add_heading(doc, "If something looks wrong", 2)
    add_heading(doc, "Timekeeping", 3)
    add_bullet(doc, "A person has no time logs — Pulse Biometric Collector must be open on the campus PC, and People360 must pull biometric logs under Time Logs for those dates.")
    add_bullet(doc, "Times are duplicated — purge the extra upload batch, then pull or upload once.")
    add_bullet(doc, "Attendance is in Time Logs but pay ignores it — the employee profile needs a shift and a timekeeping policy, and the batch must be processed after the logs arrived.")
    add_heading(doc, "Payroll", 3)
    add_bullet(doc, "A loan did not deduct — the loan type must be ticked on this calendar period, the employee loan needs a payment start date on or before the period end, and that loan must not already have been deducted on another batch.")
    add_bullet(doc, "Pay looks wrong — check the employee profile, the rate group, the calendar period, uploaded transactions, and that you processed after the latest logs.")
    add_bullet(doc, "You need to change a posted batch — use Unpost Batches. Do not delete the batch from the list.")
    add_heading(doc, "Any screen", 3)
    add_bullet(doc, "You cannot see a menu — your role does not include it. Ask an administrator.")
    add_bullet(doc, "Add or Save shows a red message — a required field is empty, or the code is already used. Correct that field. The window stays open.")
    add_bullet(doc, "Escalate to an administrator when restore, a missing role, or a posted amount still looks wrong after the checks above.")


def main():
    by_title = {}
    for item in MANIFEST:
        if item.get("error"):
            continue
        by_title[item["title"]] = item

    ordered = [title for _part, _blurb, titles in PARTS for title in titles]
    missing = sorted(set(by_title) - set(ordered))
    extra = sorted(set(ordered) - set(by_title))
    if missing or extra or len(ordered) != len(set(ordered)):
        raise SystemExit(f"Guide outline does not match screens. missing={missing} extra={extra}")

    doc = Document()
    section = doc.sections[0]
    section.top_margin = Inches(0.7)
    section.bottom_margin = Inches(0.7)
    section.left_margin = Inches(0.75)
    section.right_margin = Inches(0.75)
    section.different_first_page_header_footer = False
    apply_header_footer(section)
    figures = [0]

    write_front_matter(doc)

    for part, blurb, titles in PARTS:
        add_heading(doc, part, 1)
        add_para(doc, blurb)
        if part.startswith("Part 6"):
            write_upload_reference(doc)
        for title in titles:
            render_screen(doc, by_title[title], figures)

    write_closing(doc)
    enable_update_fields(doc)
    doc.save(OUT)
    print(f"Wrote {OUT} ({OUT.stat().st_size} bytes), figures={figures[0]}")


if __name__ == "__main__":
    main()
