# Fee Installments — FeesCRM vs School CRM

**Purpose:** Single reference for fixing installment behaviour in `school-crm`.  
**FeesCRM path:** `F:\Rohit Development\FeesCRM`  
**School CRM path:** `F:\Rohit Development\Full school soft\school-crm`  
**Last reviewed:** June 2026

---

## 1. Executive summary

| Topic | FeesCRM | School CRM (today) |
|-------|---------|-------------------|
| When installments are set | **Separate step** after student + enrollment (Fee Agreement screen) | At **convert to admission** + **Adjust Fees** after enrollment |
| Who sets amounts | Staff adds rows manually; sums must match totals | Staff adds rows; sums must match net / pending |
| Auto-fill last row with balance | **No** | **Yes** (“Fill balance on last row”) |
| Running total while typing | **Yes** (fixed + tuition sections, client-side) | **Yes** (allocation summary line) |
| Block submit if totals wrong | **Server yes**, client display-only warning | **Server yes** on save |
| Payment → installment | Pick installment; suggested = full remaining | Pick installment (or auto first unpaid) |
| Partial payment | Allowed; shortfall added to **next** installment amount | Strict cap per installment (no shortfall roll) |
| Overpayment | **Carry-forward** to next installments | Rejected (validation error) |
| GST / cash-online split | Full model (18% inclusive online) | Not implemented (by design) |
| Late fees | Cron, 7-day grace, 0.15%/day | Cron, configurable (`config/fees.php`) |
| Amendment after payments | Delete unpaid rows, recreate; paid rows locked | Reschedule unpaid rows; paid rows kept |
| Documentation on change | Revision table + OTP re-approval | Fee history + audit log with installment JSON |

**Bottom line:** School CRM is closer to the right **workflow** for schools (fees at admission, explicit discount). FeesCRM has stronger **payment maths** (shortfall, carry-forward) and **split schedules** (fixed vs tuition). Your screenshot issue (₹30,000 + ₹20 vs ₹95,000 pending) is a **UX gap**: staff need clearer live feedback and safer defaults—not necessarily copying FeesCRM’s two-step flow.

---

## 2. FeesCRM — how installments actually work

### 2.1 Staff journey (order matters)

```
1. Create student (+ enrollment auto-created)
2. Open student profile
3. Create Fee Agreement  ← installments happen HERE, not at student create
4. (Optional) OTP parent approval
5. Record payments against installments
```

Routes (see `routes/web.php`):

- `GET  /students/{student}/fee-agreement/create` → create form  
- `POST /students/{student}/fee-agreement` → save agreements + installments  
- `GET  /students/{student}/payment/create` → pay against installment  

Controller: `app/Http/Controllers/FeeAgreementController.php`

### 2.2 Data model (3 layers)

```
Course template (fee_structures)
    → fixed components + tuition duration options
         ↓
Fee agreement (fee_agreements) — per student, per enrollment
    → negotiated total_fee, promised cash/online, one_time vs tuition
         ↓
Installments (installments) — concrete schedule
    → label, promised_date, installment_amount, amount_paid, amount_remaining, status
```

**Important:** One student enrollment can have **two** agreements:

- `one_time` — registration, infrastructure, study material, etc.  
- `tuition` — recurring tuition total  

Each has its **own** installment table rows. Staff must make each section’s rows sum to that section’s total.

### 2.3 Create form behaviour (`resources/views/fee-agreements/create.blade.php`)

**Totals section**

| Field | Behaviour |
|-------|-----------|
| `total_fee` | Staff enters negotiated final amount |
| Fixed fee fields | From course template (readonly) |
| `one_time_fees_total` | Sum of fixed components |
| `tuition_fees_total` | Auto: `total_fee - one_time_fees_total` |
| `promised_cash_amount` + `promised_online_amount` | Must equal `total_fee` |

**Installment rows (two repeaters)**

- **Fixed fees:** `onetime_amounts[]` + `onetime_dates[]`  
- **Tuition:** `tuition_amounts[]` + `tuition_dates[]`  

**JavaScript validation (display only, does not block submit):**

- `validateOneTimeTotals()` — sum(fixed rows) vs `one_time_fees_total`  
- `validateTuitionTotals()` — sum(tuition rows) vs `tuition_fees_total`  
- Green check or red “mismatch” message under each section  

**First row vs next rows**

| | First row | Next rows |
|---|-----------|-----------|
| Due date | Today | Next month, same day-of-month |
| Amount | Empty (placeholder only) | Empty — **staff types all amounts** |
| Auto remainder on last row | **No** | **No** |

**Server validation on save (`FeeAgreementController::store`)**

1. `cash + online = total_fee` (±0.01)  
2. `sum(onetime_amounts) = one_time_fees_total` (±0.01)  
3. `sum(tuition_amounts) = tuition_fees_total` (±0.01)  
4. At least one row per section array (even if that side is ₹0 — known quirk)

### 2.4 Payments (`app/Services/PaymentService.php`)

1. Staff picks **agreement** (fixed / tuition)  
2. Staff picks **installment** (pending/partial/overdue) or “advance” (no installment)  
3. Suggested amount = installment `amount_remaining`  
4. On save:
   - **Cash:** full amount reduces installment  
   - **Online/UPI:** amount is GST-**inclusive**; only **base** (÷1.18) reduces installment  
   - **Underpay:** shortfall added to **next** installment’s `installment_amount`  
   - **Overpay:** surplus applied to **later** installments (carry-forward)  
   - Installment status set to `paid` even when partially paid (known quirk)

### 2.5 Amendment after payments (`edit-amendment.blade.php` + `updateByStudent`)

- Paid installments: **shown locked**, not deleted  
- Unpaid (`pending` / `partial`): **deleted and recreated** from new form rows  
- If agreement was OTP-approved → amendment needs authority + reason + **new OTP**  
- Snapshot stored in `fee_agreement_revisions`  

### 2.6 Late fees (`PenaltyCalculationService` + `fees:process-late-fees`)

- Grace: **7 days** after `promised_date`  
- Rate: **0.15% per day** on `amount_remaining`  
- Creates/updates `penalties` row (`late_fee`, `pending`)  
- Sets installment `status = overdue`  

### 2.7 Known FeesCRM gaps (do not copy blindly)

- Partial pay marks installment `paid` → hard to pay remainder on same row  
- Penalties not payable through payment screen  
- Ledger hook for penalties unused  
- Dual agreement duplicates full cash/online promise on both rows  
- No client-side submit block when installment sums wrong (only red text)

---

## 3. School CRM — how installments work today

### 3.1 Staff journey

```
1. Enquiry → Convert to Admission
      ├── Course fee (from course master)
      ├── Discount
      ├── Misc charges (optional)
      └── Installment plan (optional toggle)
2. Admission tab — edit plan before approval
3. Approve → fee_structure + fee_installments created
4. Payments → allocated to installment
5. Super Admin → Adjust Fees → reschedule remaining installments
```

Key files:

| Area | Files |
|------|--------|
| Admission plan | `AdmissionFeePlanService`, `AdmissionFeePlanFormSchema`, `student-profile-admission-fees.blade.php` |
| Calculator | `App\Support\FeePlanCalculator` |
| On approve | `FeeStructureService::createFromAdmission`, `FeeInstallmentService::createForFeeStructure` |
| Adjust fees | `AdjustFeeStructureFormSchema`, `FeeStructureService::updateByAdmin` |
| Payments | `PaymentService`, `AddPaymentFormSchema` |
| Late fees | `PenaltyCalculationService`, `crm:process-late-fees` |

### 3.2 Data model

```
admissions
    ├── admission_misc_fees
    └── admission_installment_plans   (before enrollment)
         ↓ approve
fee_structures
    ├── fee_misc_charges
    ├── fee_installments            (live schedule)
    └── fee_penalties
payments.fee_installment_id
```

**Net fee formula:**  
`net_fee = course_fee - discount + sum(misc_charges)`

Installment rows must sum to **net_fee** (admission) or **pending_amount** (adjust fees).

### 3.3 What we added (FeesCRM-inspired features)

| Feature | Status |
|---------|--------|
| Live “To schedule / Allocated / Remaining” | Done |
| Fill balance on last row | Done (FeesCRM does **not** have this) |
| Suggest 50/50 plan | Done |
| Auto one-row “Full fee” when toggle on | Done |
| Reschedule unpaid installments on Adjust Fees | Done |
| Audit log with installment before/after | Done |
| Late fee cron | Done |
| Misc charges | Done (similar to fee_breakdowns, simpler) |

### 3.4 What we deliberately skipped

- Separate fee-agreement step after enrollment  
- Fixed vs tuition **dual** agreements  
- GST inclusive/exclusive payment split  
- Promised cash vs online amounts  
- Payment shortfall → inflate next installment  
- Payment overpay → carry forward  
- OTP parent approval on fee agreement  

---

## 4. Gap analysis — your screenshot (Adjust Fees)

**What you see:** Pending ₹95,000 · Allocated ₹50,020 · Remaining ₹44,980  
Rows: ₹30,000 + ₹20 (second row likely incomplete).

**FeesCRM would show the same maths problem** until staff finish entering rows—it only shows red/green mismatch text, it does not auto-complete amounts.

**What staff expect (reasonable, not all in FeesCRM):**

1. See **remaining** update on every keystroke — we have this  
2. **Cannot submit** until remaining = 0 — we validate on server; should also block Submit in UI  
3. **Next row defaults to remaining** when clicking “Add row” — **missing** (FeesCRM also missing)  
4. **Fill balance on last row** — we have button; should be more prominent / auto-offer  
5. When only one row left empty, auto-fill it — **missing**  

---

## 5. Feature parity matrix (what to fix next)

### Phase A — UX fixes (no schema change) — **recommended first**

| # | Feature | FeesCRM | School CRM | Action |
|---|---------|---------|------------|--------|
| A1 | Running total per schedule | Per section | Single net/pending | Keep single; improve visibility |
| A2 | Block submit if remaining ≠ 0 | Server only | Server only | **Add client-side disable on Submit** |
| A3 | New row pre-fills **remaining** amount | No | No | **Add** (better than FeesCRM) |
| A4 | Fill balance on last row | No | Button exists | Make primary action; keyboard hint |
| A5 | Due date: 1st = today, next = +1 month | Yes | Manual | **Add date helpers** on add row |
| A6 | Course fee ₹0 warning | N/A | Shows ₹0 | **Warn** if course fee is 0 at convert |

### Phase B — Payment behaviour (optional, coaching-heavy)

| # | Feature | FeesCRM | School CRM | Action |
|---|---------|---------|------------|--------|
| B1 | Suggested pay = installment pending | Yes | Partial | Already default amount in form |
| B2 | Underpay → roll to next installment | Yes | No | Add if institute wants strict schedules |
| B3 | Overpay → carry forward | Yes | No | Add or keep strict (schools often prefer strict) |
| B4 | Advance payment (no installment) | Yes | No | Consider optional |

### Phase C — Structure (only if product needs coaching model)

| # | Feature | FeesCRM | School CRM |
|---|---------|---------|------------|
| C1 | Fixed + tuition split | Yes | No |
| C2 | Fee breakdown components | `fee_breakdowns` | `admission_misc_fees` only |
| C3 | OTP approval on fee plan | Yes | No |

### Phase D — Already done in school-crm

- Admission-time installment plan  
- Misc charges  
- Late fees + cron  
- Adjust fees + reschedule + audit  
- Overdue installments report  

---

## 6. Recommended fix plan (agreed order)

### Step 1 — UX (1–2 days)

1. **On “Add row”:** pre-fill amount with current **remaining**  
2. **On Submit (convert + adjust):** disable if `abs(remaining) > 0.01`  
3. **Prominent banner** when remaining > 0: “₹X still unallocated”  
4. **Due date helper:** first row today; each new row +1 month from previous  
5. **Course fee 0:** block convert with message “Set fee in Courses admin”  

### Step 2 — Payment policy decision (product choice)

Ask institute type per deployment:

- **School (strict):** keep no carry-forward; staff must match installment exactly  
- **Coaching (flexible):** port FeesCRM `addShortfallToNextInstallment` + `carryForwardToNextInstallment` from `PaymentService.php`  

### Step 3 — Amendment polish

1. Show **paid installments read-only** in Adjust Fees (like FeesCRM amendment view)  
2. Only editable rows sum to **pending**  
3. Fee history reason + audit (already done)  

### Step 4 — Optional coaching module

Only if `INSTITUTE_TYPE=coaching` and user requests:

- Split misc into fixed components template  
- Tuition = net − fixed  
- Two installment repeaters  

---

## 7. Files to read in FeesCRM (in order)

1. `resources/views/fee-agreements/create.blade.php` — row builder + running totals  
2. `app/Http/Controllers/FeeAgreementController.php` — `store`, `updateByStudent`  
3. `app/Services/PaymentService.php` — carry-forward / shortfall  
4. `app/Services/FeeCalculationService.php` — GST  
5. `resources/views/fee-agreements/edit-amendment.blade.php` — paid vs unpaid  
6. `app/Services/PenaltyCalculationService.php` — late fees  
7. `resources/views/payments/create.blade.php` — installment picker  

## 8. Files to change in School CRM (for Step 1 UX)

1. `app/Filament/Forms/AdmissionFeePlanFormSchema.php`  
2. `app/Filament/Forms/AdjustFeeStructureFormSchema.php`  
3. `app/Support/FeePlanCalculator.php`  
4. `resources/views/filament/pages/partials/student-profile-admission-fees.blade.php`  
5. `app/Filament/Pages/StudentProfilePage.php` — `addInstallmentRow()`, modal footer actions  

---

## 9. Decision log (fill in when fixing)

| Decision | Choice | Date | Notes |
|----------|--------|------|-------|
| Payment overpay | Strict / Carry-forward | **auto** (coaching=flexible) | | |
| Payment underpay | Strict / Shortfall to next | **auto** (coaching=flexible) | | |
| Dual fixed+tuition split | Yes / No | | |
| GST on online payments | Yes / No | | |
| Auto-fill new row with remaining | Yes / No | | Recommended: Yes | **Yes** (Phase A) |

---

## 10. Quick reference — validation rules

### School CRM — admission convert

```
net = course_fee - discount + sum(misc)
IF use_installment_plan:
  sum(installment_plan.amount) MUST equal net (±0.01)
ELSE:
  one "Full fee" installment created on approve
```

### School CRM — adjust fees

```
new_net = course_fee - discount + sum(misc_on_fee_structure)
new_pending = new_net - paid_amount
IF reschedule_installments:
  sum(installment_plan.amount) MUST equal new_pending (±0.01)
  delete unpaid fee_installments; create new; keep paid rows
```

### FeesCRM — fee agreement create

```
cash + online = total_fee
sum(fixed_installments) = one_time_fees_total
sum(tuition_installments) = tuition_fees_total
tuition_fees_total = total_fee - one_time_fees_total
```

---

*This document should be updated after each fix phase. Do not copy FeesCRM UI; copy **behaviours** selectively per institute type.*
