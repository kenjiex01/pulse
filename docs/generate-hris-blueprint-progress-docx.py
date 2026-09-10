#!/usr/bin/env python3
"""Generate People360 HRIS Blueprint Progress Word document (Ready / Partial / Pending + delivery timeline)."""

from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor

DOC_DIR = Path(__file__).resolve().parent
REPORT_DATE = "7 September 2026"
OUT_LATEST = DOC_DIR / "People360-HRIS-Blueprint-Progress.docx"
OUT_DATED = DOC_DIR / "People360-HRIS-Blueprint-Progress-2026-09-04.docx"

NAVY = RGBColor(0x0B, 0x31, 0x8F)
CYAN = RGBColor(0x00, 0xA3, 0xE6)
GRAY = RGBColor(0x47, 0x55, 0x69)
GREEN = RGBColor(0x16, 0x65, 0x34)
AMBER = RGBColor(0x92, 0x40, 0x0E)
RED = RGBColor(0x99, 0x1B, 0x1B)

READY = 4
PARTIAL = 9
PENDING = 15
MVP_READY = 4
MVP_TOTAL = 17

MODULES = [
    ("M01", "Organization & HR Configuration", "MVP", "Done", "Ready", "N/A", "Ready"),
    ("M02", "Worker / Employee Master Data", "MVP", "Dec 2026", "Ready", "Partial", "Partial"),
    ("M03", "Recruitment & Applicant Tracking", "MVP", "Dec 2026", "Not yet built", "Not yet built", "Not yet built"),
    ("M04", "Onboarding & Employment Documents", "MVP", "Deferred", "Partial", "Partial", "Partial"),
    ("M05", "Time, Attendance & Scheduling", "MVP", "14 Sep 2026", "Ready", "Partial", "Ready"),
    ("M06", "Leave & Absence Management", "MVP", "Oct 2026", "Partial", "Ready", "Partial"),
    ("M07", "Payroll & Statutory Pay", "MVP", "Done", "Ready", "N/A", "Ready"),
    ("M08", "Benefits & Government Contributions", "MVP", "Nov–Dec 2026", "Partial", "Not yet built", "Partial"),
    ("M09", "Performance Management", "Phase 2", "Dec 2026", "Not yet built", "Not yet built", "Not yet built"),
    ("M10", "Learning & Development", "Phase 2", "Dec 2026", "Not yet built", "Not yet built", "Not yet built"),
    ("M11", "Grievance & Discipline", "MVP", "Dec 2026", "Not yet built", "Not yet built", "Not yet built"),
    ("M12", "Separation, Clearance & Retirement", "MVP", "Nov 2026", "Not yet built", "Not yet built", "Not yet built"),
    ("M13", "Contractor & Outsourced Workforce", "MVP", "Dec 2026", "Not yet built", "Not yet built", "Not yet built"),
    ("M14", "Occupational Safety & Health", "MVP", "Dec 2026", "Not yet built", "Not yet built", "Not yet built"),
    ("M15", "Data Privacy & Records", "MVP", "Dec 2026", "Partial", "Partial", "Partial"),
    ("M16", "Self-Service, Requests & Approvals", "MVP", "Done", "N/A", "Ready", "Ready"),
    ("M17", "HR Compliance & Legal Rules", "MVP", "Nov–Dec 2026", "Partial", "Not yet built", "Partial"),
    ("M18", "HR Analytics, Dashboards & Audit", "Phase 2", "Dec 2026", "Partial", "Partial", "Partial"),
    ("M19", "Faculty & Academic Personnel", "MVP (schools)", "Dec 2026", "Partial", "Partial", "Partial"),
    ("M20", "Faculty Load, Schedule & Overload", "MVP (schools)", "Dec 2026", "Partial", "Partial", "Partial"),
    ("M21", "Faculty Evaluation & Promotion", "Phase 2", "Dec 2026", "Not yet built", "Not yet built", "Not yet built"),
    ("M22", "Faculty Development & Research", "Phase 2", "Dec 2026", "Not yet built", "Not yet built", "Not yet built"),
    ("M23", "Student-Safeguarding / Conduct", "Phase 2", "Dec 2026", "Not yet built", "Not yet built", "Not yet built"),
    ("M24", "Sales / Commission & Incentive", "Optional", "Optional", "Not yet built", "Not yet built", "Not yet built"),
    ("M25", "Shift, Field & Branch Workforce", "Optional", "Optional", "Not yet built", "Not yet built", "Not yet built"),
    ("M26", "Succession & Workforce Planning", "Phase 3", "Dec 2026", "Not yet built", "Not yet built", "Not yet built"),
    ("M27", "AI Assistance & Automation", "Phase 3", "Dec 2026", "Not yet built", "Not yet built", "Not yet built"),
    ("M28", "SDG / ESG Workforce Reporting", "Phase 3", "Dec 2026", "Not yet built", "Not yet built", "Not yet built"),
]

READY_DESKTOP = [
    (
        "M01",
        "Organization & HR Configuration",
        "Campuses, colleges, programs, departments, positions, designations, ranks, employment types, payroll calendar, holidays, users/roles.",
        "Web N/A — org configuration is desktop-only (org chart deferred).",
    ),
    (
        "M05",
        "Time, Attendance & Scheduling",
        "Policy, shifts, holidays, time log upload, biometric S3 pull, HR attendance edit, payroll OT, faculty load.",
        "Live clock-in, QR punch, OB, ESS corrections, attendance approval.",
    ),
    (
        "M07",
        "Payroll & Statutory Pay",
        "Compute/post, payslips, register, BIR reports, payslip email.",
        "Web N/A — live payroll is desktop-only.",
    ),
]


LAST_WEEK_DONE = [
    (
        "W1",
        "Payslip send by email",
        "M07",
        "Desktop",
        "1–7 Sep",
        "Done",
        "SES API; noreply@skolaris.icct.edu.ph; Payroll Transaction Payslip tab + PDF attach.",
    ),
]

THIS_WEEK_SPRINT = [
    (
        "W2a",
        "Pull approved ESS forms from People360 web",
        "M05 / M07",
        "Desktop + Web BE",
        "8–11 Sep",
        "Pulse API key (employee_requests sync)",
        "GET /pulse-api/v1/employee-requests/sync — desktop local store.",
    ),
    (
        "W2b",
        "Apply synced forms to payroll batch",
        "M05 / M07",
        "Desktop",
        "11–14 Sep",
        "W2a complete",
        "OT amendments, Job Order/WRF → batch lines before compute/post.",
    ),
]

SEPTEMBER_FOLLOWUP = [
    ("15–21 Sep", "Payslip + forms hardening", "M07, M05", "UAT fixes; sync audit log; installer if needed"),
    ("22–28 Sep", "Leave ↔ payroll tie-in", "M06, M05", "Approved web leave in desktop batch; desktop leave lines hardening"),
    ("29 Sep – 5 Oct", "Forms + leave polish", "M06, M05", "UAT fixes; approved leave import to batch"),
]

Q4_MVP = [
    ("MVP", "M12 Separation / clearance", "Nov 2026", "Clearance workflow + final pay hooks"),
    ("MVP", "M17 / M08 Effective-dated govt tables", "Nov–Dec 2026", "Version SSS / PhilHealth / Pag-IBIG"),
    ("MVP", "M03 Recruitment", "Dec 2026", "If hire-to-retire is in scope"),
    ("MVP", "M11 / M13 / M14 Discipline, contractors, OSH", "Oct–Dec 2026", "Risk modules"),
    ("Phase 2", "M09, M10, M21–M23", "Dec 2026", "Performance, L&D, faculty eval"),
    ("Phase 3", "M26–M28", "Dec 2026", "Succession, AI advisory, ESG (stretch backlog)"),
    ("Deferred", "M04 Onboarding", "TBD", "Not in current plan — ESS document upload/review stays"),
]

BLUEPRINT_STAGES = [
    ("1 Foundation", "Done (desktop M01)", "—"),
    ("2 Daily HR", "Dec 2026", "M16 forms bridge (Sep 2026 W2); M06 web leave Ready"),
    ("3 Payroll & compliance", "Dec 2026", "M07 desktop Ready; M17 (Nov 2026)"),
    ("4 Risk & outsourcing", "Dec 2026", "M11–M14 greenfield"),
    ("5 Education", "Dec 2026", "M19/M20 completion; M21+ phase 2 stretch"),
    ("6–7 Intelligence / advanced", "Dec 2026", "Reporting first; AI/ESG if capacity"),
]

EXTERNAL_CHECKLIST = [
    ("Pulse API key: employee_requests list + sync", "Skolaris admin", "W2a"),
    ("HR: sample approved OT + Job Order in staging", "HR", "W2b UAT"),
    ("Campus PCs People360 1.0.2+ with HTTPS", "Campus IT", "W2a"),
]


def set_run_font(run, *, size=11, bold=False, color=None):
    run.font.name = "Calibri"
    run._element.rPr.rFonts.set(qn("w:eastAsia"), "Calibri")
    run.font.size = Pt(size)
    run.bold = bold
    if color is not None:
        run.font.color.rgb = color


def shade_cell(cell, hex_color: str):
    tc = cell._tc
    tcPr = tc.get_or_add_tcPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:fill"), hex_color)
    shd.set(qn("w:val"), "clear")
    tcPr.append(shd)


def status_fill(status: str) -> str:
    if status == "Ready":
        return "DCFCE7"
    if status == "Partial":
        return "FEF3C7"
    return "FEE2E2"


def add_heading(doc, text, level=1):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(14 if level == 1 else 10)
    p.paragraph_format.space_after = Pt(6)
    run = p.add_run(text)
    if level == 1:
        set_run_font(run, size=20, bold=True, color=NAVY)
    else:
        set_run_font(run, size=13, bold=True, color=NAVY)


def add_para(doc, text, *, size=11, bold=False, color=None):
    p = doc.add_paragraph()
    p.paragraph_format.space_after = Pt(6)
    run = p.add_run(text)
    set_run_font(run, size=size, bold=bold, color=color or GRAY)
    return p


def add_table(doc, headers, rows, status_cols=None):
    table = doc.add_table(rows=1 + len(rows), cols=len(headers))
    table.style = "Table Grid"
    for i, h in enumerate(headers):
        cell = table.rows[0].cells[i]
        shade_cell(cell, "0B318F")
        run = cell.paragraphs[0].add_run(h)
        set_run_font(run, size=9, bold=True, color=RGBColor(0xFF, 0xFF, 0xFF))
    for r_idx, row in enumerate(rows):
        for c_idx, val in enumerate(row):
            cell = table.rows[r_idx + 1].cells[c_idx]
            if status_cols and c_idx in status_cols:
                shade_cell(cell, status_fill(val))
            run = cell.paragraphs[0].add_run(str(val))
            set_run_font(run, size=8, bold=(status_cols and c_idx in status_cols))
    doc.add_paragraph()
    return table


def build_document() -> Document:
    doc = Document()
    section = doc.sections[0]
    section.top_margin = Inches(0.75)
    section.bottom_margin = Inches(0.75)
    section.left_margin = Inches(0.9)
    section.right_margin = Inches(0.9)

    add_heading(doc, "People360 — HRIS Blueprint Progress")
    add_para(doc, "Desktop + Web · 28 modules (M01–M28)", size=12, bold=True, color=CYAN)
    add_para(doc, f"Updated: {REPORT_DATE} · Audience: Management, HR, project stakeholders")
    add_para(
        doc,
        "Desktop = People360 installer (pulse/). Web = Skolaris /people360 (iskolaris-fe + iskolaris-be). "
        "Combined = best of both; M01, M05, M07 are desktop Ready (web N/A for M01/M07); M16 is web-only (desktop N/A).",
    )

    add_heading(doc, "Progress snapshot", 2)
    pct = round(READY / 28 * 100, 1)
    add_table(
        doc,
        ["Metric", "Count", "Notes"],
        [
            ["Combined Ready", f"{READY} of 28", f"{pct}% of full blueprint — M01, M05, M07, M16"],
            ["Partial", f"{PARTIAL} of 28", "Usable but incomplete on Combined"],
            ["Not yet built", f"{PENDING} of 28", "No workflow on either product"],
            ["MVP Ready", f"{MVP_READY} of {MVP_TOTAL}", "MVP-tier modules marked Ready on Combined"],
        ],
    )
    add_para(
        doc,
        f"Visual progress bar (Combined): Ready {READY} · Partial {PARTIAL} · Pending {PENDING}.",
        bold=True,
        color=GREEN,
    )

    add_heading(doc, "Last week completed (1–7 September 2026)", 2)
    add_table(
        doc,
        ["#", "Deliverable", "Modules", "Product", "Dates", "Status", "Notes"],
        [[r[0], r[1], r[2], r[3], r[4], r[5], r[6]] for r in LAST_WEEK_DONE],
    )

    add_heading(doc, "This week — targets (8–14 September 2026)", 2)
    add_para(
        doc,
        "Target outcome: Payroll imports approved web forms (OT amendment, Job Order/WRF) "
        "into the desktop batch instead of re-keying.",
        bold=True,
    )
    add_table(
        doc,
        ["#", "Deliverable", "Modules", "Product", "Dates", "Depends on", "Notes"],
        [[r[0], r[1], r[2], r[3], r[4], r[5], r[6]] for r in THIS_WEEK_SPRINT],
    )
    add_para(
        doc,
        "Out of scope this week: recruitment, onboarding workflow (M04 deferred).",
        size=10,
    )

    add_heading(doc, "September 2026 (after next week)", 2)
    add_table(
        doc,
        ["Window", "Focus", "Modules", "Outcome"],
        list(SEPTEMBER_FOLLOWUP),
    )

    add_heading(doc, "Q4 2026 — MVP gaps & deferred", 2)
    add_table(
        doc,
        ["Priority", "Module", "Target window", "Notes"],
        list(Q4_MVP),
    )

    add_heading(doc, "Blueprint stages — high-level targets", 2)
    add_table(
        doc,
        ["Stage", "Target substantially complete", "Key milestones"],
        list(BLUEPRINT_STAGES),
    )

    add_heading(doc, "External checklist (this week)", 2)
    add_table(
        doc,
        ["Item", "Owner", "Needed for"],
        list(EXTERNAL_CHECKLIST),
    )

    add_heading(doc, "Desktop Ready — agreed scope (3 modules)", 2)
    add_para(doc, "Gaps listed under Web are not desktop owner work.", bold=True)
    add_table(
        doc,
        ["ID", "Module", "Desktop (Ready)", "Web (not desktop)"],
        [[r[0], r[1], r[2], r[3]] for r in READY_DESKTOP],
    )

    add_heading(doc, "Full module map — Desktop · Web · Combined", 2)
    add_para(doc, "Target date = planned substantially complete (from §10 delivery timeline).", size=10)
    add_para(doc, "M02 open gap: none on desktop (bank accounts shipped). Web master parity still Partial.", size=10, bold=True)
    add_table(
        doc,
        ["ID", "Module", "Priority", "Target date", "Desktop", "Web", "Combined"],
        list(MODULES),
        status_cols={4, 5, 6},
    )

    add_heading(doc, "Related documents", 2)
    add_para(doc, "Technical gap analysis + timeline: pulse/docs/hris-blueprint-gap-analysis.md")
    add_para(doc, "Progress dashboard (HTML): pulse/docs/People360-HRIS-Blueprint-Progress.html")
    add_para(doc, "Stakeholder status report: pulse/docs/People360-HRIS-Blueprint-Status-2026-08-27.html")
    add_para(doc, "Blueprint source PDF: pulse/docs/HRIS-Software-Modules-Blueprint-PH.pdf")

    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = p.add_run(f"— End of progress report · {REPORT_DATE} —")
    set_run_font(run, size=9, color=RGBColor(0x94, 0xA3, 0xB8))

    return doc


def main():
    doc = build_document()
    for out_path in (OUT_LATEST, OUT_DATED):
        doc.save(out_path)
        print(f"Wrote {out_path}")
        print(f"Size: {out_path.stat().st_size} bytes")


if __name__ == "__main__":
    main()
