# Install docs (read everything here)

**One folder for every new school install.** Start at the top and work down.

| Order | Doc | Who | What |
|-------|-----|-----|------|
| **1** | [**server-checklist.md**](server-checklist.md) | IT / DevOps | Commands, `.env`, Web Push VAPID, cron, queue, smoke test |
| **2** | [**customize-for-school.md**](customize-for-school.md) | Super Admin / institute | Setup wizard, branding, terminology, WhatsApp, modules |

Also useful (outside this folder):

| Doc | Purpose |
|-----|---------|
| [../MODULE_ARCHITECTURE.md](../MODULE_ARCHITECTURE.md) | License modules on/off |
| [../../.env.example](../../.env.example) | Full env template in the repo root |
| Admin → **Setup → Setup Guide** | In-app checklist after login |

---

## Fast path (production)

```bash
cd /path/to/school-crm
composer install --no-dev --optimize-autoloader
cp .env.example .env   # fill DB, APP_URL, ADMIN_* — see server-checklist.md
php artisan key:generate
php artisan migrate --seed --force
php artisan storage:link
php artisan crm:ensure-admin
php artisan crm:sync-permissions
php artisan crm:publish-assets
php artisan crm:webpush-vapid   # paste into .env, then config:cache
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Then cron + queue worker (details in [server-checklist.md](server-checklist.md)).

Then log in at `/admin` and follow [customize-for-school.md](customize-for-school.md).

---

## Do not mix schools

Never reuse another institute’s `APP_KEY`, database, `VAPID_*` keys, Face Verify tokens, or WhatsApp tokens.
