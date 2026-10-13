#!/usr/bin/env python3
"""People360 user guide for campus staff, with screenshots of each screen."""

import json
from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
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

LOOKUP_NOTE = (
    "Click Add. The window stays on this page. Fill in the fields and save. "
    "The pencil on the row is Edit. The green switch sets the row Active or Inactive. "
    "Delete (red trash) appears after the row is Inactive. Confirm the message. "
    "If people or payroll still use the row, leave it Inactive instead of deleting it."
)


def set_run_font(run, *, size=11, bold=False, italic=False, color=None):
    run.font.name = "Calibri"
    run._element.rPr.rFonts.set(qn("w:eastAsia"), "Calibri")
    run.font.size = Pt(size)
    run.bold = bold
    run.italic = italic
    if color is not None:
        run.font.color.rgb = color


def add_heading(doc, text, level=1):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(14 if level == 1 else 10)
    p.paragraph_format.space_after = Pt(4)
    run = p.add_run(text)
    if level == 1:
        set_run_font(run, size=20, bold=True, color=RGBColor(0x1F, 0x4E, 0x79))
    elif level == 2:
        set_run_font(run, size=16, bold=True, color=RGBColor(0x1F, 0x4E, 0x79))
    else:
        set_run_font(run, size=13, bold=True, color=RGBColor(0x2E, 0x75, 0xB6))
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


def main():
    doc = Document()
    section = doc.sections[0]
    section.top_margin = Inches(0.7)
    section.bottom_margin = Inches(0.7)
    section.left_margin = Inches(0.75)
    section.right_margin = Inches(0.75)
    figures = [0]

    add_heading(doc, "People360 — User Guide", 1)
    add_para(doc, "For campus HR and payroll staff. No technical background required.")
    add_para(
        doc,
        "People360 is the desktop app for employees, timekeeping, and payroll. "
        "Pulse Biometric Collector is a different app. Pulse only reads the time clock and sends the punches. "
        "People360 is where you pull those punches, check attendance, and compute pay.",
    )

    add_heading(doc, "How Add, Edit, and Delete work", 2)
    add_para(doc, "Almost every list works the same way. You stay on the list. A window opens on top of it.")
    add_bullet(doc, "Add — the blue button at the top right (Add Campuses, Add Employee, Add Policy, and so on). Fill in the window and save. Closing the window does not save.")
    add_bullet(doc, "Edit — the pencil on the row. Change the fields and save.")
    add_bullet(doc, "View — the eye, when the row has one. It shows the record without changing it.")
    add_bullet(doc, "Inactive — the green switch. Turn it off when you should stop using a row but must keep the history.")
    add_bullet(doc, "Delete — the red trash. On many lists it appears only after the row is Inactive. A question asks you to confirm. Cancel if you are not sure. Delete does not erase posted payroll.")
    add_para(doc, "The screenshots below were taken from People360. Buttons you do not see are hidden for your login. Ask an administrator if Add or Edit is missing.")

    add_heading(doc, "Order to follow", 2)
    add_bullet(doc, "Sign in.")
    add_bullet(doc, "Set up the lists people depend on: campuses, departments, positions, employment types, colleges, and programs.")
    add_bullet(doc, "Add employees, or upload them.")
    add_bullet(doc, "Set shifts, holidays, and the timekeeping policy. Assign that policy on the employee profile.")
    add_bullet(doc, "Set rate groups, income, deduction, loan, and leave types, then the government tables and the payroll calendar.")
    add_bullet(doc, "Pull or upload time logs. Check the employee attendance.")
    add_bullet(doc, "Create a payroll batch, process it, review it, then post it. Print or send payslips and BIR reports after posting.")

    current = None
    for item in MANIFEST:
        if item.get("error"):
            continue
        if item["section"] != current:
            current = item["section"]
            add_heading(doc, current, 2)

        add_heading(doc, item["title"], 3)
        note = NOTES.get(item["title"])
        if note:
            add_para(doc, note)
        elif item["section"] == "Human Resource":
            add_para(doc, LOOKUP_NOTE)
        else:
            add_para(doc, "Use Add, the pencil for Edit, and Delete only when the row is unused. Confirm before you delete.")

        if item.get("add"):
            add_para(doc, "Add: click the blue Add button. The window in the picture opens on the same page.")
        elif item.get("edit"):
            add_para(doc, "Edit: open the row with the pencil, change the fields, and save.")
        else:
            add_para(doc, "This screen is a workspace or a report. Use the buttons on the page. There is no separate Add row.")

        if item.get("list"):
            add_shot(doc, item["list"], f"{item['title']} — list", figures)
        if item.get("add"):
            add_shot(doc, item["add"], f"{item['title']} — Add", figures)
        if item.get("edit"):
            add_shot(doc, item["edit"], f"{item['title']} — Edit", figures)
        if item.get("hasDelete"):
            add_para(doc, "Delete: the red trash is on the row in the list picture (often only after the row is Inactive). Click it, read the question, and confirm only when the row should be removed.")

    add_heading(doc, "If something looks wrong", 2)
    add_bullet(doc, "You cannot see a menu — your role does not include it. Ask an administrator.")
    add_bullet(doc, "Add or Save shows a red message — a required field is empty, or the code is already used. Correct that field. The window stays open.")
    add_bullet(doc, "A person has no time logs — Pulse must be open on the campus PC, and People360 must pull biometric logs under Time Logs.")
    add_bullet(doc, "Pay looks wrong — check the employee profile (shift and policy), the rate group, the calendar period, and that the batch was processed after the latest logs.")
    add_bullet(doc, "Do not delete a posted batch from a list. Use Unpost Batches, fix the data, process again, then post.")

    doc.save(OUT)
    print(f"Wrote {OUT} ({OUT.stat().st_size} bytes), figures={figures[0]}")


if __name__ == "__main__":
    main()
