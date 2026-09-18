# Staff jobs vs functions — research (not implemented)

**Status:** Research only. Do **not** change roles, permissions, or the staff form until this is explicitly picked up.  
**Date:** 18 September 2026  
**Product:** School CRM  
**Related:** [`MODULE_ARCHITECTURE.md`](MODULE_ARCHITECTURE.md) · [`CRM_FEATURE_OVERVIEW.md`](CRM_FEATURE_OVERVIEW.md)

This note records how staff access works **today**, why the Super Admin checkbox list mixes **jobs** with **functions**, and a recommended split for a later change. Code paths below are the source of truth until then.

---

## Decision (locked for later)

- **Job** = who the person is. It should drive Home, bottom tabs, and the default tools they use every day.
- **Function** = an extra switch on that person. Same idea as **“Can see student mobile numbers”**, which is already a toggle, not a job.
- Fee adjuster, WhatsApp inbox, WhatsApp bulk, WhatsApp fee notices, and WhatsApp desk (full) are **functions**, not jobs.
- Do not implement this split until product confirms the five jobs and the function list below.

---

## Three layers that already exist in code

| Layer | What it answers | Where |
|-------|-----------------|--------|
| **Identity** | Super Admin vs Staff vs Student (portal) | [`app/Enums/RoleName.php`](../app/Enums/RoleName.php) |
| **Job / function ticks** | What they can do in `/admin` | [`app/Enums/StaffJobRole.php`](../app/Enums/StaffJobRole.php) + [`app/Support/StaffRolePermissions.php`](../app/Support/StaffRolePermissions.php) |
| **Class assignment** | Which batches a teacher owns | [`app/Enums/BatchStaffRole.php`](../app/Enums/BatchStaffRole.php) (`lead_teacher` / `subject_teacher`) |

How a check is evaluated ([`CrmAccess`](../app/Support/CrmAccess.php)):

```text
Super Admin     → bypass everything (vault)
Staff + job(s)  → union of those jobs’ permissions
Staff, no job   → leftover “legacy staff” bundle (calling-ish; not fees / academics / WhatsApp)
Extra checkbox  → only student mobile today (direct permission, not wiped by role sync)
Class assignment → teacher’s batches only (not a CRM job)
```

Ticking two jobs **adds** permissions. Accountant + Fee adjuster can collect **and** change plans. That union rule is correct. The problem is **what we labelled a job**.

---

## What Super Admin ticks today

Staff form: [`app/Filament/Resources/Staff/StaffResource.php`](../app/Filament/Resources/Staff/StaffResource.php) (“Access & roles” + “Student numbers”).

| Tick today | What it really is |
|------------|-------------------|
| Super Admin (full access) | Identity — already separate. **Keep.** |
| Counsellor (calls & leads) | **Job** |
| Admission officer | **Job** |
| Accountant (fees) | **Job** |
| Fee adjuster (discounts & structure) | **Function** of Accounts |
| Academic coordinator | **Job** |
| Teacher / Faculty | **Job** |
| WhatsApp inbox | **Function** |
| WhatsApp bulk campaigns | **Function** |
| WhatsApp fee notices | **Function** |
| WhatsApp desk (full) | Shortcut that turns on several WhatsApp **functions** |
| Can see student mobile numbers | **Function** (already a toggle). **Keep as toggle.** |

---

## Permissions today (as the software grants them)

Permission labels live in [`app/Enums/CrmPermission.php`](../app/Enums/CrmPermission.php). Matrix: [`StaffRolePermissions::matrix()`](../app/Support/StaffRolePermissions.php).

### Shared on every current job tick

- View students (`crm.students.view`)
- Own cases: view / open / assign / close  
  **Not** institute-wide cases (`crm.cases.view_all`) — Super Admin only.

### Nobody gets from a job

- See student mobile & dial (`crm.students.view_mobile`) — extra toggle only; Super Admin always sees numbers
- Staff accounts, Setup, WhatsApp/Meta API settings, owner dashboard charts, payment-cancel approve, waive-request approve

### Jobs (should stay jobs)

**Counsellor**

- Has: calling dashboard stats, assigned leads, call queue & log, students, own cases
- Has not: all leads, reassign, all campus visits, fees, admissions approve, academics, WhatsApp, reports export

**Admission officer**

- Has: calling stats, all + assigned leads, call, reassign, all campus visits, student edit + import, certificates view + issue, admissions view + approve, own cases
- Has not: collect / adjust fees, marks, WhatsApp desk

**Accountant**

- Has: finance dashboard, collect fees, certificates, admissions **view** (not approve), reports view + export, own cases
- Has not: adjust fee plan / waive, academics, WhatsApp

**Academic coordinator**

- Has: students, certificates, attendance (+ workshops), marks import **and publish**, homework, academics setup, own cases  
  Can send exam-marks WhatsApp because they can publish (`CrmAccess::canSendExamMarksWhatsApp`)
- Has not: fees, leads admin, WhatsApp campaigns desk

**Teacher**

- Has: students, mark attendance, enter marks, own cases (later scoped by class assignment)
- Has not: publish marks, homework manage, academics setup, fees, leads, WhatsApp

### Functions currently stored as jobs (should become toggles later)

**Fee adjuster**

- Has: finance stats, adjust structure, waive/discount request, admissions view, reports view + export, own cases
- Has not: collect fees (cannot create payments)

**WhatsApp inbox** — inbox only (plus students + own cases)  
**WhatsApp bulk campaigns** — campaigns only  
**WhatsApp fee notices** — manual amount + due date WhatsApp; does **not** read/write the fee ledger  
**WhatsApp desk (full)** — inbox + campaigns + fee notices + templates/live (`WhatsappOps`). Still **cannot** open Meta/API settings.

### Super Admin vault (never a staff job)

Staff accounts, institute Setup, all cases, WhatsApp/Meta settings, approve payment cancellations, approve waive/discount requests, owner charts.

### Legacy `staff` role (no job ticks)

Broad calling-style ops without fees, academics, WhatsApp, or export. Exists so old logins still work. Assign a real job instead of relying on this.

---

## Why this confuses the team (UX)

1. **Home is chosen from “jobs”.** Inbox-only staff get a messaging Home even if they are an accountant who was given inbox. Fee adjuster shares the **finance** Home with Accountant, so two different money powers look like one role.
2. **Ten checkboxes look like ten jobs.** Front-office staff will not remember “WhatsApp fee notices is a role.”
3. **The same extra is modeled two ways.** Mobile numbers = toggle. WhatsApp inbox = fake role. Fee adjust = fake role.
4. **Own cases are on every job.** “My work” appears for people who may never handle cases. That is a default function, not a job.
5. **WhatsApp desk vs three ticks is duplicate.** Full desk is “check all WhatsApp functions.”

A school owner should think: *Who is this person?* then *What extra can they do?*

---

## Recommended model (later — not built)

### Section A — Job (one or more; drives Home + tabs)

| Job | Default work |
|-----|----------------|
| Counsellor | Calls, my leads, follow-ups |
| Admission officer | Admissions, all leads, visits |
| Accountant | Collect fees, receipts, fee reports |
| Academic coordinator | Classes, attendance, homework, publish |
| Teacher | Own classes only |

Do **not** merge Counsellor with Admission officer, or Teacher with Academic coordinator.

### Section B — Extra functions (toggles, like mobile numbers)

| Function | Meaning today |
|----------|----------------|
| See student mobile & dial | Already a toggle |
| Adjust fee plans & discounts | Today’s Fee adjuster |
| WhatsApp inbox | Replies |
| WhatsApp bulk campaigns | Class broadcasts |
| WhatsApp fee notices | Manual pending-fee messages |
| WhatsApp templates & live | Today’s desk-only `WhatsappOps` |

**WhatsApp desk (full)** can disappear as a job: Super Admin ticks the WhatsApp functions.

**Accountant + Adjust fees ON** = today’s Accountant + Fee adjuster (collect and change plans).  
**Accountant + Adjust fees OFF** = collect only (safer for a counter clerk).  
**Adjust fees ON without Accountant** = today’s Fee adjuster (change plans, cannot take cash). Keep this if a principal edits plans but does not sit at the counter.

**Any job + WhatsApp inbox ON** = they keep their real Home (calls or fees); Inbox is a tool, not a fake “messaging person.”

Optional later (still functions, not jobs): export reports, import students, issue certificates. Today those are baked into Admission / Accounts / Coordinator.

### Suggested staff form (later)

```text
Identity     Super Admin?  (if yes, stop — they have everything)

Job          [ ] Counsellor
             [ ] Admission officer
             [ ] Accountant
             [ ] Academic coordinator
             [ ] Teacher

Functions    [ ] See student mobile numbers
             [ ] Adjust fee plans & discounts
             [ ] WhatsApp inbox
             [ ] WhatsApp bulk campaigns
             [ ] WhatsApp fee notices
             [ ] WhatsApp templates & live
```

Same permission engine. Relabel and regroup what Super Admin ticks. Point Home/tabs at the **job**, not at a WhatsApp function.

---

## What not to mix later

- Do not make “see mobile” part of Counsellor. A teacher may need attendance without parent numbers; a counsellor usually needs Dial — that stays a **toggle**, default off.
- Do not treat class lead vs subject teacher as a CRM job. That stays on the batch.
- Do not rewrite Filament or change fee/call/admission business rules as part of this split.

---

## Code map (current)

| Concern | Path |
|---------|------|
| Job enum (includes functions today) | [`app/Enums/StaffJobRole.php`](../app/Enums/StaffJobRole.php) |
| Permission matrix | [`app/Support/StaffRolePermissions.php`](../app/Support/StaffRolePermissions.php) |
| Permission names | [`app/Enums/CrmPermission.php`](../app/Enums/CrmPermission.php) |
| Access helpers + mobile toggle | [`app/Support/CrmAccess.php`](../app/Support/CrmAccess.php) |
| Dashboard / sidebar packs | [`app/Support/CrmNavigation.php`](../app/Support/CrmNavigation.php) |
| Phone bottom tabs | [`app/Support/CrmMobileBottomNav.php`](../app/Support/CrmMobileBottomNav.php) |
| Staff form | [`app/Filament/Resources/Staff/StaffResource.php`](../app/Filament/Resources/Staff/StaffResource.php) |
| Sync roles → permissions | [`app/Services/CrmPermissionSyncService.php`](../app/Services/CrmPermissionSyncService.php) |
| Tests | [`tests/Feature/CrmStaffRolesTest.php`](../tests/Feature/CrmStaffRolesTest.php), [`tests/Unit/CrmNavigationRolePacksTest.php`](../tests/Unit/CrmNavigationRolePacksTest.php) |

---

## When this is picked up

1. Confirm the five jobs and the function list (especially: adjust-fees without Accountant stays?).
2. Freeze the staff form: Jobs vs Functions.
3. Point mobile Home + five tabs at **jobs**; functions add extra tabs only when ticked.
4. Keep existing URLs and `CrmAccess` checks; migrate Fee adjuster / WhatsApp “roles” to direct permissions the same way mobile visibility already works.

Until then, assign staff with the current checkbox list. This file is the brief for the later change, not a live spec of the UI.
