# Module architecture — School CRM (full catalog)

**Status:** Source of truth for modular packaging  
**Date:** August 2026  
**Rule:** Turn one module OFF → only that module hides; data is kept; other modules keep working.  
**Skipped:** Transport / bus GPS (explicitly out of scope).

Foundation in code:

| Piece | Path |
|-------|------|
| Module keys | [`app/Enums/LicenseFeature.php`](../app/Enums/LicenseFeature.php) |
| Gate | [`app/Support/FeatureGate.php`](../app/Support/FeatureGate.php) |
| Plans | [`config/license.php`](../config/license.php) |
| License engine | [`app/Services/LicenseService.php`](../app/Services/LicenseService.php) |
| Profile tabs | [`app/Filament/Pages/StudentProfilePage.php`](../app/Filament/Pages/StudentProfilePage.php) |

**Defaults:** Cases is a sellable module; Ask CRM stays Core; one install per school (V1); parent reach = harden Portal then PWA.

### Navigation hubs

Dense areas expose **one sidebar entry**; leaf screens stay reachable by URL and hub cards:

| Hub | Sidebar group | Leaves (examples) |
|-----|---------------|-------------------|
| Homework | Academics | Submit / Review / Check / History |
| Attendance | Academics | Live punches / Manual batch / Staff |
| Fees | Students | Dashboard / Bulk charges / Adjustments |
| WhatsApp | WhatsApp | Inbox / Campaigns / Templates / Automations / Usage / Setup |
| Setup | Setup | Guide / Institute / Terminology / Devices / Backups |

Turning a license module OFF hides that hub and its leaves only. Shared layout: `resources/views/filament/pages/partials/crm-hub.blade.php`. Role packs (`CrmNavigation::navRolePacks`) collapse unused sidebar groups (union of roles) and merge dashboard hero / attention / pulse / quick actions from every assigned job role.

### Mobile list cards

Below `lg` (1024px) every `<x-crm.responsive-table>` renders as a stack of cards instead of a scrolling table — no horizontal scroll anywhere in the admin panel. Per `<td>`:

| Marker | Effect in the card |
|--------|--------------------|
| `data-label="Course"` | small label above the value |
| `data-label=""` | value only, no label |
| `crm-responsive-table__title` | card header, hoisted to the top |
| `crm-responsive-table__actions` | footer, buttons stretched to 44px tap targets |
| `crm-responsive-table__wide` | full width instead of half (prose, long values) |

Facts fill a two-column grid, so keep cells short or mark them wide; cells holding inputs, selects, textareas or nested tables go full width automatically. Card order is independent of desktop column order.

Shared mobile chrome (defined in `resources/css/filament/admin/theme.css`, used through components in `resources/views/components/crm/`): `x-crm.stat-tiles` (`.crm-stat*`), `x-crm.badge` (`.crm-badge--gray|success|info|warning|danger`), `x-crm.avatar`, plus `.crm-seg*` segmented controls and `.crm-chip*` filter chips. `resources/views/filament/pages/partials/campus-visits.blade.php` is the reference implementation. Enum tones (e.g. `VisitStatus::tone()`) feed `x-crm.badge` so statuses read the same everywhere.

### Installable PWA (per institute)

**One home-screen app per school** — not separate Admin / Portal / Website apps.

| Piece | Behaviour |
|-------|-----------|
| Name | `{Institute}` (e.g. Motion Education) |
| Short label | First brand word, ≤12 chars |
| Icon | Favicon first, then logo, else initials |
| Theme colour | ID-card primary colour |
| Install from | Website, Admin, or Portal — all offer the **same** app |
| Opens to | `/app` — smart entry |

`/app` routes by login:

1. Staff signed in (`auth`) → `/admin`
2. Parent/student portal session → `/portal`
3. Guest → chooser: Staff/Admin, Parent/Student (if Portal licensed), Visit website

Manifest: `PwaManifestService` (`/pwa/manifest`). Legacy `/pwa/manifest/admin|portal|public` URLs still return the same unified payload. Service worker (`public/sw.js`) + `public/offline.html` give a clean offline screen.

### Web Push (PWA notifications)

Optional lock-screen alerts for the installed app (staff + portal):

| Piece | Detail |
|-------|--------|
| Package | `minishlink/web-push` |
| Keys | `php artisan crm:webpush-vapid` → `.env` `VAPID_*` |
| Store | `push_subscriptions` (`user_id` staff / `student_id` portal) |
| Subscribe | `/pwa/push/subscribe` after Allow notifications |
| Fee reminders | `crm:send-fee-reminders` also sends portal push (even if WhatsApp is off) |
| Staff follow-ups | `crm:send-push-followup-digest` daily 08:30 |
| Staff assign | Lead / visit / case assign → push to assignee |
| Portal events | Attendance IN/OUT, homework published, marks published, case call update |
| Toggle UI | Setup → **Push notifications** (`PushSettings`, default on) |

---

## How to read each module

Every management system below lists:

- **Formal name** — what you sell / label in license UI  
- **Key** — `LicenseFeature` value (or Core)  
- **Includes** — screens owned by the module  
- **Status** — Complete / Incomplete / Planned  
- **Works today** — what already works  
- **Omit / Edit / Add** — packaging decisions

---

# Part 1 — Core (always ON)

## 1. Institute Core Management

| | |
|--|--|
| **Key** | none (always on) |
| **Status** | Complete enough for production |
| **Includes** | Login, Super Admin vault (staff accounts, Setup, waive approval, All cases, audit, backups), staff job roles (`StaffResource`, `CrmPermission`, `StaffRolePermissions`), Setup wizard, Terminology, Custom fields, Institute settings; vendor license UI on Platform panel |
| **Omit** | Multi-tenant shared DB (V1) |
| **Edit** | Document Super Admin–only vault vs staff job roles (Teacher, Academic publish, Messaging ops, ReportsExport) |
| **Add** | Optional school-facing “Modules enabled” read-only summary |

## 2. Students Management System

| | |
|--|--|
| **Key** | none (core) — profile tabs gate by other modules |
| **Status** | Complete as hub |
| **Includes** | All students, Student profile hub, Documents tab, batch assign, ID card, import students |
| **Omit** | Separate sellable “Documents Management” module |
| **Edit** | Docs: Find student = lead entry (`enquiries`); All students = enrolled directory |
| **Add** | Nothing critical for packaging |

## 3. Academics Core (Classes and Batches)

| | |
|--|--|
| **Key** | none (always on) |
| **Status** | Complete substrate |
| **Includes** | Courses, batches/sections, academic sessions, teaching assignments, installment templates |
| **Omit** | Selling Academics Core as optional (breaks attendance/homework/marks) |
| **Edit** | Keep Course/Batch resources behind Classes UX |
| **Add** | Timetable / Syllabus as **separate** future modules |

## 4. Ask CRM (staff assistant)

| | |
|--|--|
| **Key** | none — Core |
| **Status** | Built (widget / page) |
| **Includes** | Ask CRM page/widget + student/staff data services |
| **Omit** | Selling separately in V1 |
| **Edit** | Respect FeatureGate in answers (no Fees deep-link if Fees OFF) |
| **Add** | Module-aware prompts after Phase 0 |

---

# Part 2 — Already built licensed modules

## 5. Leads and Enquiries Management System

| | |
|--|--|
| **Formal name** | Leads and Enquiries Management System |
| **Key** | `enquiries` |
| **Status** | Incomplete (strong ops; packaging gaps) |
| **Includes** | All leads, My Leads, Campus visits, Follow-ups (visit side), lead assign, enquiry create (Find student / website), visit status pipeline, lead widgets |
| **Works today** | Mobile-first enquiry, assignment, visit log, institute visits, meeting assign/close, custom fields |
| **Omit** | Salesforce automation builder; VoIP |
| **Edit** | Gate Find student with `enquiries` (or split directory search vs create lead); dual-gate Follow-ups (`enquiries` OR `calls`); decouple My work from Enquiries-only |
| **Add** | Optional lead aging report when Enquiries ON |

**Key files:** `StudentSearchPage`, `EnquiryResource`, `MyLeadsPage`, `CampusVisitsPage`, `FollowUpsPage`, `EnquiryService`, `LeadAssignmentService`

---

## 6. Calling Management System

| | |
|--|--|
| **Formal name** | Calling Management System |
| **Key** | `calls` |
| **Status** | Complete enough (CSV export + dual-mode Setup Guide live; VoIP omitted) |
| **Includes** | Call Queue, Call Report, Students → Calls, profile Calls, dual-mode Log call (lead / enrolled / case), 3-strike block, calling widgets |
| **Works today** | `tel:` dial + log; lead VisitStatus sync; enrolled purpose-as-tag; enrolled out of lead queue; Students Calls filters; Call Report CSV (filtered + Excel UTF-8) |
| **Omit** | VoIP, recording, predictive dialer, bus |
| **Edit** | Document `LeadsCall` vs `StudentsView`; Follow-ups call rows respect `calls` |
| **Add** | Optional purpose deep-link later |

**Key files:** `CallQueuePage`, `CallReportPage`, `StudentCallsPage`, `CallLogService`, `HandlesLogCallModal`

---

## 7. Admissions Management System

| | |
|--|--|
| **Formal name** | Admissions Management System |
| **Key** | `admissions` |
| **Status** | Incomplete |
| **Includes** | Admissions resource, convert-to-admission, fee plan at conversion, approve/return, enrollment + roll, pending widgets |
| **Works today** | Staff-driven admission → enrollment |
| **Omit** | Entrance-test LMS inside Admissions |
| **Edit** | Clarify Reject vs return-for-correction |
| **Add** | Later: parent application on Portal if Admissions + Portal ON |

---

## 8. Fees Management System

| | |
|--|--|
| **Formal name** | Fees Management System |
| **Key** | `fees` |
| **Status** | Complete enough for packaging (collect UX + payment cancel requests) |
| **Includes** | Fees dashboard, profile Fees/Receipts, installments, misc charges, adjustments, **payment cancellation requests** (latest active only → Super Admin approve; soft cancel + void receipt + ledger reverse), fee settings, WA reminder hooks |
| **Works today** | Office collect, receipts PDF, discounts/penalties, GST coaching settings, defaulters with Collect deep-link; staff request cancel mistaken payment (never hard-delete) |
| **Omit** | Full Tally inside Fees (expense accounting = separate module); cancel of non-latest payments |
| **Edit** | Gate `ManageFeeSettings` with `fees`; portal Fees only if `fees` ON |
| **Add** | Online pay later |

---

## 9. Attendance Management System

| | |
|--|--|
| **Formal name** | Attendance Management System |
| **Key** | `attendance` (biometric/face = sub-features, not separate SKUs) |
| **Status** | Incomplete |
| **Includes** | Live/manual batch attendance, staff attendance, ADMS `/iclock`, face-verify APIs, display, WA hooks |
| **Works today** | Punch → attendance; RFID; camera-punch API ready |
| **Omit** | Separate Face/Biometric license SKUs in V1 |
| **Edit** | Gate device/face/setup + display with `attendance` |
| **Add** | Camera Mode B Android only when a client needs it |

---

## 10. Homework Management System

| | |
|--|--|
| **Formal name** | Homework Management System |
| **Key** | `homework` |
| **Status** | Incomplete |
| **Includes** | Assignments, Homework check, profile Homework, WA share, portal homework |
| **Works today** | Assign/check Done–Not Done, attachments |
| **Omit** | Full LMS grading |
| **Edit** | Portal homework only if `homework` ON |
| **Add** | Optional student submit later |

---

## 11. Marks and Exams Management System

| | |
|--|--|
| **Formal name** | Marks and Exams Management System |
| **Key** | `marks` |
| **Status** | Complete enough for packaging |
| **Includes** | Activity types/sessions, exam windows, bulk import, profile Exams |
| **Omit** | Live class streaming; online exams (separate/later) |
| **Edit** | Hard-dep messaging when Results ON but Marks OFF |
| **Add** | — |

---

## 12. Results Declaration Management System

| | |
|--|--|
| **Formal name** | Results Declaration Management System |
| **Key** | `results` |
| **Hard dep** | `marks` |
| **Status** | Complete enough |
| **Edit / Add** | Portal marks only when `results` and/or `marks` ON |

---

## 13. Marksheets Management System

| | |
|--|--|
| **Formal name** | Marksheets Management System |
| **Key** | `marksheets` |
| **Hard dep** | `marks` |
| **Status** | Complete enough |
| **Omit** | DigiLocker V1 |

---

## 14. WhatsApp Management System

| | |
|--|--|
| **Formal name** | WhatsApp Management System |
| **Key** | `whatsapp` |
| **Status** | Incomplete (ops strong; settings cleanup) |
| **Includes** | Meta templates, inbox, campaigns, analytics; soft hooks from fees/attendance/homework/calls/results; **Parent fee notices** (manual bulk pending-fee WhatsApp — independent of Fees ledger) |
| **Omit** | AiSensy; bus alerts |
| **Edit** | Single WhatsApp settings path; docs = Meta only |
| **Add** | SMS as separate module (`sms`) |

**Parent fee notices:** Staff pick a batch, type amount + due date per student, send via approved template. Logged on Student Profile → Parent updates. Does **not** read or write `fee_installments` / payments. Requires `whatsapp` only (Fees may be OFF).

---

## 15. Student Portal Management System

| | |
|--|--|
| **Formal name** | Student Portal Management System |
| **Key** | `portal` |
| **Status** | Incomplete |
| **Works today** | Parent & student login (password/OTP), multi-child switch via mobile/alternate mobile, dashboard, attendance tab, homework, receipts/ID, published marks, unified PWA `/app` |
| **Omit** | Treating Portal as native parent app |
| **Edit** | Each portal section checks its sibling module key |
| **Add** | Notices/certificates when those modules exist; then Parent App |

---

## 16. Reports Management System

| | |
|--|--|
| **Formal name** | Reports Management System |
| **Key** | `reports` |
| **Status** | Incomplete |
| **Works today** | Large report hub CSV/Excel/PDF |
| **Edit** | Filter report catalog by sibling FeatureGates |
| **Add** | Calling reports only if `calls` ON |

---

## 17. Website CMS Management System

| | |
|--|--|
| **Formal name** | Website CMS Management System |
| **Key** | `website` |
| **Status** | Incomplete |
| **Works today** | Admin Site Content gated |
| **Edit** | Public site fallback when `website` OFF |
| **Omit** | Full multi-page CMS beyond home/courses/contact V1 |

---

## 18. Student Cases Management System

| | |
|--|--|
| **Formal name** | Student Cases Management System |
| **Key** | `cases` |
| **Status** | Built functionally; packaging Incomplete → becoming Live |
| **Includes** | All cases, My work cases tabs, profile Cases, open/transfer/close, case call log |
| **Omit** | Full ITIL helpdesk |
| **Edit** | License key + gates; My work accessible when Cases OR Enquiries OR Calls |
| **Add** | Distinct case types (later) |

### 19. Certificates Management System

| | |
|--|--|
| **Formal name** | Certificates Management System |
| **Key** | `certificates` |
| **Status** | Live (V1) |
| **Includes** | Students → Certificates page, profile Certificates tab, issue PDF (TC / bonafide / character / birth / fee), history + preview/download |
| **Works** | DomPDF with institute branding; serial numbers; enrolled students only |
| **Incomplete** | Portal download; custom certificate templates; DigiLocker |
| **Omit** | DigiLocker V1 |
| **Edit** | — |
| **Add** | Portal surface when Portal + Certificates ON (later) |

---

# Part 3 — Planned modules (not built)

| Formal name | Key | Priority | Add | Omit |
|-------------|-----|----------|-----|------|
| Front Office Management System | `front_office` | P1 | Visitors, gate pass, short leave | Photo kiosk V1 optional |
| Notices Management System | `notices` | P1 | Circulars → staff + portal | Full newsletter |
| Timetable Management System | `timetable` | P1 | Timetable + basic substitution | AI scheduler |
| Parent Mobile App | `parent_app` | P1 | PWA/API on Portal | Bus tracking |
| Library Management System | `library` | P2 | Catalogue, issue/return | Advanced barcode V1 optional |
| Inventory Management System | `inventory` | P2 | Stock movements | WMS |
| Expense Accounting Management System | `accounting` | P2 | Day-to-day expenses | Full GST ERP |
| Payroll Management System | `payroll` | P2 | Salary cycles | Complex payroll compliance V1 |
| Syllabus and Planner | `syllabus` | P2 | Monthly planner + tracking | — |
| Leave Management System | `leave` | P2 | Student/staff leave | — |
| SMS Management System | `sms` | P2 | Transactional SMS | Replace WhatsApp |
| Gallery | `gallery` | P3 | Albums | — |
| Online Exams / Live Classes | — | P3 | Prefer external tools | Full LMS now |
| Transport / Bus GPS | — | **Skip** | — | Entire module |

---

# Part 4 — Gating rules

1. Nav + `canAccess` + profile tab + portal section + report types + Ask CRM links check the module key.  
2. Disable does not delete data.  
3. Soft hooks (WhatsApp) check **both** parent module and `whatsapp`.  
4. Hard deps: `results` / `marksheets` → `marks`; `parent_app` → `portal`.

```text
Turn OFF WhatsApp  → Fees ✅ Calls ✅ Attendance ✅
Turn OFF Calls     → Enquiries ✅ Fees ✅
Turn OFF Fees      → Students ✅ Admissions ✅
Turn OFF Cases     → Students ✅ Calls ✅ (no Cases UI)
```

---

# Part 5 — Delivery phases

| Phase | Work |
|-------|------|
| **0a** | `cases` license feature + gate Cases; fix My work coupling |
| **0b** | Gate Find student, fee settings, biometric setup, portal child tabs, website public, reports-by-sibling |
| **0c** | Call Report CSV + Setup Guide dual-mode copy |
| **1** | Certificates Management System (first new gated module) |
| **1+** | Notices, Front office, Timetable… |

---

# Part 6 — Snapshot

```text
CORE
├── Institute Core
├── Students MS
├── Academics Core
└── Ask CRM

LIVE LICENSED
├── Leads & Enquiries MS   (enquiries)
├── Calling MS             (calls)
├── Admissions MS          (admissions)
├── Fees MS                (fees)
├── Attendance MS          (attendance)
├── Homework MS            (homework)
├── Marks & Exams MS       (marks)
├── Results MS             (results)
├── Marksheets MS          (marksheets)
├── WhatsApp MS            (whatsapp)
├── Student Portal MS      (portal)
├── Reports MS             (reports)
├── Website CMS MS         (website)
├── Student Cases MS       (cases)
└── Certificates MS        (certificates)

PLANNED
├── Front office / Notices / Timetable / Parent app
├── Library / Inventory / Accounting / Payroll / Syllabus / Leave / SMS
└── Gallery / Online exams (later) — NO bus
```

---

## Changelog

| Date | Change |
|------|--------|
| 2026-09-05 | WhatsApp: Parent fee notices (manual bulk amounts) — independent of Fees; profile Parent updates tab |
| 2026-08-15 | Nav hubs: Attendance / Fees / WhatsApp / Setup = one sidebar entry each; leaf pages stay URL-reachable and module-gated. Homework hub pattern is the reference. |
| 2026-08-10 | Initial short architecture |
| 2026-08-10 | Restructured already-built vs planned |
| 2026-08-10 | Full catalog: formal MS names, omit/edit/add, Phase 0–1 |
| 2026-08-10 | Phase 0 gates + Certificates Management System V1 |
