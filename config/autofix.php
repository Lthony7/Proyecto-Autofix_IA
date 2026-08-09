<?php

return [
    'tax_rate' => env('AUTOFIX_TAX_RATE', '0.00'),
    'payment_backdate_days' => (int) env('AUTOFIX_PAYMENT_BACKDATE_DAYS', 7),
    'appointment_reminders' => [
        'enabled' => filter_var(env('AUTOFIX_APPOINTMENT_REMINDERS_ENABLED', false), FILTER_VALIDATE_BOOL),
        'window_minutes' => (int) env('AUTOFIX_APPOINTMENT_REMINDERS_WINDOW_MINUTES', 1440),
    ],
];
