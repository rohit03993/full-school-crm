# CRM modules — reference study & native build

This document describes how **school-crm** grows as **one standalone product**. Other CRM projects (calling, attendance, homework, WhatsApp, fees, etc.) are **reference only** — we study their behaviour from code, then **build the same capabilities natively here**. We do **not** connect or depend on those external apps at runtime.

## Three pillars (one app, one student identity)

| Pillar | Purpose | Hub in school-crm |
|--------|---------|-------------------|
| **Management** | Leads, visits, follow-ups, calling | Search Student, All Leads, Follow-ups, Student Profile → Visits |
| **Academics** | Batches, attendance, activities, homework | Batch Attendance, Activities, Student Profile → Attendance |
| **Accounts** | Admission, fees, receipts | Admission Requests, Reports, Student Profile → Admission / Fees / Receipts |

**Identity rules (do not break):**

- One **mobile** → one `students` row (unique in DB)
- One **Roll No.** (`enrollments.enrollment_number`) issued when admission is **approved**
- **Student Profile** is the operational hub — all new features attach here and to shared models (`Student`, `Visit`, `Enrollment`)

## Phase 1 (done)

- Sidebar: **Management** | **Academics** | **Accounts** (+ Setup, Website, Administration)
- User-facing **Roll No.** label; editable in **Edit Details → Academic identity**
- **Follow-ups** worklist (due/overdue + next 7 days)
- **Import Students** (Management) — bulk enroll from Excel/CSV; session + course context; per-row duplicate mobile choice; global roll number; course fee on enrollment

## How we use reference projects (not interconnect)

Same workflow as fees (`docs/FEE_INSTALLMENTS_FEESCRM_VS_SCHOOL_CRM.md`):

| Step | What happens |
|------|----------------|
| 1 | You share a **reference project path** (read-only study) |
| 2 | We document **what it does**: screens, rules, data fields, workflows |
| 3 | We write `docs/*_REFERENCE_VS_SCHOOL_CRM.md` — behaviour comparison, not a merge plan |
| 4 | We **implement fresh in school-crm**: migrations, models, services, Filament UI — matching *your* three-pillar structure |
| 5 | No API links, no shared DB, no dependency on the old repo |

**Fees** is the template: behaviour was studied from Fees CRM, then built properly on admission/enrollment in this app.

## Modules still to build (reference → native)

| Module | Reference project (you provide path) | Build target in school-crm |
|--------|--------------------------------------|----------------------------|
| **Calling log** | Calling CRM (study only) | **Phase A+B done** — Call Queue, My Leads, Follow-ups (calls), profile Calls tab |
| **WhatsApp updates** | Messaging CRM (study only) | **Phase D done** — Pal Digital templates, campaigns, profile Messages tab |
| **Homework** | Academics CRM | Academics nav + Student Profile tab |
| **Attendance (extra)** | Attendance CRM | Extend existing batch/activity attendance if reference has more rules |
| **Fees (gaps)** | Fees CRM | Mostly done — only missing pieces from reference |

**Suggested build order:** ~~Calling~~ → ~~WhatsApp~~ → Homework → Attendance extras.

## What you do next

1. Smoke-test Phase 1 on your machine (nav groups, Follow-ups, Edit Details → Roll No.)
2. Send **one reference project path** at a time + which screens/rules matter most
3. We study the code, write the comparison doc, then implement **only inside school-crm**
4. You run migrations/tests locally when we ask

## Roll number (already in school-crm)

- **Edit Details → Academic identity** (after admission approved)
- Unique across students; ID card/receipts regenerate if changed; audit log entry

## Where new features land (anchors in this codebase)

- `app/Filament/Pages/StudentProfilePage.php`
- `app/Services/`, `app/Models/`
- Nav groups: `Management`, `Academics`, `Accounts`
- `app/Services/StudentSearchService.php` — mobile + Roll No. lookup

When you send the first reference path, we start with a behaviour doc, then a native vertical slice in this CRM only.
