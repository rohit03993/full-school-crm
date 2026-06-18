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
    | strict   — payment cannot exceed the selected installment pending amount
    | flexible — underpay rolls shortfall to the next installment; overpay
    |            carries forward (FeesCRM-style)
    | auto     — same as flexible (default)
    |
    */

    'payment' => [
        'allocation' => env('FEE_PAYMENT_ALLOCATION', 'auto'),
    ],

];
