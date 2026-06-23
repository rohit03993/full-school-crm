# Calling CRM reference → school-crm native build

**Reference project:** `F:\Rohit Development\bulk marketing\bulk marketing\`  
**Purpose:** Study only. All features will be built **natively inside school-crm** — no runtime link to bulk marketing.

---

## What the reference CRM does

Laravel 12 + Breeze + Tailwind/Alpine telecalling CRM for lead follow-up and WhatsApp (AiSensy).

### Calling (no VoIP — phone dialer only)

- Staff tap **Call Now** → `tel:+91…` opens the mobile dialer.
- After the call, **Log Call Result** wizard records outcome.
- Every call stored in `student_calls` with staff, time, status, notes, tags, follow-up datetime.
- Student row updated: `last_call_at`, `last_call_status`, `total_calls`, `next_followup_at`, pipeline status.
- **3 failed attempts** (no answer, busy, etc.) → permanent block from call queue.
- **Call Queue:** one lead at a time, auto-advance after log; priority: overdue → due today → never called.

### WhatsApp (AiSensy API)

- **Templates** with param mapping (`student.name`, school, class, caller name, etc.).
- **Bulk campaigns** by school/class → batched queue job.
- **Single send** from student profile.
- **Post-call auto-message** on connected outgoing calls (admin setting).

### Dashboard & profile

- Telecaller dashboard: calls today, pending queue, follow-ups, score.
- Student profile: call timeline (who called, when, notes), message history.
- Phone page: all calls + WhatsApp for one number.

### Mobile UX (telecaller)

- Bottom tab bar: Home, My Leads, **Call Queue**, Follow-ups, Report.
- Large tap targets, bottom-sheet log-call modal, follow-up alert strip.
- Same web routes — responsive Blade, not a separate app.

---

## Map to school-crm (native)

| Reference | school-crm today | Native build |
|-----------|------------------|--------------|
| `students.lead_status` | `VisitStatus` on enquiry/visit | Update enquiry/visit status after call |
| `next_followup_at` | `Visit.next_follow_up_date` | Set on visit after each call |
| `student_calls` | Missing | New `student_calls` table |
| `assigned_to` | `Enquiry.meeting_with_user_id` | Assigned leads = enquiries for staff |
| Call queue | Missing | Management → **Call Queue** (mobile-first page) |
| Follow-ups page | `FollowUpsPage` (visit dates) | Merge visit + call follow-ups |
| Student show calls/messages | Profile tabs (Visits only) | Add **Calls** + **Messages** tabs |
| Schools/class sections | Course, Batch | Target WhatsApp by course/batch later |
| AiSensy campaigns | Not present | Phase 2: templates + campaigns in Setup |

**Keep school-crm identity:** one mobile = one student; Student Profile = hub; visits for walk-ins; admission for convert.

---

## Recommended build order in school-crm

### Phase A — Calling core
1. Migration: `student_calls` (+ optional `students.last_call_at`, `total_calls` for fast dashboard).
2. Enums: call status, direction, who answered.
3. `CallLogService` — log call, follow-up rules, 3-strike block (configurable).
4. Wire call outcome → enquiry status + new/update visit + `next_follow_up_date`.
5. Student Profile → **Calls** tab (timeline + log call action).
6. **Call Queue** page (mobile-first) for staff.
7. Extend **Follow-ups** with call-based due items.

### Phase B — Telecaller mobile UX
1. Simplified Management home (pending counts).
2. **My Leads** (assigned enquiries).
3. Log-call wizard (bottom sheet on mobile).

### Phase C — Reporting
1. Call report (filters, new vs follow-up split).
2. Admin staff call history (optional score/leaderboard).

### Phase D — WhatsApp
1. Institute settings: AiSensy API key, URL.
2. Template CRUD + param mapping (student, course, staff).
3. Single send + bulk campaign + post-call auto-send.
4. Queue jobs for batch send.

---

## Key reference files

| Feature | Path |
|---------|------|
| Call logging | `app\Http\Controllers\StudentCallController.php` |
| Call queue | `app\Http\Controllers\CallQueueController.php` |
| WhatsApp send | `app\Services\AisensyService.php` |
| Campaigns | `app\Http\Controllers\CampaignController.php` |
| Mobile nav | `resources\views\layouts\bottom-nav.blade.php` |
| Call queue UI | `resources\views\crm\students\call-queue.blade.php` |
| Log call modal | `resources\views\crm\students\partials\log-call-modal.blade.php` |
| Student profile | `resources\views\crm\students\show.blade.php` |

---

## Confirmed product decisions (2026-06)

| Topic | Decision |
|-------|----------|
| WhatsApp | **Pal Digital** API — native build in Phase D (not AiSensy) |
| Callers | **All staff**; caller stored on `student_calls.user_id` |
| Assignment | `enquiries.meeting_with_user_id` |
| 3-strike block | Yes |
| Mobile UI | Mobile-friendly Filament pages |

See `docs/CALLING_CRM_DECISIONS.md` for implementation status.

## Decisions needed before coding

1. **3-strike block** — same as reference (permanent after 3 not-connected)?
2. **Assignment** — use existing `meeting_with_user_id` on enquiry, or new field?
3. **WhatsApp provider** — keep AiSensy or different API?
4. **Telecaller role** — new Spatie role or flag on Staff user?
5. **Mobile UI** — custom Filament/Livewire pages under `/admin` or separate telecaller route group?

When you confirm, we start **Phase A** in school-crm only.
