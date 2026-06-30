# Generic School & Coaching CRM — Project Brief

**Version:** 1.0  
**Date:** June 2026  
**Status:** Forked from Folks India — genericization in progress  
**Workspace path:** `F:\Rohit Development\Full school soft\school-crm`

---

## For Cursor / AI agents — read this first

When this folder is opened as the workspace, treat this as a **separate product** from Folks India.

| Rule | Detail |
|------|--------|
| **This project** | Generic multi-tenant-ready school & coaching CRM (working title: **School CRM**) |
| **Do not modify** | `F:\Rohit Development\Folks India` unless the user explicitly opens that workspace |
| **Source lineage** | Cloned from [folksindia](https://github.com/rohit03993/folksindia) — same Laravel/Filament codebase, different branding & config |
| **Primary doc** | This file (`docs/GENERIC_SCHOOL_CRM_PROJECT.md`) |
| **Deep reference** | Original plan still in repo: `docs/FOLKS_INDIA_MASTER_PLAN.md` (rename/replace over time) |
| **User preference** | User runs terminal commands themselves unless they ask the agent to run them |
| **Database** | Always use a **separate** database — never `folks_india` |

### How to help effectively

1. Read this brief + user message before coding.
2. Keep changes scoped — generic product goals, not Folks-only copy.
3. Prefer config (`config/`, `.env`, institute settings) over hard-coded institute names.
4. Do not commit `.env` or push to `folksindia` remote unless user asks.
5. For deploy issues on CloudPanel: `public/vendor/livewire` must exist (nginx blocks dynamic Livewire routes).

---

## 1. Executive summary

### Vision

Build a **generic CRM/ERP** that any **school, college, or coaching institute** can use:

- Enquiry → admission → enrollment → fees → batches → attendance → activities → reports
- Staff admin panel (Filament) + student portal + public website
- Institute name, logo, contact, and branding configurable per deployment (not hard-coded “Folks India”)

### Relationship to Folks India

| Item | Folks India | This project (School CRM) |
|------|-------------|---------------------------|
| Folder | `F:\Rohit Development\Folks India` | `F:\Rohit Development\Full school soft\school-crm` |
| Production URL | `https://folksindia.org` | TBD (new domain later) |
| Git remote (current) | `rohit03993/folksindia` | Same clone — **change remote** when new repo is created |
| Database | `folks_india` (production) | **New DB** e.g. `school_crm` locally |
| Purpose | Single institute (Folks India) | Product for many schools/coachings |

**Nothing in Folks India is affected** by work done only in this folder.

---

## 2. Tech stack (inherited)

| Layer | Technology |
|-------|------------|
| Backend | Laravel 12, PHP 8.2+ |
| Admin UI | Filament 5 |
| Database | MySQL 8 |
| Frontend build | Vite, Tailwind (Filament theme) |
| PDF | DomPDF (receipts, ID cards, reports) |
| Excel export | maatwebsite/excel |
| Auth | Spatie Permission (Super Admin, Staff, Student portal) |
| QR | simplesoftwareio/simple-qrcode |

---

## 3. Local setup (Windows)

Run all commands **inside this project folder**:

```powershell
cd "F:\Rohit Development\Full school soft\school-crm"
```

### First-time setup

```powershell
copy .env.example .env
composer install
npm install
npm run build
php artisan key:generate
```

### Configure `.env` (this project only)

```env
APP_NAME="School CRM"
APP_URL=http://localhost:8000

DB_DATABASE=school_crm
DB_USERNAME=root
DB_PASSWORD=

ADMIN_MOBILE=9876543210
ADMIN_PASSWORD=Admin@2026
```

Create the MySQL database `school_crm` in phpMyAdmin/XAMPP **before** migrate.

### Database & admin

```powershell
php artisan migrate --seed
php artisan crm:ensure-admin
php artisan serve
```

| URL | Purpose |
|-----|---------|
| http://localhost:8000 | Public site |
| http://localhost:8000/admin | Staff CRM |
| http://localhost:8000/portal/login | Student portal |

Default admin (after `crm:ensure-admin`): see `ADMIN_MOBILE` / `ADMIN_PASSWORD` in `.env`.

---

## 4. Project structure (key paths)

```
school-crm/
├── app/
│   ├── Filament/          # Admin panel (resources, pages, widgets)
│   ├── Services/          # Business logic
│   ├── Models/
│   └── Console/Commands/
│       ├── CrmEnsureAdminCommand.php
│       └── CrmPublishAssetsCommand.php
├── config/
│   └── folks.php          # Institute defaults — rename/genericize later
├── database/seeders/
│   ├── AdminUserSeeder.php
│   └── DemoDataSeeder.php
├── docs/
│   ├── GENERIC_SCHOOL_CRM_PROJECT.md   ← this file
│   └── FOLKS_INDIA_MASTER_PLAN.md      ← legacy reference
├── public/vendor/livewire/             ← required for CloudPanel deploy
└── resources/views/
```

---

## 5. What already works (from Folks India fork)

All modules below exist in code; genericization = branding, config, and optional feature flags.

- Dashboard, enquiries, visits, admissions, documents
- Student profile (hub), fees, receipts, ID cards
- Batches, attendance, practicals, industrial visits, seminars
- Reports + Excel export
- Institute settings (logo, header/footer for PDFs)
- Audit log, storage cleanup cron
- Student portal (mobile + DOB login)
- Public website + site content CMS

---

## 6. Genericization roadmap (planned work)

Use this section to steer the agent when opening this project.

### Phase G1 — Branding & naming

- [x] Replace “Folks India” strings with `APP_NAME` / institute settings
- [x] Rename `config/folks.php` → `config/institute.php` (or similar)
- [x] Update Filament `brandName()` to use DB settings
- [x] Generic public site copy (hero, about, contact)
- [x] Default seeders use neutral demo data (not Folks-specific)

### Phase G2 — Multi-institute readiness (optional later)

- [ ] Single deployment per school (V1) vs multi-tenant (V2) — **decide with user**
- [x] Environment-based branding only for V1
- [x] Document install guide for new schools (`docs/INSTALL_AND_CUSTOMIZE_GUIDE.md`)

### Phase G3 — Product packaging

- [ ] New GitHub repo (not `folksindia`)
- [ ] README for installers
- [ ] Deployment doc (CloudPanel checklist from Folks India learnings)
- [x] Optional: installer wizard or setup command (First-run setup wizard)

### Phase G4 — Coaching-specific tweaks (if needed)

- [x] Course types: school vs coaching batches
- [x] Fee plans / installment labels
- [x] Custom fields per institute (student + enquiry)

---

## 7. Git strategy

### Current state

- Cloned from `https://github.com/rohit03993/folksindia.git`
- Same commit history as Folks India at clone time

### Recommended next steps

```powershell
cd "F:\Rohit Development\Full school soft\school-crm"
git remote -v
# When new repo exists:
# git remote remove origin
# git remote add origin https://github.com/YOUR_USER/school-crm.git
# git push -u origin main
```

**Do not push generic experiments to `folksindia`** unless intentionally merging back.

---

## 8. Deployment checklist (CloudPanel / VPS)

Learned from Folks India production deploy:

| Step | Action |
|------|--------|
| 1 | Point domain A record to server IP |
| 2 | CloudPanel site root: `/home/USER/htdocs/DOMAIN/public` |
| 3 | `git clone` or `git pull` into site folder |
| 4 | `composer install --no-dev` |
| 5 | `npm install && npm run build` |
| 6 | `cp .env.example .env` — set `APP_URL`, DB, `ADMIN_*` |
| 7 | `php artisan key:generate` |
| 8 | `php artisan migrate --seed --force` |
| 9 | `php artisan crm:publish-assets` (Livewire static JS) |
| 10 | `php artisan crm:ensure-admin` |
| 11 | `chown -R siteuser:siteuser .` + `chmod -R 775 storage bootstrap/cache` |
| 12 | SSL (Let’s Encrypt) |
| 13 | Cron: `* * * * * php artisan schedule:run` |
| 14 | Open firewall ports 80, 443, 22 |

**Login broken / eye icon dead?** → Check `https://DOMAIN/vendor/livewire/livewire.min.js` returns JS (not 404).

---

## 9. Useful artisan commands

| Command | Purpose |
|---------|---------|
| `php artisan serve` | Local dev server |
| `php artisan migrate --seed` | Fresh DB + roles, courses, admin |
| `php artisan crm:ensure-admin` | Create/reset Super Admin + check storage |
| `php artisan crm:publish-assets` | Publish Livewire + Filament static assets |
| `php artisan crm:cleanup` | Storage cleanup (scheduled daily) |
| `php artisan test` | Run PHPUnit tests |
| `npm run build` | Build Vite assets → `public/build` |
| `php artisan config:clear` | After `.env` changes |

---

## 10. Business rules (unchanged from Folks India)

- One mobile number = one student record
- Student Profile is the operational hub (lazy-loaded tabs)
- Payments immutable for Staff; corrections Super Admin only
- Documents in private storage only
- Enrollment number format: `{PREFIX}-YYYY-XXXXXX` — prefix from **Website → Site Content** (`number_prefix`, default `CRM`)

---

## 11. Testing

```powershell
cd "F:\Rohit Development\Full school soft\school-crm"
php artisan test
```

Key tests include: `FilamentAdminLoginTest`, `AdmissionWorkflowTest`, `StaffEnquiryTest`.

---

## 12. Agent session starter prompt

Copy into Cursor when beginning work on this project:

```text
Workspace: F:\Rohit Development\Full school soft\school-crm
Read docs/GENERIC_SCHOOL_CRM_PROJECT.md first.
This is the generic School/Coaching CRM fork — NOT Folks India production.
Do not change F:\Rohit Development\Folks India.
Goal: [describe task, e.g. rebrand to generic institute name, add feature X]
```

---

## 13. Contacts & decisions log

| Date | Decision |
|------|----------|
| 2026-06-18 | Forked Folks India repo into `Full school soft\school-crm` for generic product |
| TBD | Product name finalized |
| TBD | New GitHub repository |
| TBD | First non-Folks pilot school/coaching |

---

## 14. Related files

| File | Purpose |
|------|---------|
| `docs/FOLKS_INDIA_MASTER_PLAN.md` | Full original specification (2348 lines) |
| `.env.example` | Environment template |
| `README.md` | Laravel default readme — update for this product |

---

*Update this document when major decisions are made (product name, repo URL, multi-tenant approach).*
