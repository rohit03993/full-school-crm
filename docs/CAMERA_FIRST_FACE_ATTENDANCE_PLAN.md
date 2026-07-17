# Camera-first Face Attendance (RFID untouched)

**Status:** CRM camera-punch API ready · Android/Face API Mode B still needed  
**Date:** July 2026

---

## Is this okay?

**Yes.** Two independent modes:

| Mode | Trigger | RFID machine | Use when |
|------|---------|--------------|----------|
| **A — Card / fingerprint** | ZKTeco ADMS | Used | Keep as today |
| **B — Camera attendance** | Face → roll → CRM punch | **Not used** | Camera kiosk |

Mode B does **not** change `/iclock/*` or how ZKTeco marks punches.

**Critical for Mode A:** leave **Require face verification = OFF** on biometric devices.

---

## Roll number is the key

Both paths meet at `punch_logs.employee_id` = enrollment / roll number.

```text
Camera:
  Face match → roll → POST /api/face-verify/camera-punch → punch_logs → attendance

Biometric (unchanged):
  Card → /iclock/cdata → biometric_punches → punch_logs → attendance
```

---

## CRM pieces (done)

| Piece | Detail |
|-------|--------|
| Endpoint | `POST /api/face-verify/camera-punch` |
| Auth | Bearer `FACE_VERIFY_SERVICE_TOKEN` + HMAC `X-Face-Verify-Signature` |
| Effect | Writes `punch_logs` for the roll, logs `face_verification_requests` with `source=camera_kiosk`, runs `PunchAttendanceProcessor` |
| Does not | Create `biometric_punches`, call ADMS, or gate card punches |
| Cooldown | `FACE_VERIFY_CAMERA_PUNCH_COOLDOWN_SECONDS` (default 60) |
| Device label on punch | `Face Camera Kiosk` (never the ZKTeco machine name) |

### Payload

```json
{
  "enrollment_number": "FI 0801",
  "device_id": "<kiosk-uuid>",
  "student_id": "<optional face-api id>",
  "request_id": "<optional>",
  "score": 0.62,
  "timestamp": "2026-07-17T10:00:00+05:30"
}
```

### Deploy note

After pulling CRM code on the server:

```bash
cd /home/folksindia/htdocs/folksindia.org
php artisan optimize:clear
```

Optional in `.env`:

```env
FACE_VERIFY_CAMERA_PUNCH_COOLDOWN_SECONDS=60
```

---

## Caveat (Face app still required)

Roll sync alone is not enough. Each student needs a one-time face enrollment (photos → embedding) in Face API / kiosk before camera can identify them.

---

## What Face app (`proxy attend`) still needs

1. Gallery / 1:N identify
2. Call CRM `camera-punch` on match
3. Android mode: continuous camera attendance (no RFID wait)

See `proxy attend/docs/CAMERA_ATTENDANCE_MODE.md`.
