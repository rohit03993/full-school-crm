# Pal Digital / waservice integration (school-crm)

School CRM talks to **Pal Digital waservice** (`F:\Rohit Development\waservice`), not raw AiSensy.

## API key

| Item | Value |
|------|--------|
| Where to create | waservice dashboard → **Integrations** → Create key |
| Format | `wsk.<uuid>.<secret>` |
| Auth (send) | `apiKey` field in JSON body (AiSensy-compatible) |
| Tenant scope | Key is tied to one waservice tenant |

The integration key **cannot** list templates or campaigns. waservice only exposes **send** endpoints to external CRMs:

- `POST /api/v1/campaign/t1/api/v2` (AiSensy drop-in)
- `POST /api/v1/integrations/whatsapp/send-template` (`X-Integration-Key` header)
- `POST /api/v1/integrations/campaigns/{id}/trigger` (`X-Integration-Key` header)

Listing templates/campaigns requires **admin JWT** inside waservice (`GET /whatsapp/templates`, `GET /campaigns`) — not available to school-crm with integration key alone.

## school-crm configuration

```env
PAL_DIGITAL_API_KEY=wsk.<uuid>.<secret>
PAL_DIGITAL_API_URL=https://wa.paldigital.in/api/v1/campaign/t1/api/v2
```

Or base URL only: `https://wa.paldigital.in/api/v1` (school-crm appends `/campaign/t1/api/v2`).

## Send payload (what school-crm posts)

```json
{
  "apiKey": "wsk.<uuid>.<secret>",
  "campaignName": "Test announcement",
  "destination": "919876543210",
  "userName": "Student Name",
  "templateParams": ["Param1", "Param2"]
}
```

| Field | Notes |
|-------|--------|
| `campaignName` | Must match a **live API campaign** name in waservice, **or** the linked Meta `template_name` |
| `destination` | Digits with country code, no `+` (e.g. `919876543210`) |
| `templateParams` | Ordered body variables for the template |

## Setup workflow (Pal Digital side)

1. Template approved in Meta → synced in waservice (`POST /whatsapp/templates/sync` in dashboard).
2. Create **API campaign** → choose template → **Go Live**.
3. Create **integration key** → paste into school-crm **Setup → WhatsApp Settings**.
4. Ensure waservice **worker** is running (queued sends).

## school-crm template registration

Because the API key cannot fetch template metadata:

1. **Setup → WhatsApp Settings** → enter live API campaign names (one per line).
2. Optional format: `Campaign name|6` to set param count.
3. Click **Register templates** — creates local rows with default param mappings.
4. Edit preview text / mappings in **Setup → WhatsApp Templates** if needed.

## campaignName matching (waservice logic)

waservice resolves `campaignName` in this order:

1. Live API campaign where `Campaign.name` equals `campaignName`
2. Else live API campaign where `Campaign.template_name` equals `campaignName`

See `waservice/backend/app/services/aisensy_compat.py`.

## References

- waservice: `docs/EXTERNAL_CRM_INTEGRATION_SAFE.md`
- waservice send: `backend/app/api/aisensy_compat.py`
- school-crm send: `app/Services/PalDigitalWhatsAppService.php`
- **Developer spec for waservice changes:** `docs/WASERVICE_API_CHANGES_REQUEST.md`
