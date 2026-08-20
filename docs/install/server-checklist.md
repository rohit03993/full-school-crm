# Server install checklist (every new school)

**Follow this document for every production install.**  
One CRM install = one institute. Do **not** reuse another school’s `.env`, database, or VAPID keys.

Related: client customization after install → [`customize-for-school.md`](customize-for-school.md)  
Index: [`README.md`](README.md) (all install docs in one place)

---

## 0. Before you start

| Need | Notes |
|------|--------|
| PHP 8.2+ | With extensions Laravel needs (mbstring, openssl, pdo_mysql, tokenizer, xml, ctype, json, bcmath, fileinfo, gd/imagick) |
| MySQL 8 | Empty database created already |
| Composer | On the server |
| Node 18+ | Only if you build assets on the server (`npm run build`) |
| Domain + HTTPS | Set `APP_URL` to the final `https://…` URL |
| CloudPanel / nginx | Document root = `public/` |

---

## 1. Commands (run in order)

Replace `/path/to/school-crm` with the real site path.

```bash
cd /path/to/school-crm

# Code (first install: clone/upload; later: git pull)
# git pull origin main

composer install --no-dev --optimize-autoloader

# Only if frontend assets are not already built in the release:
# npm ci && npm run build

cp .env.example .env
# Edit .env now — see Section 2 (fill DB, APP_URL, ADMIN_*, etc.)

php artisan key:generate

php artisan migrate --seed --force

php artisan storage:link

php artisan crm:ensure-admin
php artisan crm:sync-permissions
php artisan crm:publish-assets

# PWA Web Push keys (REQUIRED for install-app notifications) — generate ONCE per school
php artisan crm:webpush-vapid
# Paste printed WEBPUSH_* / VAPID_* lines into .env (see Section 2.4)
# If you see "minishlink/web-push is not installed", re-run composer install above.

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Optional (vendor / platform only)

```bash
# Only if you use the hidden vendor console
php artisan crm:ensure-platform-operator
```

### After every later deploy (same site, new code)

```bash
cd /path/to/school-crm
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan crm:publish-assets
php artisan crm:sync-permissions
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

**Do not** re-run `crm:webpush-vapid` on an existing live site unless you intend to break all current push subscriptions and issue new keys.

---

## 2. `.env` template (production)

Copy from `.env.example`, then set the values below.  
**Never commit `.env`.** Never copy another school’s `APP_KEY`, DB password, or `VAPID_PRIVATE_KEY`.

### 2.1 Core (required)

```env
APP_NAME="Your Institute Name"
APP_ENV=production
APP_KEY=                    # from: php artisan key:generate
APP_DEBUG=false
APP_URL=https://your-domain.example/
APP_TIMEZONE=Asia/Kolkata

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
# Leave SESSION_SECURE_COOKIE unset on HTTPS (Laravel handles Secure with trustProxies)

QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local
BROADCAST_CONNECTION=log
```

### 2.2 First Super Admin (required)

Created / reset by `php artisan crm:ensure-admin`. Sign in at `/admin`.

```env
ADMIN_MOBILE=9876543210
ADMIN_PASSWORD=ChangeMe@Strong1
ADMIN_NAME="Super Admin"
```

Change the password after first login. Do not leave the example password on production.

### 2.3 Parent & student portal (required)

```env
PORTAL_DEFAULT_PASSWORD=Student@2026
```

Parents/students use this at `/portal/login` (mobile + this password), unless Super Admin later sets a different shared password under **Setup → Institute Settings**. WhatsApp OTP can also be enabled after Meta WhatsApp is configured.

### 2.4 PWA Web Push (required for “Install app” notifications)

1. Ensure Composer deps are installed (`minishlink/web-push` must be present).
2. Run:

```bash
php artisan crm:webpush-vapid
```

3. Paste into `.env` (example shape — **use your generated keys, not another school’s**):

```env
WEBPUSH_ENABLED=true
VAPID_SUBJECT=mailto:admin@your-domain.example
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
```

4. Then:

```bash
php artisan config:clear
php artisan config:cache
```

Keep `VAPID_PRIVATE_KEY` secret. Regenerating keys invalidates existing subscriptions.

### 2.5 License defaults (fresh install)

```env
LICENSE_DEFAULT_PLAN=full_results
LICENSE_DEFAULT_VALID_DAYS=365
```

Vendor can change the live license later from the platform console.

### 2.6 Backups

```env
CRM_BACKUP_RETAIN=14
CRM_BACKUP_SCHEDULE_AT=02:15
# Optional Google Drive (or set Client ID/Secret in Setup → Backups)
# Redirect URI: {APP_URL}/admin/backups/google/callback
GOOGLE_DRIVE_CLIENT_ID=
GOOGLE_DRIVE_CLIENT_SECRET=
```

### 2.7 Attendance + biometric (typical school)

```env
ATTENDANCE_AUTO_OUT_ENABLED=true
ATTENDANCE_AUTO_OUT_TIME=20:00
ATTENDANCE_AUTO_OUT_LATE_GRACE_MINUTES=60

BIOMETRIC_ADMS_ENABLED=true
BIOMETRIC_ADMS_REQUIRE_ALLOWLIST=true
BIOMETRIC_ADMS_PROCESS_INLINE=true
# BIOMETRIC_ADMS_TZ_OFFSET_MINUTES=330
# BIOMETRIC_ADMS_TIMEZONE=Asia/Kolkata
```

### 2.8 Face Verify (optional — only if this school uses the face gate)

Leave disabled unless the school has a Face Verify service account.

```env
FACE_VERIFY_ENABLED=false
FACE_VERIFY_API_URL=
FACE_VERIFY_SERVICE_TOKEN=
FACE_VERIFY_CALLBACK_SECRET=
FACE_VERIFY_DEFAULT_DEVICE_ID=
FACE_VERIFY_TIMEOUT_SECONDS=30
FACE_VERIFY_HTTP_TIMEOUT_SECONDS=10
```

### 2.9 Platform / vendor console (optional)

Not for school staff.

```env
PLATFORM_PANEL_PATH=_vendor-console
PLATFORM_MOBILE=
PLATFORM_PASSWORD=ChangeMe@Platform2026
PLATFORM_NAME="Platform Operator"
```

Then: `php artisan crm:ensure-platform-operator`

### 2.10 Mail / Redis / AWS

Keep defaults from `.env.example` unless this install needs real SMTP, Redis, or S3.

```env
MAIL_MAILER=log
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
VITE_APP_NAME="${APP_NAME}"
```

### 2.11 Optional later (admin UI preferred)

| Feature | Prefer |
|---------|--------|
| Institute name, logo, phone | **Website → Site Content** / Setup wizard — not `.env` |
| Meta WhatsApp / OTP | **Setup → Meta WhatsApp** (or `META_WHATSAPP_*` in `.env`) |
| Ask CRM Gemini | `ASK_CRM_USE_AI` + `GEMINI_API_KEY` if used |

---

## 3. Always-on server services

### Cron (required)

```cron
* * * * * cd /path/to/school-crm && php artisan schedule:run >> /dev/null 2>&1
```

Needed for: backups, late fees, attendance auto-out, cleanup, digests.

### Queue worker (required for WhatsApp campaigns / queued jobs)

Supervisor example:

```ini
[program:school-crm-queue]
command=php /path/to/school-crm/artisan queue:work --sleep=3 --tries=3
directory=/path/to/school-crm
autostart=true
autorestart=true
user=www-data
```

After `.env` or code changes: `php artisan queue:restart`

---

## 4. Smoke test (after install)

| Check | URL / action |
|-------|----------------|
| Public site | `/` |
| Admin login | `/admin` with `ADMIN_MOBILE` / `ADMIN_PASSWORD` |
| Setup wizard | `/admin/setup` (first Super Admin visit) |
| Portal login | `/portal/login` with student mobile + `PORTAL_DEFAULT_PASSWORD` |
| Livewire / admin JS | If blank/broken → `php artisan crm:publish-assets` |
| PWA | Open `/app`, install app, enable notifications (needs VAPID) |
| Queue | Send a test WhatsApp campaign only after worker is running |

---

## 5. Common mistakes

| Symptom | Fix |
|---------|-----|
| `minishlink/web-push is not installed` | `composer install --no-dev --optimize-autoloader` then `php artisan crm:webpush-vapid` |
| Admin UI blank / Livewire 404 | `php artisan crm:publish-assets` |
| Portal “Invalid mobile or password” | Mobile must exist on student (or alternate mobile); password = `PORTAL_DEFAULT_PASSWORD` or Institute Settings shared password |
| Push never arrives | `WEBPUSH_ENABLED=true` + both VAPID keys + `config:cache` + user allowed notifications in installed app |
| Campaigns stuck | Queue worker not running |
| Nightly jobs missing | Cron for `schedule:run` missing |

---

## 6. What stays unique per school

Never copy these between institutes:

- `APP_KEY`
- Database name / credentials
- `ADMIN_PASSWORD` / `PLATFORM_PASSWORD`
- `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY`
- Face Verify tokens
- Meta WhatsApp access tokens
- Google Drive OAuth secrets

Branding (name, logo, courses) is configured in admin after login — not by cloning another school’s `.env`.
