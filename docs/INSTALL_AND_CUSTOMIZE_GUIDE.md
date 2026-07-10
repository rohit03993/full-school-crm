# School CRM — Install & Customize Guide

**For:** schools, coaching institutes, and colleges  
**One installation = one institute.** Configure everything from the admin panel — no code edits required.

Share this document with whoever installs or runs the CRM.

---

## Quick summary

| You want to… | Do this in admin |
|--------------|------------------|
| Set institute name, logo, phone | **Website → Site Content** |
| First-time setup | **`/admin/setup`** (wizard — Super Admin only) |
| Rename “Course”, “Batch”, “Roll No.” | **Setup → Terminology** |
| Add extra student fields | **Setup → Custom Fields** |
| Add classes / programmes | **Academics → Courses** |
| Create batches / sections | **Academics → Batches** |
| WhatsApp messages | **Setup → WhatsApp Settings** |
| Receipt / PDF branding | **Setup → Institute Settings** |

**Do not** edit `.env` for institute name, phone, or courses — use the admin screens above.

---

## Part 1 — Installation (IT person)

### Requirements

- PHP 8.2+
- MySQL 8
- Composer
- Node.js 18+ (for building frontend assets)
- Web server (Apache/nginx) or `php artisan serve` for testing

### Steps

```powershell
# 1. Copy project to server or PC
cd "path\to\school-crm"

# 2. Environment (server settings only)
copy .env.example .env
# Edit .env: DB_DATABASE, DB_USERNAME, DB_PASSWORD, APP_URL, ADMIN_MOBILE, ADMIN_PASSWORD

# 3. Install dependencies
composer install
npm install
npm run build

# 4. Application key & database
php artisan key:generate
# Create empty MySQL database first (e.g. school_crm)
php artisan migrate --seed

# 5. Admin user & assets
php artisan crm:ensure-admin
php artisan crm:publish-assets   # required on production (Livewire JS)

# 6. Queue worker (required for WhatsApp campaigns)
php artisan queue:work
```

On production, run the queue worker under **Supervisor** or systemd so it stays running (see **Part 5 — Production deployment** below).

### URLs after install

| URL | Purpose |
|-----|---------|
| `/` | Public website |
| `/admin` | Staff CRM (Filament) |
| `/admin/setup` | First-run wizard (once) |
| `/portal/login` | Student portal |

Login with `ADMIN_MOBILE` / `ADMIN_PASSWORD` from `.env`.

### Optional `.env` flags

| Variable | When to use |
|----------|-------------|
| `SEED_DEMO_DATA=true` | Only for **demo/training** installs — adds sample students & fees |
| `PAL_DIGITAL_*` | Optional; WhatsApp can be set in **Setup → WhatsApp Settings** instead |

**Ignore** `INSTITUTE_NAME`, `INSTITUTE_PHONE`, etc. in `.env` — those are configured in the admin panel.

---

## Part 2 — First login (Super Admin)

After login, if setup is not complete, you are sent to **Setup Wizard** (`/admin/setup`).

### Step 1 — Branding & contact

| Field | Example (school) | Example (coaching) |
|-------|-------------------|---------------------|
| Institute name | `Delhi Public School, Agra` | `Apex IIT Academy` |
| Tagline | `Excellence in Education` | `Your Path to IIT & NEET` |
| Record ID prefix | `DPS` | `APEX` |
| Phone / email | Reception desk | Front office |

**Record ID prefix** is used for enquiry numbers, admission numbers, and roll numbers (e.g. `DPS-ENQ-2026-000001`).

### Step 2 — Website hero

Text shown on the public homepage banner. You can change it anytime under **Website → Site Content**.

### Step 3 — Labels & wording

Match your institute’s language:

| Default | School might use | Coaching might use | College might use |
|---------|------------------|--------------------|-------------------|
| Course / programme | **Class** | **Programme** | **Degree / Programme** |
| Batch | **Section** | **Batch** | **Semester / Section** |
| Roll No. | **Roll No.** | **Registration No.** | **Enrollment No.** |

Click **Finish setup** — you can change all of this later under **Setup → Terminology**.

---

## Part 3 — Customize each area (after wizard)

### A. Public website

**Menu: Website → Site Content**

| Tab | What to set |
|-----|-------------|
| **Branding** | Logo, favicon, institute name, tagline, ID prefix |
| **Contact** | Phone, WhatsApp, email, address, office hours |
| **Hero** | Main banner title & subtitle, hero images |
| **About** | About paragraph + about image |
| **Statistics** | Homepage number blocks (e.g. “500+ Students”) |
| **Home Page** | About heading, bullet points, courses section toggle/text, bottom CTA |
| **Gallery** | Campus / classroom photos |
| **Social** | Facebook, Instagram, YouTube links |

**Tip:** Upload logo first — it appears on the website, admin panel, and receipts.

---

### B. Terminology (rename labels)

**Menu: Setup → Terminology**

| Label key | Where it appears |
|-----------|------------------|
| Course / programme label | Courses menu, forms |
| Batch / section label | Batch screens, WhatsApp audience |
| Student ID label | Student profile, roll number field, portal |
| Programmes section title | Public website courses area |

Leave a field blank to use the generic default.

---

### C. Custom student fields

**Menu: Setup → Custom Fields**

Add fields your institute needs — no developer required.

| Field type | Example use |
|------------|-------------|
| Text | Aadhaar last 4 digits, transport route |
| Number | Family income code |
| Date | Medical certificate expiry |
| Long text | Special notes |
| Dropdown | Blood group, scholarship type |

**Where they show:** Student profile → **Edit Details** → section **Additional information**.

**Tips:**
- Use clear labels (`Blood Group`, not `bg`).
- Mark **Required** only if staff must fill it every time.
- **Inactive** hides a field without deleting past data.

---

### D. Academic structure

Do these in order:

#### 1. Academic session  
**Academics → Academic Sessions**  
Example: `2025–26` — set as **current**.

#### 2. Courses / programmes  
**Academics → Courses**  
One row per programme you offer.

| Institute | Examples |
|-----------|----------|
| School | Class 11 Science, Class 12 Commerce |
| Coaching | JEE Main 2026, NEET Dropper Batch |
| College | B.Com Honours, B.Sc Maths |

Set fee, duration, and description. Use **Show on public website** to control which programmes appear on the contact form and courses page.

#### 3. Batches / sections  
**Academics → Batches**  
Link each batch to a course + session + trainer (optional).

| Institute | Examples |
|-----------|----------|
| School | Class 12-A Morning |
| Coaching | JEE Batch A (6 PM) |
| College | B.Com Sem 2 — Section A |

#### 4. Activity types (optional)  
**Setup → Activity Types**  
Exams, mock tests, industrial visits — used in attendance & activities.

---

### E. Fees & admissions

| Task | Menu |
|------|------|
| Fee plans on courses | Edit course → fee structure |
| Record enquiry | Enquiries / student search |
| Admission workflow | Student profile → Convert to admission |
| Approve admission | Super Admin only — Admissions → Approve |
| Collect fees | Student profile → Fees tab |
| Receipts & PDFs | Auto-generated; logo from Site Content |

**Setup → Institute Settings** — receipt footer text and PDF header for receipts / ID cards.

---

### F. WhatsApp (optional)

**Menu: Setup → WhatsApp Settings**

1. Paste Pal Digital **integration key** (`wsk.…`) and API URL.  
2. Click **Sync templates** (live API campaigns from Pal Digital).  
3. Set batch size & delay for large campaigns.  
4. Run queue worker: `php artisan queue:work`

Campaigns: **Management → WhatsApp Campaigns**.

Full API notes: `docs/PAL_DIGITAL_WASERVICE_INTEGRATION.md`

---

### G. Staff & roles

| Role | Typical user |
|------|--------------|
| **Super Admin** | Owner, principal — full access + Setup menus |
| **Staff** | Counsellors, accountants — day-to-day CRM |

**Setup → Staff** (or Staff resource in admin) — create users and assign roles.

---

### H. Student portal

Students log in at **`/portal/login`** with mobile + date of birth (default).

They see fees, attendance, and profile linked to their enrollment.

---

## Part 4 — Go-live checklist

### Any institute

- [ ] Finish **Setup wizard** (branding, labels)
- [ ] **Site Content**: logo, phone, address, homepage programmes toggle
- [ ] **Terminology**: Course/Class, Batch/Section, Roll No. labels
- [ ] **Academic session** marked current (e.g. 2025–26)
- [ ] **Courses**: only programmes you actually offer; hide extras from website if needed
- [ ] **Batches** linked to course + session
- [ ] Test: website enquiry → staff follow-up → admission → fee receipt
- [ ] Optional: `php artisan crm:backfill-enrollment-sessions` if older enrollments lack a session

### School-style example

- [ ] Terminology: Class, Section, Roll No.
- [ ] Courses: Class 10, 11, 12 streams
- [ ] Batches per section

### Coaching-style example

- [ ] Terminology: Programme, Batch, Registration No.
- [ ] Hero text focused on exam targets (JEE/NEET)
- [ ] Batches: Morning/Evening timing
- [ ] WhatsApp: sync attendance / test templates

### College-style example

- [ ] Terminology: Programme, Semester/Section, Enrollment No.
- [ ] Courses: each degree + year
- [ ] ID cards & receipts with college logo

---

## Part 5 — What stays the same for everyone

These work the same regardless of institute type:

- Enquiry → visit → admission → enrollment flow  
- One mobile number = one student  
- Student profile as the main hub  
- Batch attendance & activities  
- Fee installments & receipts  
- Audit log (Super Admin)  
- Excel reports  

---

## Part 5 — Production deployment checklist

Use this when moving from a dev PC to a live server (CloudPanel, nginx, etc.).

### Before go-live

| Step | Command / action |
|------|------------------|
| 1. Environment | Set `APP_ENV=production`, `APP_DEBUG=false`, correct `APP_URL`, DB credentials |
| 2. Dependencies | `composer install --no-dev --optimize-autoloader` |
| 3. Frontend | `npm ci && npm run build` |
| 4. Database | `php artisan migrate --force` (run once; backs up DB first on live servers) |
| 5. Admin user | `php artisan crm:ensure-admin` |
| 6. Livewire assets | `php artisan crm:publish-assets` (**required** — admin breaks without this) |
| 7. Cache | `php artisan config:cache && php artisan route:cache && php artisan view:cache` |

### Queue worker (WhatsApp campaigns)

Campaign messages are queued. Without a worker, sends fail silently.

```bash
php artisan queue:work --sleep=3 --tries=3
```

**Supervisor example** (Linux):

```ini
[program:school-crm-queue]
command=php /path/to/school-crm/artisan queue:work --sleep=3 --tries=3
directory=/path/to/school-crm
autostart=true
autorestart=true
user=www-data
```

### Scheduled tasks

Add to server cron (required for daily backups, late fees, cleanup, WhatsApp helpers):

```cron
* * * * * cd /path/to/school-crm && php artisan schedule:run >> /dev/null 2>&1
```

### Full backups (database + all files / images)

Nightly at **02:15** (server time) the scheduler runs `php artisan crm:backup`. Each zip includes:

- Entire database (students, leads, calls, cases, fees, attendance, homework, WhatsApp, settings, users…)
- `storage/app/private` — photos, Aadhaar, documents, receipts, ID cards, payment proofs, marksheets, WhatsApp media
- `storage/app/public` — homework files, website logo/gallery, CRM branding
- `app-key.txt` — must match `.env` `APP_KEY` on restore

**Super Admin:** Setup → **Backups** — create now, download, connect **Google Drive** for automatic off-site upload, or **upload a zip to restore** (no terminal needed after `.env` is set).

**Google Drive (recommended):**

1. Enable Google Drive API + create a service account (JSON key).
2. Create a Drive folder and share it with the service account email (Editor).
3. In Setup → Backups, paste folder ID + JSON, enable, Test connection.
4. Nightly `crm:backup` then uploads the zip to Drive automatically.

**Manual:**

```powershell
php artisan crm:backup
php artisan crm:restore path\to\school-crm-full-backup-….zip --force
```

Prefer **Setup → Backups → Restore from uploaded backup** for day-to-day recovery after reinstall.

After restore: `php artisan storage:link`, `php artisan crm:publish-assets`, `php artisan cache:clear`, restart queue worker (UI restore runs link/assets/cache for you).

Retention: last **14** archives (`CRM_BACKUP_RETAIN` in `.env`) on server and on Drive.

### After deploy — smoke test

| URL | Check |
|-----|--------|
| `/` | Public website loads with institute name |
| `/admin` | Login, dashboard loads in seconds |
| `/admin/setup` | Super Admin can finish wizard |
| `/portal/login` | Student portal login works |
| **Reports** | Generate a report (large exports cap at 5,000 rows) |
| **WhatsApp** | Send a test campaign only after queue worker is running |

### Performance tips

| Setting | Recommendation |
|---------|----------------|
| `CACHE_STORE` | `redis` or `file` on production (avoid `database` if slow) |
| PHP `max_execution_time` | 60s+ for large imports; reports are capped to avoid timeouts |
| DB indexes | Ensure `php artisan migrate` has run (includes CRM performance indexes) |

---

## Part 6 — Troubleshooting

| Problem | Fix |
|---------|-----|
| Redirected to `/admin/setup` every time | Complete wizard, or set real institute name in Site Content |
| Public site shows “Your Institute” | **Website → Site Content → Branding** |
| No courses on website | Add courses in **Academics → Courses** (status Active + **Show on public website**) |
| WhatsApp campaign not sending | Check API key, run `php artisan queue:work` |
| Admin login / Livewire broken on server | Run `php artisan crm:publish-assets` |
| Want demo students for training | Reinstall with `SEED_DEMO_DATA=true` in `.env` before `migrate --seed` |

---

## Part 7 — For whoever shares this software

**Give each client:**

1. This guide (`docs/INSTALL_AND_CUSTOMIZE_GUIDE.md`)  
2. Their own server or hosted copy  
3. Super Admin login (they change password after first login)  
4. Optional: Pal Digital WhatsApp key if they use campaigns  

**They should not need:**

- Access to source code  
- `.env` changes after initial install  
- Separate builds per school (same software, different admin settings)

---

## Related docs

| Document | Topic |
|----------|--------|
| `docs/GENERIC_SCHOOL_CRM_PROJECT.md` | Product overview & tech stack |
| `docs/PAL_DIGITAL_WASERVICE_INTEGRATION.md` | WhatsApp / Pal Digital |
| `AGENTS.md` | Developer / AI agent notes |

### Inline hints in admin

Every major screen shows a **blue info hint** under the page title and on key Setup forms. Hover sidebar items for short tooltips. Staff can follow these without reading the full guide.

---
