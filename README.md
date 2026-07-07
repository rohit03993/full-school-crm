# School CRM

Generic school and coaching institute CRM — enquiries, admissions, fees, batches, attendance, WhatsApp, and student portal.

Forked from Folks India for multi-institute use. See [docs/GENERIC_SCHOOL_CRM_PROJECT.md](docs/GENERIC_SCHOOL_CRM_PROJECT.md) for the full brief.

## Requirements

- PHP 8.2+
- MySQL 8
- Composer, Node.js 18+

## Quick install

```powershell
cd "path\to\school-crm"
copy .env.example .env
composer install
npm install
npm run build
php artisan key:generate
```

Create database `school_crm` (or your `DB_DATABASE` name), then:

```powershell
php artisan migrate --seed
php artisan crm:ensure-admin
php artisan serve
```

| URL | Purpose |
|-----|---------|
| http://localhost:8000/admin | Staff CRM |
| http://localhost:8000/portal/login | Student portal |
| http://localhost:8000 | Public website |

Default admin: `ADMIN_MOBILE` / `ADMIN_PASSWORD` in `.env`.

## First-time institute setup

1. Log in as Super Admin — **Setup wizard** runs automatically.
2. Open **Settings → Institute Setup** for the full checklist (courses, terminology, branding, WhatsApp).
3. Sync branding from config if needed: `php artisan crm:sync-institute-branding`

Detailed steps: [docs/INSTALL_AND_CUSTOMIZE_GUIDE.md](docs/INSTALL_AND_CUSTOMIZE_GUIDE.md)

## WhatsApp fee reminders

Automated reminders use **Meta-approved templates only** (outside the 24-hour window).

1. **WhatsApp → Templates** — create/submit a template (e.g. `fee_reminder`) and wait for Meta **APPROVED** status.
2. **WhatsApp → Live campaigns** — link the template and click **Go live**.
3. **WhatsApp → Automations** — enable **Send daily fee reminders** and pick the live campaign.
4. Scheduler runs `crm:send-fee-reminders` daily at 09:00 (requires `* * * * * php artisan schedule:run`).

Template variables auto-map: student name, pending amount, due date, institute name (see `WhatsAppTemplateParamResolver`).

## Accounting ledger

Fee receipts and late-fee accruals post to a double-entry journal automatically.

- View: **Students → Accounting ledger**
- Migrations create chart-of-accounts on first post (`crm:process-late-fees` / fee collection)

## Production deploy

```bash
git pull origin main
composer install --no-dev
npm ci && npm run build
php artisan migrate --force
php artisan crm:publish-assets
php artisan optimize:clear
```

Cron: `schedule:run` every minute; ensure `crm:process-queue` runs for WhatsApp campaigns.

CloudPanel: `public/vendor/livewire/` must exist — run `php artisan crm:publish-assets` on the server.

## Tests

```powershell
php artisan test
```

## License

Proprietary — institute deployment per customer agreement.
