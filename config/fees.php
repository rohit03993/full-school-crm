<?php

return [

  /*
  |--------------------------------------------------------------------------
  | Late fee on overdue installments
  |--------------------------------------------------------------------------
  |
  | Applied by `php artisan crm:process-late-fees` (scheduled daily).
  | Formula: pending_installment_amount × daily_rate × days_after_grace
  |
  */

    'late_fee' => [
        'enabled' => env('FEE_LATE_FEE_ENABLED', true),
        'grace_days' => (int) env('FEE_LATE_FEE_GRACE_DAYS', 7),
        'daily_rate' => (float) env('FEE_LATE_FEE_DAILY_RATE', 0.0015),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment allocation across installments
    |--------------------------------------------------------------------------
    |
    | strict   — partial underpay stays on the installment unless flexible shortfall UI is used
    | flexible — underpay can roll shortfall to the next installment or a new row
    | auto     — same as flexible (default)
    |
    | Overpayment on any mode automatically clears the selected installment and reduces
    | future installments, with a note saved on the payment receipt.
    |
    */

    'payment' => [
        'allocation' => env('FEE_PAYMENT_ALLOCATION', 'auto'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Automated WhatsApp fee reminders
    |--------------------------------------------------------------------------
    |
    | Requires an approved Meta template mapped in WhatsApp → Automations.
    | Scheduled via `crm:send-fee-reminders` (daily).
    |
    */

    'reminder' => [
        'min_days_overdue' => (int) env('FEE_REMINDER_MIN_DAYS_OVERDUE', 1),
        'cooldown_days' => (int) env('FEE_REMINDER_COOLDOWN_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Coaching: cash vs online fee agreement + GST on online overage
    |--------------------------------------------------------------------------
    |
    | When enabled in Settings → Fee Settings, staff record how much of the net
    | tuition fee the student agreed to pay in cash vs online. If online tuition
    | payments exceed the online plan, GST is charged on the excess only.
    |
    */

    'online_allowance_gst' => [
        'enabled' => env('FEE_ONLINE_ALLOWANCE_GST_ENABLED', false),
        'percentage' => (float) env('FEE_GST_PENALTY_PERCENTAGE', 18),
    ],

];
