# Performance & Scalability Audit — School CRM (Oct 2026)

**Scope:** existing Laravel 12 + Filament 5 CRM/ERP. 
**Size:** ~300 students, ~50 teachers, ~10 admins. WhatsApp automation, AI (Gemini/OpenAI), biometric/face attendance. 
**Method:** full static code review (no code changed). Server-side numbers (CPU, RAM, p95, load test) **must be measured on the live VPS** — Part C gives the exact steps and blank result tables.

---

## 0. Executive summary

1. **No technology migration is needed.** At 300 students / 60 staff, Laravel is many times bigger than the load. Every bottleneck found is a code or config issue that can be fixed inside the current system.
2. **Main slowness cause (code):** every page and every auto-refresh runs **hundreds of avoidable database queries**, mostly from the licence/module check (`FeatureGate`) and per-request settings reads.
3. **Second cause (config):** cache, queue and locks all use **MySQL** (`CACHE_STORE=database`, `QUEUE_CONNECTION=database`). "Cache" adds DB load instead of removing it.
4. **Third cause (design):** slow external calls (WhatsApp media download, AI, bulk WhatsApp up to 50, homework WhatsApp to a whole class, Call Intelligence 180 s) run **inside the user's web request**, blocking PHP-FPM workers.
5. **Reliability risks found:** no retry on WhatsApp API errors, queue `retry_after` (90 s) far below job timeout (900 s), punch-alert processing can overlap, and a **bug** that marks every successful staff punch WhatsApp as "failed".
6. **Security risks found:** 7 admin download links check only "logged in" (any teacher can download any student's Aadhaar/receipt by changing the ID), backup zips are unencrypted and contain `APP_KEY`, 4-digit staff OTP has no per-number attempt limit.

7. **Server is not the bottleneck.** Hostinger KVM 2 (2 vCPU / 8 GB) runs at 17 % CPU / 37 % RAM at midday. No upgrade needed; but the Hostinger firewall has **0 rules** — fix this.

**Expected result after Phase 1 + 2 fixes:** typical page time should fall by roughly 50–80 % and DB queries per page by 80–90 % (to be confirmed by the before/after benchmark in Part C).

---

## Server (VPS) details — Hostinger, snapshot 15 Oct 2026, 12:34 PM

Source: Hostinger hPanel overview. IP address and root SSH details intentionally **not** stored in this repo.

| Item | Value |
|---|---|
| Provider / plan | Hostinger VPS **KVM 2** (`srv1042659.hstgr.cloud`) |
| CPU / RAM | KVM 2 plan = **2 vCPU / 8 GB RAM** (confirm with `nproc` and `free -h`) |
| Disk | **37 GB used of 100 GB** (NVMe) |
| Bandwidth | 0.006 TB used of 8 TB |
| OS / panel | Ubuntu 24.04 + **CloudPanel** (Nginx + PHP-FPM + MySQL) |
| CPU usage (snapshot) | **17 %** |
| Memory usage (snapshot) | **37 %** (~3 GB of 8 GB) |
| Network (snapshot) | In 2.3 MB / Out 5.0 MB |
| Hostinger firewall rules | **0** (none configured) |
| Snapshots & backups | 2 manual snapshots; Hostinger automated daily backup **not** enabled |
| Malware scanner | Active |

**What this tells us:**

1. **The server is not short of hardware.** At midday it uses 17 % CPU and 37 % RAM. The CRM feels slow because each request does too much work and some requests hold a PHP worker for a long time (Part A), not because the VPS is too small. **No VPS upgrade is needed now.**
2. 8 GB RAM leaves room for Redis (~100–200 MB) and Supervisor queue workers on the same server at ₹0 extra.
3. This is one midday snapshot. Peak (08:00–10:00 attendance time, campaign sends) must still be measured (Part C2). Watch for CPU spikes in the hPanel graph at those times.
4. **Firewall rules = 0** (security). Unless UFW is configured inside the server, every port is open to the internet. Allow only 22 (SSH, ideally restricted to your IP), 80, 443 and 8443 (CloudPanel; restrict to your IP).
5. Backups: the CRM's own daily backup + Google Drive exists (A8). Hostinger snapshots are a second layer — take one before every Phase deployment.

### Live server findings — 15 Oct 2026, ~14:00 IST

School CRM = `motionagra.in` (site user `motionsoft`). Same codebase also at `horizon.taskbook.co.in` (second client). VPS hosts ~15 sites + Docker apps.

| Check | Result | Meaning |
|---|---|---|
| Load average (2 vCPU) | 0.71 / 0.54 / 0.44 | CPU not overloaded |
| RAM / swap | 4.8 GB available; **swap 2 GB of 2 GB full** | Memory ran out at some point |
| What is in swap | **host MySQL 678 MB**, Docker MySQL 330 MB, Celery/Gunicorn/Next.js ~500 MB | **CRM database is partly on disk** → random slow queries when swapped pages are read back |
| Active swapping now (`vmstat si/so`) | 0 | Not thrashing at this moment; slowdowns come in spikes |
| CRM traffic today | ~10.6k requests, **0 server errors (5xx)**, 2 × 499 | Low traffic, stable |
| Top CRM URLs | `/iclock/getrequest` **7,634 (72 %)**, `/livewire/update` 877, WhatsApp webhook 824, `/iclock/devicecmd` 478 | Biometric device polling dominates; each poll boots full Laravel (incl. per-request settings queries, A1.2) |
| CRM scheduler | Root crontab: `php artisan schedule:run` **as root** | Runs, but as wrong user → files in `storage/` may become root-owned → web user cannot write logs/cache |
| CRM `.env` | `APP_DEBUG=false` ✅, `LOG_LEVEL=debug` ❌, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`, `SESSION_DRIVER=file` | Redis already installed on host (`127.0.0.1:6379`) — free to use |
| Queue workers | No Supervisor; queue drained by cron `crm:process-queue` | As in A3.2 |
| Docker apps | `face-verify-api` (port 8000 public, `uvicorn --reload` = dev mode), `face-verify-mysql` (**port 3307 public**), EasyTimePro Django + Celery + **Postgres port 7496 public**, nginx 4370/18090 | Two database ports open to the internet + firewall 0 rules = **High security risk** |
| Other heavy background jobs | `taskbook-msg`: scheduler twice/min + continuous worker, **0 web traffic**; `taskbook-horizon`: scheduler + `script.php` every minute | Background CPU from sites nobody visits |

**Follow-up checks (14:25 IST):**

- `vm.swappiness = 60` (Ubuntu default) → too eager to swap for a DB server.
- UFW is **active** (allows 22, 80, 443, 8443, 8088). **But Docker-published ports bypass UFW** — 3307 (MySQL), 7496 (Postgres), 8000 (face API), 4370, 18090 listen on `0.0.0.0` and are reachable from the internet despite UFW. Host MySQL 3306 listens on all interfaces but is blocked by UFW. Redis is local-only ✅.
- Root-owned files inside CRM `storage/`: all backup zips and **compiled Blade views** (`storage/framework/views`). After a deploy, the web user (`motionsoft`) cannot overwrite those views → "Permission denied" errors on changed screens.
- CLI PHP 8.4 = site FPM pool PHP 8.4 ✅ (same version).
- CRM config has no connection to ports 3307/7496 — those databases belong to the Docker apps.

**Conclusion:** hardware is enough. Slowness = (1) CRM code cost per request (Part A), (2) MySQL partly in swap, (3) every biometric poll pays the full per-request cost. Fix server items S1–S5 (Part B) first, then code Phase 1.

---

## Part A — Findings by area

Severity: **P1** = fix first (big impact or risk), **P2** = next, **P3** = later.

### A1. Laravel / PHP application performance

| # | Finding | Evidence | Sev |
|---|---|---|---|
| 1.1 | Licence check runs ~5 DB queries + 2 HMAC signatures **per call**, never memoised. Used ~150 files; sidebar alone checks ~93 pages/resources per page load. | `app/Services/LicenseService.php` — `ensureDefaultLicense()` (`->exists()` query) called from `current()`, `isSignatureValid()`, `enabledFeatureKeys()` | P1 |
| 1.2 | Face Verify settings loaded on **every request** (incl. Livewire polls, biometric pings): `Schema::hasTable('settings')` + 5 setting queries. | `AppServiceProvider::boot()` → `FaceVerifyPlatformService::applyConfiguredOverrides()` | P1 |
| 1.3 | Settings read **one key = one query** (`Setting::getValue`). Many keys per page. | `app/Models/Setting.php` | P2 |
| 1.4 | Brand name read from a cache entry that also holds the **base64 logo** (large blob, from MySQL cache table) on every page. | `InstituteSettings::forDocuments()` | P2 |
| 1.5 | Auto-refresh (Livewire polling) multiplies all the above: dashboard pulse 15 s, WhatsApp inbox/thread 10 s, profile messages 8 s, campaign progress 2 s, notifications 30 s. | `DashboardTodayPulseWidget` (`poll => 15s`), `whatsapp-inbox.blade.php`, `whatsapp-campaign-poll.blade.php`, `AdminPanelProvider` (`databaseNotifications()`) | P1 |
| 1.6 | WhatsApp thread view: `Schema::hasColumn()` 4–6× **per message** (×50 messages) = 200–300 schema queries per load, repeated every 8–10 s. | `StudentWhatsAppThreadService::mapMetaMessageRows()`, `MetaWhatsAppMediaService` | P1 |
| 1.7 | WhatsApp media downloaded from Meta **during page render** (up to 10 files × 12 s timeout). Blocks the worker. | `MetaWhatsAppMediaService::syncPendingDownloads()` called from thread load | P1 |
| 1.8 | Long work inside web requests: campaigns ≤ 50 recipients (`dispatchSync`), homework WhatsApp to whole class, AiSensy trigger, AI calls (15–20 s each, Ask CRM up to 2 calls), Call Intelligence (180 s), manual backup. | `WhatsAppCampaignService.php:133-169`, `HomeworkWhatsAppService.php:198-275`, `AskCrmService`, `StudentProfilePage.php:1528`, `BackupsPage.php:92` | P1 |
| 1.9 | ~40 runtime `Schema::hasTable/hasColumn` checks in services (each = `information_schema` query). | `PunchLogService`, `PublishedResultsGate`, `ExamTestGroupService`, etc. | P2 |
| 1.10 | Install guide lacks `route:cache`, `view:cache`, `event:cache`, `filament:optimize`, `icons:cache`. | `docs/install/server-checklist.md` | P2 |

**Good already:** dashboard numbers and menu badges are cached 60 s; main list tables eager-load relations (`StudentResource`, `EnquiryResource`); pagination set globally (`CrmPagination`).

### A2. Database — queries, N+1, indexes, pagination

| # | Finding | Evidence | Sev |
|---|---|---|---|
| 2.1 | ~250 uses of `whereDate(col, …)`. Wraps column in `DATE()` → **index not used** → full table scan. Hot in dashboards, fees, follow-ups, attendance. | `CrmDashboardService`, `DashboardOpsService`, `FeesDashboardService`, `FollowUpWorklistService`, `AttendanceHubOverviewService` | P2 |
| 2.2 | Missing single-column indexes for institute-wide "today" totals: `payments.payment_date`, `student_calls.called_at`, `attendances.attendance_date`, `homework_*` dates. Existing indexes start with `student_id`/`batch_id`. | migrations `create_payments_table`, `create_student_calls...`, `create_batches_tables` | P2 |
| 2.3 | Student search uses `LIKE '%term%'` (no index possible). Fine at 300 students; revisit above ~20k rows. | `StudentResource::table()` | P3 |
| 2.4 | N+1: main lists OK. Thread/inbox rendering does per-row work (see 1.6). Full N+1 scan needs runtime tool (Part C, step C3). | — | P2 |
| 2.5 | Pagination: global 25/page via `CrmPagination`. OK. | `AppServiceProvider` | — |

### A3. Redis caching & Laravel queue configuration

| # | Finding | Evidence | Sev |
|---|---|---|---|
| 3.1 | `CACHE_STORE=database` — every cache read/write, lock (`BulkSendGuard`, `withoutOverlapping`), Spatie permission cache, OTP, device lock = MySQL query. | `.env.example:98`, `config/permission.php` (`store => default`) | P1 |
| 3.2 | `QUEUE_CONNECTION=database`; worker = cron `crm:process-queue` every minute (`queue:work --stop-when-empty --max-time=55`). Polls `jobs` table constantly; jobs wait up to 60 s to start. | `.env.example:96`, `CrmProcessQueueCommand.php` | P1 |
| 3.3 | **`retry_after=90` vs `RunWhatsAppCampaignJob::$timeout=900`.** A job running >90 s is given to a second worker while still running → double processing risk. | `config/queue.php:43`, `app/Jobs/RunWhatsAppCampaignJob.php:16` | P1 |
| 3.4 | Supervisor sample in docs has no `--timeout`/`--max-time` and duplicates the cron worker. | `docs/install/server-checklist.md:277` | P2 |
| 3.5 | `DownloadMetaWhatsAppMediaJob` dispatched `->afterResponse()` → runs in the web process, not the queue; its retries/backoff are ignored. | `MetaWhatsAppWebhookService.php:168` | P2 |

### A4. Separate queues & workers (WhatsApp / AI / reports)

- **Current:** one default queue, no `onQueue()` anywhere, no AI jobs at all (AI is synchronous), reports/PDF/Excel/backups run in web requests.
- **Risk:** a 600-message campaign and a punch alert share one line; AI or a big report blocks a PHP-FPM worker.
- **Target:** queues `critical` (OTP, punch alerts), `whatsapp` (campaigns, reminders), `ai`, `reports` (PDF/Excel/backup), `default`. One Supervisor program per group with its own `--timeout` (Part B, item 9).

### A5. PHP-FPM, OPcache, Nginx, VPS

Server: Hostinger KVM 2 (2 vCPU / 8 GB), CloudPanel, Ubuntu 24.04 — snapshot CPU 17 %, RAM 37 % (see "Server (VPS) details"). PHP-FPM, OPcache and MySQL settings must still be read on the server (Part C, step C1). With 8 GB RAM, `pm.max_children` can safely be ~16–20 (≈ 60–80 MB per PHP worker) instead of CloudPanel's lower default. Code-side facts that affect sizing:

- Requests can hold a worker for **12–180 s** (media download, AI, Call Intelligence, campaigns ≤50). With `pm.max_children` = 5–10 (common default), a few such requests make **everyone** wait. This matches "CRM hangs sometimes".
- 8 scheduler commands every minute (`bootstrap/app.php`), each a full Laravel boot; `queue:work` runs ~55 s of every minute.
- `.env.example` ships `APP_DEBUG=true`, `LOG_LEVEL=debug`, `LOG_CHANNEL=stack→single` (never rotates). Verify production overrides.

### A6. WhatsApp — retries, rate limits, webhooks, duplicates

| # | Finding | Evidence | Sev |
|---|---|---|---|
| 6.1 | **No retry** on Meta 429 / 5xx / timeout — message marked Failed permanently. | `MetaWhatsAppService.php:83-157` (no `Http::retry`), `RunWhatsAppCampaign.php:195` | P1 |
| 6.2 | **Bug:** staff punch alert checks `status === 'sent'/'queued'`, Meta service returns `'success'` → every successful alert logged as `failed`. | `StaffPunchWhatsAppService.php:93-95` vs `MetaWhatsAppService.php:113` | P1 |
| 6.3 | `attendance:process-punches` every minute **without `withoutOverlapping`**; sends inline per punch; check-then-send has no lock → duplicate parent messages if a run exceeds 1 min. | `bootstrap/app.php:52`, `PunchWhatsAppService.php:70, 227-238` | P1 |
| 6.4 | Campaign rows stuck in `Processing` 15 min are reset to `Pending`; a long batch can exceed this → resend risk. | `RunWhatsAppCampaign.php:271-275` | P2 |
| 6.5 | Fee reminder log written right after queueing (not on success); no DB unique key → failed sends never retried; duplicates possible under race. | `FeeReminderWhatsAppService.php:225-226, 435-447` | P2 |
| 6.6 | Webhook processed synchronously; exceptions swallowed with 200 → lost; status arriving before outbound row is saved is dropped; repeated inbound delivery re-runs flow/form auto-replies. | `MetaWhatsAppWebhookController.php:40-51`, `MetaWhatsAppMessageLogger.php:216`, `MetaWhatsAppWebhookService.php:171-185` | P2 |
| 6.7 | No `RateLimiter`; bulk pacing = batch 10 (max 50) + 2 s delay; homework loops have no pause. Fine for 300 students; needs a limiter for growth. | `config/whatsapp.php:11-17` | P3 |

**Good already:** webhook HMAC (`X-Hub-Signature-256`) verified with `hash_equals`; recipients claimed with `lockForUpdate`; `BulkSendGuard` cache lock; unique `wamid`; status only moves forward; per-message cost estimate stored.

### A7. AI — timeouts, isolation, usage tracking

| # | Finding | Evidence | Sev |
|---|---|---|---|
| 7.1 | All AI calls synchronous in Livewire requests (homework 20 s, Ask CRM 15 s ×2, Call Intelligence 180 s). No retries. | `GeminiHomeworkTextImprover.php:24`, `AskCrmGeminiService.php:223,234`, `CallIntelligenceClient.php:97` | P1 |
| 7.2 | **Ask CRM: no rate limit, no quota, no usage log** — unlimited paid calls per user. | `AskCrmPage::send`, `AskCrmChatWidget::send` | P1 |
| 7.3 | Homework AI quota (6/min, 10/day) kept only in cache → reset by cache clear; no token/cost table. | `HomeworkAiService.php:84-158` | P2 |
| 7.4 | Ask CRM logs raw exception message → may contain `?key=` API key in `laravel.log`. | `AskCrmGeminiService.php:251, 265` | P2 |

### A8. Security, permissions, logging, backups

| # | Finding | Evidence | Sev |
|---|---|---|---|
| 8.1 | **7 download controllers check only `auth()->check()`** — any staff can fetch any student's documents/receipts/marksheets/ID card/WhatsApp media by changing the URL ID. | `DocumentDownloadController.php:15,26`, `ReceiptDownloadController`, `PaymentProofDownloadController`, `MarksheetDownloadController`, `ConsolidatedMarksheetDownloadController`, `IdCardDownloadController`, `MetaWhatsAppMediaController` | P1 |
| 8.2 | **Backup zip unencrypted and contains `APP_KEY`** (+ env snapshot); same zip uploaded to Google Drive. | `CrmBackupService.php:123-131` | P1 |
| 8.3 | Staff OTP is 4 digits, throttle per IP only, no per-mobile wrong-attempt lock. | `WhatsAppOtpService.php:16`, `routes/web.php:170-173` | P1 |
| 8.4 | No throttle on `/webhooks/meta/whatsapp`, `/campaign/t1/api/v2` (sends paid WhatsApp with body `apiKey`), `routes/api.php`, `/iclock/*`, `/verify/{enrollment}` (shows student data, guessable). | `routes/web.php:41-62,130`, `routes/api.php`, `routes/biometric.php` | P2 |
| 8.5 | Not audited: login/logout, OTP login, role/permission changes, settings/secret changes, file downloads, AI usage. | `AuditService` usage | P2 |
| 8.6 | Restore runs in a Livewire request, no automatic safety backup first, no maintenance mode. `mysqldump` output fully buffered in memory; DB password visible in process list. | `BackupsPage.php:427`, `CrmBackupService.php:459,470` | P2 |
| 8.7 | Logs: `single` file never rotates; default level `debug`. | `config/logging.php` | P2 |

**Good already:** all 20 resources + 70/73 pages permission-guarded; Spatie roles; no `$guarded = []`; raw SQL uses bindings; face-verify & call-intelligence callbacks use bearer + HMAC; daily backup with 14-copy retention on Drive; restore round-trip has tests.

---

## Part B — Prioritised optimisation plan

Effort = developer days (1 dev). Infra cost is approximate (India VPS pricing). No item deletes data; each needs backup → change → `php artisan test` → verify screen.

### Phase 0 — Server fixes (no CRM code), from live findings

| # | Change | Why | Risk | Effort |
|---|---|---|---|---|
| S1 | Firewall: close public DB ports 3307, 7496 (and 8000 if face devices do not call it directly); keep 22, 80, 443, 8443 (your IP), 4370/18090 (EasyTimePro devices) | Databases exposed to internet | Low — confirm which devices use which port first | 0.5 d |
| S2 | Lower `vm.swappiness` to 10; size MySQL `innodb_buffer_pool_size` to fit RAM | Keep MySQL out of swap | Low | 0.2 d |
| S3 | Move CRM scheduler from root crontab to `motionsoft` user (same PHP version as site); fix any root-owned files in `storage/` | Avoid permission errors | Low | 0.2 d |
| S4 | `LOG_LEVEL=warning`, `LOG_CHANNEL=daily` | Smaller logs, less disk writes | Very low | 0.1 d |
| S5 | Stop or reduce background jobs of unused sites (`taskbook-msg` etc.); run `face-verify-api` without `--reload` | Free CPU/RAM | Low — confirm site not in use | 0.3 d |

### Phase 1 — Quick wins (week 1) — biggest speed gain, lowest risk

| # | Change | Fixes | Effort | Cost |
|---|---|---|---|---|
| 1 | Memoise licence result once per request (and cache across requests, cleared on licence save). | 1.1 | 0.5 d | ₹0 |
| 2 | Load all `settings` rows in one query per request; move Face Verify override out of `boot()` or cache it. | 1.2, 1.3 | 0.5 d | ₹0 |
| 3 | Install Redis on same VPS; `CACHE_STORE=redis`, `SESSION_DRIVER=redis`. (Fallback: `file`.) | 3.1 | 0.5 d | ₹0 (same VPS) |
| 4 | Fix staff punch status bug (`'success'`). | 6.2 | 0.1 d | ₹0 |
| 5 | Add `withoutOverlapping()` to `attendance:process-punches` and `attendance:auto-out`. | 6.3 | 0.1 d | ₹0 |
| 6 | Set `DB_QUEUE_RETRY_AFTER` above longest job timeout (e.g. 960). | 3.3 | 0.1 d | ₹0 |
| 7 | Production `.env`: `APP_DEBUG=false`, `LOG_LEVEL=warning`, `LOG_CHANNEL=daily`; deploy runs `php artisan optimize` + `filament:optimize`. | 1.10, 8.7 | 0.2 d | ₹0 |
| 8 | Add permission/assignment checks to the 7 download controllers. | 8.1 | 1 d | ₹0 |

### Phase 2 — Move slow work off the web request (weeks 2–3)

| # | Change | Fixes | Effort | Cost |
|---|---|---|---|---|
| 9 | `QUEUE_CONNECTION=redis`; named queues `critical`, `whatsapp`, `ai`, `reports`, `default`; Supervisor workers with own `--timeout`; remove cron `crm:process-queue`. | 3.2, 3.4, A4 | 1.5 d | ₹0 |
| 10 | WhatsApp media download → queued job only (never in page render). Remove per-row `Schema::hasColumn` in thread/media services. | 1.6, 1.7, 3.5 | 1 d | ₹0 |
| 11 | Homework WhatsApp, AiSensy trigger, all campaigns → always queued (drop the ≤50 sync path). | 1.8 | 1 d | ₹0 |
| 12 | `Http::retry()` with backoff for Meta 429/5xx/timeouts; job `$backoff`; respect Meta throttle codes. | 6.1 | 1 d | ₹0 |
| 13 | Call Intelligence upload + Ask CRM heavy calls → `ai` queue; Ask CRM rate limit + `ai_usage_logs` table (user, feature, tokens, cost); redact keys in logs. | 7.1–7.4 | 2 d | ₹0 |
| 14 | Slow polling: dashboard 60 s, inbox 20 s, `.visible` on all polls; campaign progress 5 s. | 1.5 | 0.3 d | ₹0 |

### Phase 3 — Database & hardening (weeks 3–4)

| # | Change | Fixes | Effort | Cost |
|---|---|---|---|---|
| 15 | Replace hot `whereDate()` with `whereBetween(start, end)`; add indexes (`payments.payment_date`, `student_calls.called_at`, `attendances.attendance_date`, etc.). Migration only adds indexes — no data change. | 2.1, 2.2 | 2 d | ₹0 |
| 16 | Remove remaining runtime `Schema::has*` checks (schema is guaranteed by migrations). | 1.9 | 1 d | ₹0 |
| 17 | Fee reminder log on actual success + unique key; webhook processing via queue with retry; inbound idempotency for flow replies. | 6.5, 6.6 | 2 d | ₹0 |
| 18 | Encrypt backup zip (password from env), exclude `APP_KEY` from Drive copy; safety backup before restore; stream `mysqldump` to file. | 8.2, 8.6 | 1.5 d | ₹0 |
| 19 | OTP 6 digits + per-mobile attempt lock; throttles on webhook/API/iclock/verify routes; audit logins and role changes. | 8.3–8.5 | 1.5 d | ₹0 |

**Total:** ~19 developer days. **Infra:** ₹0 — current Hostinger KVM 2 (2 vCPU / 8 GB) is enough; Redis and queue workers run on the same VPS.

**Server hardening (no code, ~0.5 d):** add Hostinger firewall rules (currently 0) — allow 22 (your IP), 80, 443, 8443 (your IP); take a Hostinger snapshot before each phase deploy.

---

## Part C — Measurement plan (baseline + before/after)

You run these on the server. Fill the tables, then we compare after each phase.

### C1. Server configuration snapshot (once)

```bash
nproc; free -h; df -h
php -v; php -i | grep -E "opcache.enable|opcache.memory_consumption|opcache.validate_timestamps|memory_limit|max_execution_time"
# PHP-FPM pool (path may differ on CloudPanel)
grep -E "^pm|^pm\." /etc/php/8.*/fpm/pool.d/*.conf
mysql -e "SHOW VARIABLES LIKE 'innodb_buffer_pool_size'; SHOW VARIABLES LIKE 'max_connections';"
grep -E "^(APP_ENV|APP_DEBUG|CACHE_STORE|SESSION_DRIVER|QUEUE_CONNECTION|LOG_LEVEL|LOG_CHANNEL)=" .env
crontab -l; supervisorctl status
```

| Item | Value |
|---|---|
| vCPU / RAM / disk free | 2 vCPU / 8 GB / 63 GB free (from hPanel — confirm) |
| PHP version / OPcache on? / OPcache memory | |
| `pm` / `pm.max_children` | |
| MySQL buffer pool / max connections | |
| APP_DEBUG / CACHE / SESSION / QUEUE / LOG_LEVEL | |

### C2. Resource use during peak (school morning 08:00–10:00)

```bash
# CPU/RAM/disk every 5 s for 2 hours
vmstat 5 1440 > vmstat_peak.txt &
iostat -x 5 1440 > iostat_peak.txt &      # apt install sysstat
mysqladmin -i 10 extended-status | grep -E "Threads_running|Questions|Slow_queries" > mysql_peak.txt &
```

Enable MySQL slow log for that window:

```sql
SET GLOBAL slow_query_log = 1;
SET GLOBAL long_query_time = 0.2;
SET GLOBAL log_queries_not_using_indexes = 1;
```

| Metric (peak) | Avg | Max |
|---|---|---|
| CPU % | | |
| RAM used / swap | | |
| Disk util % / await ms | | |
| MySQL Threads_running | | |
| MySQL queries/sec | | |
| Slow queries (>200 ms) count | | |

### C3. Response time p50 / p95 / p99

Add request time to the Nginx access log (CloudPanel → Vhost):

```nginx
log_format timed '$remote_addr [$time_local] "$request" $status $request_time';
access_log /var/log/nginx/crm_timed.log timed;
```

After one school day:

```bash
awk '{print $NF, $(NF-3)}' /var/log/nginx/crm_timed.log | grep -v -E "\.(css|js|png|jpg|svg|woff2?)" \
 | sort -n | awk '{a[NR]=$1} END {print "count",NR,"p50",a[int(NR*0.5)],"p95",a[int(NR*0.95)],"p99",a[int(NR*0.99)]}'
```

Per-page query count (local copy with production-like data): temporarily install `barryvdh/laravel-debugbar` as **dev only**, open each key screen, note queries + time.

| Screen / endpoint | p50 | p95 | p99 | DB queries |
|---|---|---|---|---|
| Dashboard (full load) | | | | |
| Livewire poll (`/livewire/update`) | | | | |
| Students list | | | | |
| Student profile | | | | |
| Attendance (mark section) | | | | |
| WhatsApp inbox / thread | | | | |
| Fee collect / receipt PDF | | | | |
| `/iclock/cdata` (biometric) | | | | |
| Webhook `/webhooks/meta/whatsapp` | | | | |

### C4. Load test (realistic)

Run on a **staging copy** (not live). Tool: [k6](https://k6.io).

**Realistic peak model:** 08:30 attendance — 40 teachers + 10 admins active together, each clicking every 10–20 s, plus background polls. Bulk: one 600-message campaign during that window.

```javascript
// loadtest.js — run: k6 run -e BASE=https://staging.example.com -e COOKIE="laravel_session=..." loadtest.js
import http from 'k6/http';
import { sleep, check } from 'k6';

export const options = {
  scenarios: {
    staff: { executor: 'ramping-vus', startVUs: 0, stages: [
      { duration: '2m', target: 20 }, { duration: '5m', target: 50 },
      { duration: '3m', target: 80 }, { duration: '2m', target: 0 } ] },
  },
  thresholds: { http_req_duration: ['p(95)<1500'], http_req_failed: ['rate<0.01'] },
};

const pages = ['/admin', '/admin/students', '/admin/attendance-page', '/admin/follow-ups-page'];

export default function () {
  const res = http.get(__ENV.BASE + pages[Math.floor(Math.random() * pages.length)],
    { headers: { Cookie: __ENV.COOKIE } });
  check(res, { 'status 200': (r) => r.status === 200 });
  sleep(10 + Math.random() * 10);
}
```

Bulk-message test: on staging with a **test WhatsApp number list**, queue one 600-recipient campaign while k6 runs; record time to finish and failures.

| Test | Users | Requests/s | p95 ms | Error % | CPU max | Notes |
|---|---|---|---|---|---|---|
| Baseline 20 users | | | | | | |
| Baseline 50 users | | | | | | |
| Stress 80 users | | | | | | |
| 50 users + 600-msg campaign | | | | | | campaign minutes: |

### C5. Before / after benchmark (fill after each phase)

| Metric | Baseline | After Phase 1 | After Phase 2 | After Phase 3 |
|---|---|---|---|---|
| Dashboard p95 (ms) | | | | |
| Queries per dashboard load | | | | |
| Livewire poll p95 (ms) | | | | |
| WhatsApp thread load (ms) | | | | |
| Peak CPU % | | | | |
| MySQL queries/sec at peak | | | | |
| 50-user load test p95 / error % | | | | |
| 600-msg campaign duration / failures | | | | |

---

## Part D — Scaling plan

**Current target load is small.** 60 staff + 300 students (portal) ≈ 5–20 requests/second at peak after Phase 2 (polling reduced). One well-configured VPS is enough.

| Stage | Size | Setup | Approx. infra cost / month |
|---|---|---|---|
| **Now** (current Hostinger KVM 2 = 2 vCPU / 8 GB — already enough) | ≤ 500 students, ≤ 80 staff, ≤ 5k msgs/day | 1 VPS 2 vCPU / 4–8 GB: Nginx + PHP-FPM (`pm.max_children` ≈ 12–16) + MySQL + Redis + Supervisor workers. OPcache on. | ₹1,000–2,500 |
| **Next** | 1,000–2,000 students, 150 staff, 20k msgs/day | 4 vCPU / 8–16 GB (Hostinger KVM 4 or similar). Separate worker counts per queue (whatsapp ×2, ai ×1, reports ×1). Daily slow-query review. | ₹2,500–5,000 |
| **Multi-campus / SaaS** | 5k+ students or many schools | Split: app VPS + managed MySQL + Redis; object storage (S3-compatible) for documents/media; Laravel Horizon for queue monitoring; CDN for static assets. | ₹8,000–20,000 |

**When would another backend be justified?** Only if, after Phases 1–3, a measured limit remains that Laravel cannot meet — e.g. thousands of persistent real-time connections (would use Laravel Reverb first) or very heavy number-crunching (would add a small separate service, not rewrite). Nothing in this audit points to that.

---

## Part E — What could not be done from code alone

- CPU/RAM/disk/DB numbers, p50/p95/p99, load-test results → need server/staging access (Part C).
- PHP-FPM, OPcache, Nginx settings → server files, not in repo.
- Actual production `.env` values → not read (secrets).
- Before/after benchmarks → filled after each phase is deployed.
