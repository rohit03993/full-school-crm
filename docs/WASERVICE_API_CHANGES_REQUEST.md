# waservice API changes request — for external CRM (school-crm)

**Audience:** waservice / Pal Digital developer  
**Requested by:** school-crm team  
**Date:** June 2026  
**waservice repo:** `F:\Rohit Development\waservice`  
**school-crm repo:** `F:\Rohit Development\Full school soft\school-crm`

---

## 1. Why we need this

school-crm sends WhatsApp via waservice using the **integration key** (`wsk.<uuid>.<secret>`) and the existing AiSensy-compatible send endpoint:

```http
POST /api/v1/campaign/t1/api/v2
```

**Sending works.** What does **not** work for external CRMs today:

- Listing **approved WhatsApp templates** (name, language, param count, preview text)
- Listing **live API campaigns** (name, linked template, param count)
- Fetching **one campaign/template detail** for building a send form

Today those exist only for **logged-in admin users** (`GET /whatsapp/templates`, `GET /campaigns` with JWT). External CRMs only get **send** routes in `backend/app/api/v1/integrations.py`.

Because of that, school-crm cannot auto-load template parameters when the user picks a template. We manually register template names — which is error-prone and not the real Meta template structure.

**Goal:** Add **read-only** integration endpoints authenticated with the same `wsk` key, scoped to the key’s tenant.

---

## 2. What already exists (do not break)

| Endpoint | Auth | Purpose |
|----------|------|---------|
| `POST /api/v1/campaign/t1/api/v2` | `apiKey` in JSON body | AiSensy drop-in send (school-crm uses this) |
| `POST /api/v1/integrations/whatsapp/send-template` | `X-Integration-Key` | Direct template send |
| `POST /api/v1/integrations/campaigns/{id}/trigger` | `X-Integration-Key` | Trigger live API campaign |
| `GET /api/v1/whatsapp/templates` | Admin JWT | List templates (**not** available to integration key) |
| `GET /api/v1/campaigns` | Admin JWT | List campaigns (**not** available to integration key) |

Reference files:

- `backend/app/api/v1/integrations.py`
- `backend/app/api/aisensy_compat.py`
- `backend/app/api/integration_deps.py` — `resolve_integration_auth()` / `wsk` validation
- `backend/app/api/v1/whatsapp.py` — `list_templates()`, uses `TemplateItemResponse`
- `backend/app/services/template_preview.py` — `body_template_variables()`, `build_template_preview_from_stored()`
- `docs/EXTERNAL_CRM_INTEGRATION_SAFE.md`

---

## 3. What to build (minimum — Phase 1)

Add **two GET endpoints** under the existing integrations router, same auth as send.

### 3.1 Auth (same as send)

```http
X-Integration-Key: wsk.<uuid>.<secret>
```

Use existing `get_integration_auth` from `integration_deps.py`.  
Scope all queries to `ctx.tenant_id` from the key row.

Rate limit similar to send routes (e.g. 60/min per key + IP).

---

### 3.2 `GET /api/v1/integrations/templates`

**Purpose:** Return approved WhatsApp templates synced from Meta so school-crm can build dynamic “fill param {{1}}…{{n}}” forms.

**Query params (optional):**

| Param | Default | Description |
|-------|---------|-------------|
| `status` | `APPROVED` | Filter by Meta status |
| `language` | (all) | e.g. `en_US` |

**Response:** `200` JSON array

```json
[
  {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "hello_world",
    "language": "en_US",
    "category": "UTILITY",
    "status": "APPROVED",
    "preview_text": "Hello {{1}}, your batch {{2}} has test on {{3}}.",
    "body_variables": ["1", "2", "3"],
    "param_count": 3
  }
]
```

**Field rules:**

| Field | Source in waservice |
|-------|---------------------|
| `id` | `MessageTemplate.id` |
| `name` | `MessageTemplate.name` |
| `language` | `MessageTemplate.language` |
| `category` | `MessageTemplate.category` |
| `status` | `MessageTemplate.status` |
| `preview_text` | `build_template_preview_from_stored(components)` — already used in dashboard |
| `body_variables` | `body_template_variables(components)` — ordered keys (`"1"`, `"2"` or named keys) |
| `param_count` | `len(body_variables)` |

**Implementation hint:** Reuse logic from `list_templates()` in `whatsapp.py` but filter by `IntegrationAuthContext.tenant_id` instead of admin membership.

**Do not return:** Meta access tokens, full raw `components` JSON (optional later), other tenants’ data.

---

### 3.3 `GET /api/v1/integrations/api-campaigns`

**Purpose:** Return **live API campaigns** that external systems can trigger (same names used in `campaignName` for AiSensy send).

**Query params (optional):**

| Param | Default | Description |
|-------|---------|-------------|
| `status` | `live` | Campaign status |

**Response:** `200` JSON array

```json
[
  {
    "id": "660e8400-e29b-41d4-a716-446655440001",
    "name": "Test announcement",
    "status": "live",
    "campaign_type": "api",
    "template_name": "hello_world",
    "template_language": "en_US",
    "param_count": 3,
    "body_variables": ["1", "2", "3"],
    "preview_text": "Hello {{1}}, your batch {{2}} has test on {{3}}."
  }
]
```

**Field rules:**

| Field | Source |
|-------|--------|
| `id` | `Campaign.id` |
| `name` | `Campaign.name` — this is what CRM sends as `campaignName` |
| `status` | `Campaign.status` — only `live` useful for CRM |
| `campaign_type` | Must be `"api"` |
| `template_name` / `template_language` | From campaign row |
| `body_variables`, `param_count`, `preview_text` | Join `MessageTemplate` by tenant + template name + language; same helpers as above |

**Filter:**

```python
Campaign.tenant_id == ctx.tenant_id
Campaign.campaign_type == "api"
Campaign.status == "live"  # default
```

This matches `resolve_live_api_campaign()` in `aisensy_compat.py`.

---

## 4. Optional — Phase 2 (nice to have)

### 4.1 `GET /api/v1/integrations/templates/{name}`

Single template by name + optional `language` query param. Same object as list item.

### 4.2 `GET /api/v1/integrations/api-campaigns/{campaign_id}`

Single live API campaign + template metadata. Helps CRM validate before send.

### 4.3 `POST /api/v1/integrations/validate-key`

Returns `{ "ok": true, "tenant_id": "..." }` for connection test (no admin JWT).

---

## 5. What school-crm will do after Phase 1

No waservice changes required on school-crm side until Phase 1 is deployed. After that we will:

1. **Setup → WhatsApp Settings** — “Sync templates” calls `GET /integrations/templates` and `GET /integrations/api-campaigns`
2. Store locally: name, language, `param_count`, `preview_text`, `body_variables`
3. **New WhatsApp Campaign** — Step 3 shows **one input per template param** (`{{1}}`…`{{n}}`) from synced data, not hard-coded “announcement” fields
4. Map auto-filled values (student name, batch, etc.) in school-crm; user only types params the template actually needs
5. Send still uses existing `POST /campaign/t1/api/v2` with ordered `templateParams[]`

---

## 6. Acceptance criteria

| # | Test |
|---|------|
| 1 | `GET /integrations/templates` with valid `wsk` returns only that tenant’s templates |
| 2 | Invalid/missing key → `401` |
| 3 | `param_count` matches number of body variables Meta expects for send |
| 4 | `preview_text` shows `{{1}}` style placeholders (human-readable) |
| 5 | `GET /integrations/api-campaigns` returns only `campaign_type=api` and `status=live` by default |
| 6 | Campaign `name` matches what AiSensy send accepts as `campaignName` |
| 7 | Existing send endpoints unchanged |
| 8 | Rate limit applied |

**Manual test (curl):**

```bash
curl -sS "https://<API_HOST>/api/v1/integrations/templates" \
  -H "X-Integration-Key: wsk.<uuid>.<secret>"

curl -sS "https://<API_HOST>/api/v1/integrations/api-campaigns" \
  -H "X-Integration-Key: wsk.<uuid>.<secret>"
```

---

## 7. Suggested waservice code locations

| Task | Suggested file |
|------|----------------|
| New routes | `backend/app/api/v1/integrations.py` |
| Response schemas | `backend/app/schemas/integrations.py` (add `IntegrationTemplateItem`, `IntegrationApiCampaignItem`) |
| Query helpers | Reuse `body_template_variables`, `build_template_preview_from_stored` from `template_preview.py` |
| Auth | Existing `get_integration_auth` |
| Docs | Update `docs/EXTERNAL_CRM_INTEGRATION_SAFE.md` §6 table |

**Pseudocode for templates list:**

```python
@router.get("/templates", response_model=list[IntegrationTemplateItem])
def integration_list_templates(
    ctx: IntegrationAuthContext = Depends(get_integration_auth),
    db: Session = Depends(get_db),
    status: str = Query(default="APPROVED"),
):
    rows = (
        db.query(MessageTemplate)
        .filter(MessageTemplate.tenant_id == ctx.tenant_id)
        .filter(MessageTemplate.status == status.upper())
        .order_by(MessageTemplate.name)
        .all()
    )
    return [
        IntegrationTemplateItem(
            id=str(row.id),
            name=row.name,
            language=row.language,
            category=row.category,
            status=row.status,
            preview_text=build_template_preview_from_stored(row.components),
            body_variables=body_template_variables(row.components),
            param_count=len(body_template_variables(row.components)),
        )
        for row in rows
    ]
```

---

## 8. Out of scope (for this request)

- Changing Meta webhook URL or admin dashboard auth
- Allowing integration key to create/edit templates or campaigns
- Replacing AiSensy send payload format
- school-crm changes (we will implement after waservice Phase 1 is live)

---

## 9. Contact / workflow

1. waservice dev implements Phase 1  
2. Deploy to staging/production  
3. Share base URL + confirm endpoints live  
4. school-crm team implements sync + dynamic campaign form  
5. End-to-end test: sync templates → create campaign → send to test mobile  

---

## 10. Related docs

- school-crm: `docs/PAL_DIGITAL_WASERVICE_INTEGRATION.md`
- waservice: `docs/EXTERNAL_CRM_INTEGRATION_SAFE.md`
- waservice send: `backend/app/api/aisensy_compat.py`
