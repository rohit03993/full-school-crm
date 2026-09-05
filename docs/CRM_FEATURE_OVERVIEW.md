# School CRM — Full Feature Overview

**Product:** Generic school & coaching institute CRM (rebrandable for any institute)  
**Stack:** Laravel 12 · PHP 8.2+ · Filament 5 · MySQL · Vite/Tailwind  
**Users:** Super Admin · Staff (role-based) · Students/Parents (portal) · Public website visitors  

**Related docs:** [`GENERIC_SCHOOL_CRM_PROJECT.md`](GENERIC_SCHOOL_CRM_PROJECT.md) · [`MODULE_ARCHITECTURE.md`](MODULE_ARCHITECTURE.md) · [`install/README.md`](install/README.md)

---

## 1. Product vision

End-to-end institute operations in one system:

```text
Enquiry → Lead → Visit → Admission → Enrollment → Fees → Batch
    → Attendance → Homework → Exams/Marks → Results → Reports
```

Each school gets its own install with configurable **name, logo, terminology, modules, and content** — not hard-coded to one brand.

**Example deployments:** Motion (`motionagra.in`), Horizon (`horizon.taskbook.co.in`), demo installs.

---

## 2. Three surfaces

| Surface | URL | Who uses it |
|--------|-----|-------------|
| **Admin CRM** | `/admin` | Office staff, teachers, counsellors, Super Admin |
| **Student/Parent Portal** | `/portal` | Enrolled students & parents (OTP/password login) |
| **Public website** | `/` | Walk-in enquiries, course info, contact |
| **Unified PWA** | `/app` | One installable app → routes to admin, portal, or website |

---

## 3. Module packaging (license ON/OFF)

Modules can be turned off per school without breaking the rest. Data is kept; UI hides.

### Always ON (Core)

| Module | Features |
|--------|----------|
| **Institute Core** | Login, Super Admin vault, staff accounts, job roles & permissions, Setup wizard, terminology, custom fields, institute settings, license UI, backups, audit |
| **Students** | All students list, **Student Profile hub** (central record), documents, batch assign, ID card PDF, bulk import |
| **Academics Core** | Courses, batches/sections, academic sessions, teaching assignments, fee installment templates |
| **Ask CRM** | AI staff assistant — answers from live student/fee/attendance data (respects module gates) |

### Licensed modules (sellable)

| Key | Formal name | Main features | Status |
|-----|-------------|---------------|--------|
| `enquiries` | Leads & Enquiries | All leads, My Leads, Find student, campus visits, follow-ups, lead assignment, visit pipeline, lead widgets, open leads aging report | Strong; some packaging polish left |
| `calls` | Calling | Call queue, call report (CSV/Excel), log calls (lead/enrolled/case), 3-strike block, profile call history | Complete enough |
| `admissions` | Admissions | Convert enquiry → admission, fee plan, approve/return, enrollment + roll number | Incomplete |
| `fees` | Fees | Fees dashboard, installments, collect payment, receipts PDF, misc charges, discounts/penalties, defaulters, payment cancel requests (Super Admin) | Complete enough |
| `attendance` | Attendance | Live punch, manual batch roster, staff attendance, RFID/ADMS, face-verify APIs, attendance display screen, leave reasons | Incomplete |
| `homework` | Homework | Assign homework, check Done/Not Done, review & WhatsApp to parents, portal homework view | Incomplete |
| `marks` | Marks & Exams | Activity types, exam sessions, exam windows, bulk marks import, profile exams tab | Complete enough |
| `results` | Results Declaration | Publish results to portal (depends on `marks`) | Complete enough |
| `marksheets` | Marksheets | PDF marksheets (depends on `marks`) | Complete enough |
| `whatsapp` | WhatsApp (Meta) | Templates, inbox, campaigns, automations, usage analytics, hooks from fees/attendance/homework/calls; **Parent fee notices** (manual per-student amount bulk WhatsApp, Fees not required) | Strong ops; settings cleanup ongoing |
| `portal` | Student Portal | Parent/student login, multi-child switch, dashboard, attendance, homework, fees/receipts, ID card, published marks | Incomplete |
| `reports` | Reports | Operational & academic report hub — CSV, Excel, PDF, 20-row preview pagination | Incomplete |
| `website` | Website CMS | Hero, logo, courses section, contact, public enquiry form | Incomplete |
| `cases` | Student Cases | All cases, My work cases, open/transfer/close, case call log | Live |
| `certificates` | Certificates | TC, bonafide, character, birth, fee certificates — PDF with serial numbers | Live V1 |

### Planned (not built yet)

Front office, notices, timetable, parent app, library, inventory, accounting, payroll, syllabus, leave, SMS, gallery — **transport/bus explicitly out of scope**.

---

## 4. Admin CRM — feature breakdown by area

### Dashboard

- Today pulse, attention items, finance stats, lead stats
- Batch overview, admissions charts, fee collection charts
- Pending admissions, recent enquiries, license status
- Role-based widgets (counsellor vs finance vs academic)

### Leads & Enquiries (`enquiries`)

- **Find student** — search or create new lead
- **All leads** — full pipeline with filters (assigned, visit status, source)
- **My Leads** — counsellor’s assigned leads
- **Campus visits** — walk-in log
- **Follow-ups** — call + visit follow-up dates
- Lead assignment, visit status pipeline, custom enquiry fields
- Website enquiry capture (when website ON)

### Calling (`calls`)

- **Call queue** — who to call next
- **Call report** — filters, summary, CSV/Excel export
- **Log call** from lead profile, student profile, or case
- Sync visit status on lead calls
- Student call history page

### Students (Core)

- **All students** — enrolled directory
- **Student Profile** — hub with lazy-loaded tabs:
  - Overview, fees, receipts, attendance, homework, exams, cases, certificates, documents, calls, ID card, etc. (tabs respect module gates)
- Bulk student import
- Assign to batch
- Generate ID card PDF

### Admissions (`admissions`)

- Admissions list & workflow
- Convert enquiry → admission with fee structure
- Approve / return for correction
- Creates enrollment + roll number on approval

### Academics (Core)

- **Courses** — school/coaching programmes
- **Batches / Class sections** — active batches per session
- **Academic sessions** — school years
- **Teaching assignments** — which teacher owns which batch/subject
- Installment templates per course

### Fees (`fees`)

- **Fees hub** — dashboard entry
- **Fees dashboard** — collections, defaulters, quick collect
- Per-student fee plan, installments, misc charges
- Collect payment (cash/online/UPI etc.), receipt PDF
- Bulk misc charges
- Fee settings (late fee, coaching GST agreement)
- **Payment cancellation requests** — staff request, Super Admin approves (soft cancel, never hard-delete)
- WhatsApp fee reminder automation (when WhatsApp ON)

### Attendance (`attendance`)

- **Attendance hub** — live vs manual
- **Live punches** — biometric/RFID/face punch → IN/OUT
- **Manual roster** — mark Present/Absent/Leave with preset leave reasons
- **Session attendance** — per activity session
- **Staff attendance** — staff punch IN/OUT
- **Attendance display** — public screen for lobby
- Face platform connect, device setup (ADMS `/iclock`)
- WhatsApp to parents on IN/OUT (when configured)

### Homework (`homework`)

- **Submit homework** — assign to batch/subject with attachments
- **Homework check** — Done / Not Done per subject
- **Homework review** — send WhatsApp to parents (not done / share)
- Homework history
- Portal view for students/parents

### Marks & Exams (`marks`, `results`, `marksheets`)

- **Activity types** — JEE test, unit test, etc.
- **Activity sessions** — schedule exams
- **Exam windows** — bulk exam periods
- **Activity attendance** — who sat the test
- Marks entry, bulk import
- **Results** — publish to portal
- **Marksheets** — PDF download
- Consolidated report cards

### WhatsApp (`whatsapp`)

- Meta Cloud API integration
- Template builder & approval sync
- **Automations** — attendance, fee reminders, homework, lead after-call, etc.
- Campaigns (bulk send)
- Inbox / message log
- Usage & analytics

### Reports (`reports`)

Auto-load results, optional filters, 20 rows/page preview, full export.

| Category | Reports |
|----------|---------|
| **Leads** | Enquiries (date range), Open leads — aging, Enquiry source-wise |
| **Admissions** | Admissions by course, Admissions by staff |
| **Attendance** | By batch, By student (student required), Daily absent sheet, Monthly %, Low attendance alert |
| **Marks** | Tests & exams summary, Test marks detail |
| **Fees** | Fee collection, Pending fees, Overdue installments, Discounts, Payment modes, Financial summary (Super Admin) |
| **Other** | Audit log, Homework check Done % |

Financial exports = **Super Admin only**.

### Cases (`cases`)

- **All cases** — institute-wide support/discipline cases
- **My work** — assigned cases
- Open, transfer, close workflow
- Case-linked call logging

### Certificates (`certificates`)

- Issue: Transfer Certificate, Bonafide, Character, Birth, Fee certificate
- PDF with institute branding + serial number
- History per student on profile

### Setup & Admin (Core)

- **Setup hub** — install guide for new schools
- Institute settings, terminology labels
- Custom fields (student + enquiry)
- Staff import, role permissions
- Face/biometric platform, attendance devices
- Push notification settings (Web Push / PWA)
- Site content (when `website` ON)
- Backups, audit logs, staff login sessions
- Subscription/license management (vendor platform panel)

### Other staff tools

- **My account** — change password/mobile
- **My meetings** — visit/meeting assignments
- **Ask CRM** — natural-language queries
- **Accounting ledger** page (basic)
- Mobile-responsive admin — tables become cards on phone

---

## 5. Student / Parent Portal (`portal`)

When licensed and modules enabled:

- Login: mobile + password or OTP
- Multi-child switch (same parent mobile)
- Dashboard summary
- **Attendance** tab (if `attendance` ON)
- **Homework** (if `homework` ON)
- **Fees & receipts** download (if `fees` ON)
- **Published marks/results** (if `marks`/`results` ON)
- ID card view
- Installable PWA (`/app`)

**Web Push alerts:** fee reminders, attendance IN/OUT, homework published, marks published, case updates.

---

## 6. Public Website (`website`)

- Configurable hero, logo, favicon, about, courses section
- Contact / enquiry form → creates lead in CRM
- Institute name & branding from admin (not hard-coded)
- Same PWA manifest as admin/portal

---

## 7. Integrations & hardware

| Integration | Purpose |
|-------------|---------|
| **Meta WhatsApp Cloud API** | Parent/staff messaging |
| **Biometric / RFID (ADMS)** | Device punch → student attendance |
| **Face verification API** | Camera punch attendance |
| **Web Push (VAPID)** | PWA lock-screen notifications |
| **DomPDF** | Receipts, ID cards, certificates, reports |
| **Excel export** | Reports, call report, marks import |

---

## 8. Security & business rules

- **Spatie Permission** — Super Admin, Staff roles, granular CRM permissions
- One mobile = one student record
- Payments **immutable** for staff; corrections via Super Admin cancel flow
- Documents in **private storage** only
- Enrollment number: `{PREFIX}-YYYY-XXXXXX`
- Module `FeatureGate` — disabled module hides nav, profile tabs, portal sections, reports
- Separate database per school install (never shared between institutes)

---

## 9. Automation & scheduled jobs (examples)

- Fee reminder WhatsApp + portal push
- Staff follow-up digest push (daily 08:30)
- Lead/case assignment push to assignee
- Storage cleanup cron
- Queue workers for WhatsApp sends

---

## 10. Module dependency map

```text
results ──requires──► marks
marksheets ──requires──► marks
parent_app (planned) ──requires──► portal

WhatsApp hooks need: parent module ON + whatsapp ON
Portal sections need: portal ON + sibling module ON
```

Turning OFF one module does not delete its data.

---

## 11. Navigation hubs (admin sidebar)

Dense areas use **one sidebar entry**; detail screens stay reachable from the hub:

| Hub | Group | Examples |
|-----|-------|----------|
| Homework | Academics | Submit / Review / Check / History |
| Attendance | Academics | Live punches / Manual batch / Staff |
| Fees | Students | Dashboard / Bulk charges / Adjustments |
| WhatsApp | WhatsApp | Inbox / Campaigns / Templates / Automations / Usage / Setup |
| Setup | Setup | Guide / Institute / Terminology / Devices / Backups |

---

## 12. What’s complete vs still evolving

| Complete enough for production | Still incomplete / planned |
|-------------------------------|----------------------------|
| Fees collection & receipts | Online payment gateway |
| Calling & call reports | VoIP / call recording |
| Marks, results, marksheets | Full LMS / online exams |
| Certificates V1 | Portal certificate download |
| WhatsApp Meta templates & automations | SMS module |
| Reports hub + exports | More dashboard tiles |
| Student profile hub | Parent native app (PWA exists) |
| Attendance punch + manual | Advanced timetable |
| Lead pipeline + aging report | Salesforce-style automation builder |

---

## 13. Key commands

```powershell
cd "F:\Rohit Development\Full school soft\school-crm"
php artisan serve
php artisan crm:ensure-admin
php artisan crm:publish-assets    # Required on CloudPanel deploy
php artisan crm:sync-permissions
php artisan test
```

---

## 14. Module snapshot

```text
CORE (always ON)
├── Institute Core
├── Students Management
├── Academics Core (courses, batches, sessions)
└── Ask CRM

LIVE LICENSED MODULES
├── Leads & Enquiries      (enquiries)
├── Calling                (calls)
├── Admissions             (admissions)
├── Fees                   (fees)
├── Attendance             (attendance)
├── Homework               (homework)
├── Marks & Exams          (marks)
├── Results                (results)
├── Marksheets             (marksheets)
├── WhatsApp               (whatsapp)
├── Student Portal         (portal)
├── Reports                (reports)
├── Website CMS            (website)
├── Student Cases          (cases)
└── Certificates           (certificates)

PLANNED
├── Front office / Notices / Timetable / Parent app
├── Library / Inventory / Accounting / Payroll / Syllabus / Leave / SMS
└── Gallery / Online exams (later) — NO bus/transport
```

---

*Last updated: August 2026 — align with [`MODULE_ARCHITECTURE.md`](MODULE_ARCHITECTURE.md) for packaging details.*
