# Confirmed decisions — Calling & WhatsApp (school-crm)

| Topic | Decision |
|-------|----------|
| **WhatsApp provider** | **Pal Digital** WhatsApp API (not AiSensy). Built natively in school-crm Phase D. |
| **Who can call** | **All staff** can log calls. **`student_calls.user_id`** records who called. |
| **Lead assignment** | **`enquiries.meeting_with_user_id`** — call queue prioritises assigned (+ unassigned) leads. |
| **3-strike block** | **Yes** — permanent block after **3 not-connected** attempts. |
| **Mobile UI** | **Yes** — mobile-friendly Filament pages (Call Queue first). |
| **Telephony** | **`tel:` links** only — staff use phone dialer; CRM logs outcomes. |

## Phase A (implemented — run migration first)

```powershell
cd "F:\Rohit Development\Full school soft\school-crm"
php artisan migrate
```

- `student_calls` table + call summary fields on `students`
- `CallLogService` — log connected / not connected, follow-up, 3-strike block, sync visit
- **Management → Call Queue** — mobile-first, Call Now + Log Call Result
- **Student Profile → Calls** tab + **Log Call** action
- Caller name shown on every call row (`staff.name`)

## Phase B (implemented)

- **Management → My Leads** — enquiries where `meeting_with_user_id` = current staff; search + uncalled/called filters
- **Dashboard → Calling today** widget — calls today, queue, due follow-ups, uncalled assigned leads
- **Last call summary** — profile header, student search results, recent enquiries list
- **Mobile bottom nav** — Home, My Leads, Call Queue, Follow-ups (phones/tablets)
- **Follow-ups page** — visit follow-ups **and** call callbacks (`next_call_followup_at`)

## Phase C (implemented)

- **Management → Call Report** — date range, connection, new vs follow-up, not-connected reason, visit status, search
- Summary cards: total, connected, not connected, **new calls** (first-ever per student), **follow-up calls**
- Staff see own calls; Super Admin can filter by staff or view all
- Mobile bottom nav includes **Report** tab
- Dashboard **Calls today** links to Call Report

## Phase D (implemented)

Run migration first:

```powershell
cd "F:\Rohit Development\Full school soft\school-crm"
php artisan migrate
```

- **Setup → WhatsApp Settings** — Pal Digital API key/URL, post-call auto-send, batch size
- **Setup → WhatsApp Templates** — template name (matches Pal Digital), param mapping, preview body
- **Management → WhatsApp Campaigns** — bulk send to **enrolled students** by batch or whole class/course (Super Admin only for now)
- **Setup → WhatsApp Settings** — waservice integration key (`wsk.…`), send URL, register templates from live API campaign names
- **Student Profile → Messages** tab — send single WhatsApp + message history
- **Post-call auto WhatsApp** — optional after connected outgoing calls
- Queue worker required: `php artisan queue:work`

### .env (optional fallback)

```
PAL_DIGITAL_API_KEY=wsk.<uuid>.<secret>
PAL_DIGITAL_API_URL=https://wa.paldigital.in/api/v1/campaign/t1/api/v2
PAL_DIGITAL_DEFAULT_TEMPLATE=
WHATSAPP_CAMPAIGN_BATCH_SIZE=10
```

See `docs/PAL_DIGITAL_WASERVICE_INTEGRATION.md` for waservice setup (API key from Integrations tab, live API campaigns, worker).

See also: `docs/CALLING_CRM_REFERENCE_VS_SCHOOL_CRM.md`
