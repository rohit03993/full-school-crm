# RFID + AI Face Verification — Full Handoff Plan

**Audience:** Product owner + AI/dev agents building the **separate** Face Verify stack  
**Related CRM:** School CRM (`school-crm` — Laravel/Filament)  
**Date:** July 2026  
**Status:** Architecture approved — build as **separate projects**, integrate via APIs

---

## 0. How to use this document

Give this file to the agent that will build:

1. **Python Face Verify API** (`face-verify-api`)
2. **Android Kiosk App** (`face-verify-kiosk`)

The School CRM stays the **source of truth for students and final attendance**.  
The new projects own **face AI + camera verification UX**.

When the Face Verify team needs CRM work, use the section **“When we need CRM help”** — those items are done **in the School CRM repo**, not inside Python/Android.

---

## 1. Objective

Prevent **proxy attendance** while keeping existing **RFID / biometric machines**.

**Rule:** Attendance is marked in CRM **only after** successful AI face verification against the student linked to that card/PIN.

---

## 2. High-level decision (locked)

| Decision | Choice |
|----------|--------|
| Build location | **Separate projects** (Python + Android), **not** inside School CRM |
| Attendance authority | **School CRM** decides Present/IN/OUT |
| RFID machine | Keep as-is (ZKTeco ADMS or similar) |
| Face matching | Embeddings (InsightFace / ArcFace family), not raw photo compare |
| First MVP | One gate + one Android kiosk + one student batch |

**Do not** put Python ML or Kotlin app code into the Laravel repo.

---

## 3. System architecture

```text
┌─────────────────────┐
│  RFID / Biometric   │  Student taps card
│  Machine (existing) │
└──────────┬──────────┘
           │ ADMS POST /iclock/*  (already in CRM)
           ▼
┌─────────────────────┐
│   SCHOOL CRM        │  Identifies student by PIN = enrollment_number
│   Laravel + MySQL   │  Creates verification request (PENDING)
│                     │  Does NOT mark attendance yet
└──────────┬──────────┘
           │ WebSocket / FCM / poll API
           │ payload: request_id, student_id, roll, name, embedding_ref
           ▼
┌─────────────────────┐
│  ANDROID KIOSK      │  Capture face, match embedding
│  Kotlin             │  Success / Fail UI + sound
└──────────┬──────────┘
           │ result: request_id, score, pass/fail, optional fail image
           ▼
┌─────────────────────┐
│  PYTHON FACE API    │  Enroll embeddings, optional server match,
│  InsightFace/ONNX   │  store templates, health checks
└──────────┬──────────┘
           │ on PASS → CRM callback
           ▼
┌─────────────────────┐
│   SCHOOL CRM        │  Approve → write punch_logs → existing
│                     │  PunchAttendanceProcessor (WhatsApp, TV, IN/OUT)
└─────────────────────┘
```

### Repo layout (recommended)

```text
face-verify/                    ← NEW monorepo OR two repos
  api/                          ← Python (FastAPI recommended)
  android/                      ← Kotlin kiosk app
  docs/                         ← copy of this handoff + API contracts

school-crm/                     ← EXISTING (Laravel)
  docs/RFID_AI_FACE_VERIFICATION_HANDOFF.md  ← this file
```

---

## 4. How School CRM attendance works today (important)

Agents building Face Verify must understand this so they don’t invent a second attendance system.

### Current punch pipeline

```text
ZKTeco ADMS → biometric_punches (raw)
            → punch_logs (employee_id = device user PIN)
            → PunchAttendanceProcessor
            → attendance marked + WhatsApp + TV display
```

### Key CRM files (reference only — CRM repo)

| Piece | Path in School CRM |
|-------|--------------------|
| ADMS ingest | `app/Services/Biometric/BiometricAdmsIngestService.php` |
| ADMS routes | `routes/biometric.php` → `/iclock/*` |
| Punch processing | `app/Services/Punch/PunchAttendanceProcessor.php` |
| IN/OUT logic | `app/Services/Punch/PunchInOutCalculator.php` |
| Student lookup | `enrollment_number` (active enrollment) = device `user_pin` / `employee_id` |
| Biometric config | `config/biometric.php` |
| Devices allowlist | `biometric_devices` (serial number) |
| Live / manual attendance | `app/Filament/Pages/AttendancePage.php` |
| TV display | Attendance display (reads `punch_logs` only) |

### Student ↔ RFID identity today

- **No separate RFID card UID table yet.**
- Device PIN = `enrollments.enrollment_number` (normalized uppercase/trim).
- For MVP, treat **Card UID / PIN = enrollment_number** unless CRM later adds a real card-mapping table.

### Critical change CRM must make later

Today ADMS often **mirrors to `punch_logs` and processes attendance immediately** (`BIOMETRIC_ADMS_PROCESS_INLINE`).

For face verify, CRM must:

1. Receive RFID punch.
2. Create **pending verification** (do **not** final-process attendance yet).
3. Notify Android kiosk.
4. On **PASS** → write punch + run existing processor.
5. On **FAIL / TIMEOUT** → log rejection; no attendance (or optional manual approve).

---

## 5. Product phases

### Phase 1 — Student face registration (Python + CRM UI later)

**Goals**

- Register / link student identity (roll = enrollment_number).
- Capture **5–10** face images (angles).
- Generate **face embeddings**; store embeddings (not only photos).
- Store photos for admin audit.

**Owned by**

| Work | Owner |
|------|-------|
| Embedding generation & storage | **Python API** |
| Who the student is (id, roll, name, batch) | **CRM data** (API) |
| Enrollment UI (capture from admin/phone) | Android enrollment mode **or** CRM admin calling Python |
| Link roll ↔ student | **CRM** |

### Phase 2 — Android AI verification kiosk

**Always-on kiosk**

- Camera preview active
- Listen for verification requests
- Capture face → verify → return result
- Success / Failed screen
- Voice or beep
- Auto-start on boot
- Kiosk mode (lock task)

**Owned by:** Android project (+ Python for server-side match if used)

### Phase 3 — Live attendance flow (CRM-gated)

1. Student taps RFID on machine.  
2. CRM receives Card/PIN via ADMS.  
3. CRM finds student by `enrollment_number`.  
4. CRM creates verification request → pushes to Android.  
5. Android captures face.  
6. AI compares captured face ↔ registered embedding.  
7. If score ≥ threshold → CRM marks attendance.  
8. If fail → reject UI + save image for admin review.

### Future Phase A — Face only (no RFID)

Student walks to camera; AI identifies face; CRM marks attendance.

### Future Phase B — Multi-gate campus

Multiple Android devices: Entry, Classroom, Library, Hostel, Exit → movement timeline.

---

## 6. Technology stack (new projects)

| Layer | Technology |
|-------|------------|
| Face API | Python 3.11+, FastAPI, InsightFace / ArcFace (ONNX for mobile export) |
| Embeddings DB | MySQL or PostgreSQL (can be Face Verify’s own DB); vectors as BLOB/JSON |
| Android | Kotlin, CameraX, ONNX Runtime or TFLite for on-device match |
| CRM ↔ apps | REST + WebSocket (preferred for instant RFID→phone) |
| Auth | Device API tokens issued by CRM (or Face API with CRM-signed JWT) |

**AI note:** InsightFace Python is for **server enroll/match**. On Android use **ONNX/TFLite** of the same embedding family so scores are comparable. Prefer **on-device match** for gate speed; optional server fallback.

---

## 7. Data model (Face Verify + CRM contract)

### Owned by Face Verify DB (Python)

| Table | Purpose |
|-------|---------|
| `face_students` | Local mirror: `crm_student_id`, `enrollment_number`, name |
| `face_embeddings` | `crm_student_id`, model_version, embedding blob, enrolled_at |
| `face_photos` | Enrollment / fail images (paths or object storage) |
| `verification_events` | request_id, score, result, device_id, timestamps |
| `kiosk_devices` | device_id, gate_name, token hash, last_seen |

### Owned by School CRM (existing + new)

| Table / concept | Purpose |
|-----------------|--------|
| `students`, `enrollments` | Identity; `enrollment_number` = RFID PIN |
| `biometric_devices`, `biometric_punches` | Machine ADMS |
| `punch_logs` | Final punches (only after verify PASS) |
| **NEW** `face_verification_requests` | pending / verified / rejected / expired / manual |
| **NEW** `face_verification_devices` | which Android kiosk serves which gate/SN |
| Attendance reports / TV / WhatsApp | Unchanged consumers of verified punches |

### Attendance / verification statuses

| Status | Meaning |
|--------|---------|
| `pending_verification` | RFID seen; waiting for face |
| `verified` | Face matched; punch written |
| `rejected` | Face mismatch |
| `expired` | No face within timeout (e.g. 25s) |
| `manual_approval` | Admin forced accept after review |

---

## 8. API contracts (draft for agents)

Base idea: Face Verify talks to CRM; Android talks to Face Verify **and/or** CRM.

### 8.1 CRM → Face Verify / Android (when RFID arrives)

CRM creates request and notifies kiosk:

```json
{
  "verification_request_id": "uuid",
  "crm_student_id": 12345,
  "enrollment_number": "DISP-101",
  "student_name": "Aarjav Jain",
  "batch_name": "Class 11-JEE BATCH I",
  "gate_id": "entry-main",
  "device_serial": "ZKTecoSN...",
  "punch_time": "2026-07-17T08:15:02+05:30",
  "timeout_seconds": 25,
  "embedding_ref": "emb_abc123"
}
```

### 8.2 Android / Face API → CRM (result)

```json
{
  "verification_request_id": "uuid",
  "result": "verified",
  "match_score": 0.62,
  "threshold": 0.40,
  "captured_at": "2026-07-17T08:15:04+05:30",
  "fail_image_url": null,
  "kiosk_device_id": "android-gate-1"
}
```

On `verified`, CRM writes punch and runs existing attendance processor.

### 8.3 CRM → Face API (enrollment sync)

```json
{
  "crm_student_id": 12345,
  "enrollment_number": "DISP-101",
  "name": "Aarjav Jain",
  "photos_base64_or_urls": ["..."],
  "replace_existing": true
}
```

Response: embedding id + model version.

### 8.4 Face API → CRM (optional health)

Device online, last verify, model version — for CRM admin “Device management” page.

---

## 9. When we will need CRM data / CRM development help

Use this checklist with the **School CRM agent**. Face Verify agents should **request** these; they should not invent parallel student/attendance databases as the source of truth.

### A. Needed immediately (before useful MVP)

| # | CRM help needed | Why |
|---|-----------------|-----|
| A1 | **Read-only Student/Enrollment API** | Android/Python need `crm_student_id`, `enrollment_number`, name, batch, photo URL for display |
| A2 | **Stable student identifier** | Always use CRM `students.id` + `enrollment_number` |
| A3 | **Auth for device APIs** | API tokens / Sanctum / signed JWT so kiosks aren’t public |
| A4 | **List active students by batch** (optional but useful) | Bulk face enrollment for one class |

**Minimal student payload CRM should expose:**

```json
{
  "id": 12345,
  "name": "Aarjav Jain",
  "enrollment_number": "DISP-101",
  "batch_id": 9,
  "batch_name": "Class 11-JEE BATCH I",
  "is_active": true,
  "profile_photo_url": "https://..."
}
```

### B. Needed to connect RFID → face verify (core integration)

| # | CRM help needed | Why |
|---|-----------------|-----|
| B1 | **Hold ADMS punches** until face result | Stop auto `punch_logs` + inline process for gated devices |
| B2 | **Create `face_verification_requests`** on RFID | Pending queue for Android |
| B3 | **Push/notify kiosk** (WebSocket endpoint or FCM topic) | Phone must wake in &lt;1–2s |
| B4 | **Callback endpoint** `POST /api/face-verify/result` | Approve/reject → write punch only on pass |
| B5 | **Map gate ↔ biometric device serial ↔ Android kiosk** | Which phone serves which machine |
| B6 | **Timeout job** | Expire pending after N seconds |

**This is the main CRM coding block.** Without B1–B4, Face Verify is a demo camera app only.

### C. Needed for admin UX in CRM (can be phase 1.5)

| # | CRM help needed | Why |
|---|-----------------|-----|
| C1 | Filament: **Register / update face** (upload or deep-link to enrollment app) | Ops staff work in CRM they already know |
| C2 | Filament: **Failed verification logs** + captured images | Review proxy attempts |
| C3 | Filament: **Device management** (kiosks online/offline) | Support |
| C4 | Filament: **Re-verify / manual approve** | Fix false rejects |
| C5 | Reports: verified vs rejected vs expired | Management |

### D. Needed later (not MVP)

| # | CRM help needed | Why |
|---|-----------------|-----|
| D1 | Real **RFID card UID** table separate from enrollment_number | If cards ≠ roll numbers |
| D2 | Face-only mode (no RFID) API | Future Phase A |
| D3 | Multi-gate timeline / location punches | Future Phase B |
| D4 | Permissions (who may approve failed verifies) | e.g. Super Admin only |

### E. CRM data Face Verify must **never** own as source of truth

- Fee balances, admissions, WhatsApp templates, staff roles  
- Final attendance ledgers (CRM `attendances` / punch pipeline)  
- Institute branding (CRM can expose name/logo if needed for kiosk splash)

Face Verify may **cache** student name/roll/embedding for offline gates, but CRM remains master for identity and attendance.

---

## 10. Android app features (build checklist)

- [ ] Camera preview (CameraX)
- [ ] Face detection (ML Kit or model-based)
- [ ] Face verification (ONNX embedding + cosine similarity)
- [ ] Online sync of embeddings for assigned gate students
- [ ] Offline verification (cached embeddings) — optional MVP+
- [ ] Retry capture (1–2 times)
- [ ] Voice / beep feedback
- [ ] Success (green) / Fail (red) full-screen
- [ ] Kiosk / lock task mode
- [ ] Auto start on boot
- [ ] Secure device token storage
- [ ] Remote update strategy (Play internal / MDM / sideload policy)

---

## 11. Python API features (build checklist)

- [ ] `POST /enroll` — images → embedding
- [ ] `POST /match` — image or embedding vs student (server fallback)
- [ ] `GET /students/{crm_id}/embedding` — for Android cache
- [ ] Model version header on all match results
- [ ] Store fail images + scores
- [ ] Health `/health`
- [ ] Auth via CRM-issued device/service tokens
- [ ] Export mobile ONNX model + recommended threshold docs

---

## 12. MVP definition (ship this first)

1. Enroll faces for **one batch** via Python (+ simple admin or script).  
2. **One** Android kiosk + **one** RFID gate.  
3. CRM: RFID → pending → Android verify → PASS writes punch via existing processor.  
4. FAIL saves image + log; no attendance.  
5. Timeout → expired.  
6. Manual approve in CRM for edge cases (optional but recommended).  

**Out of scope for MVP:** multi-campus timeline, face-only entry, training custom models from scratch, replacing ZKTeco hardware.

---

## 13. Campus / ops requirements

- Mount Android at **same gate** as RFID (tap then look at camera within ~5–15s).  
- Stable power + network (or offline embedding cache).  
- Good lighting; avoid strong backlight.  
- Re-enroll if major appearance change.  
- Tune threshold using real fail/pass logs (start conservative).

---

## 14. Suggested build order for the other agent

```text
Week 1–2   Python: enroll + match API + store embeddings
Week 2–3   Android: camera kiosk UI + on-device match vs cached embedding
Week 3     Contract tests with mock CRM JSON (no CRM yet)
Week 4+    REQUEST CRM HELP (section 9.B) — gate ADMS + callback
Week 5     End-to-end one gate pilot
Week 6     Admin fail logs + reports (CRM section 9.C)
```

**Parallel track:** CRM agent can start A1–A3 (student read API + tokens) while Python/Android build.

---

## 15. Message templates for the other agent

### Start Face Verify (Python + Android)

> Build a separate Face Verify system per `docs/RFID_AI_FACE_VERIFICATION_HANDOFF.md`.  
> Do **not** modify School CRM attendance math.  
> Implement Python enroll/match API and Android kiosk first against **mock CRM payloads**.  
> When RFID integration is needed, ask for CRM items in section **9.B**.

### Ask School CRM agent for integration

> Implement face-verification gate per section **9.B** of `docs/RFID_AI_FACE_VERIFICATION_HANDOFF.md`:  
> hold ADMS punch → create pending request → notify kiosk → on verified callback write `punch_logs` and run existing `PunchAttendanceProcessor`.  
> Expose student read API per **9.A**. Do not break existing manual attendance or TV display for already-verified punches.

---

## 16. Risks

| Risk | Mitigation |
|------|------------|
| RFID machine beeps before face verify | CRM is attendance authority; unverified punches don’t count |
| Lookalike / photo spoof | Threshold + fail logs; add liveness later |
| Network down at gate | On-device embeddings cache |
| Rush queue | Short UI; optional second kiosk; queue requests |
| Wrong PIN on card | Fix enrollment_number mapping in CRM first |

---

## 17. Bottom line

- **Possible:** Yes.  
- **Build:** Separate **Python + Android** projects.  
- **CRM:** Supplies **student identity** early; supplies **RFID hold + approve punch** when ready for live attendance.  
- **Do not** duplicate final attendance in the Face Verify DB.

---

## 18. Document control

| Item | Value |
|------|-------|
| Location | `school-crm/docs/RFID_AI_FACE_VERIFICATION_HANDOFF.md` |
| Related CRM brief | `docs/GENERIC_SCHOOL_CRM_PROJECT.md` |
| Copy into | New `face-verify` repo `docs/` when that repo is created |

When the Face Verify repo is created, **copy this file** there so the other agent has it without opening School CRM.
