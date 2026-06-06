<?php

use App\Enums\MutationStatus;

return [
    'default_history_days' => 30,
    'history_days'         => [7,30, 60, 90],

    'fast_moving_days_of_stock' => 14,
    'slow_moving_days_of_stock' => 60,

    'recommendation' => [
        'fast_moving' => 'RESTOCK_OR_TRANSFER_IN',
        'slow_moving' => 'TRANSFER_OUT',
        'normal'      => 'HOLD',
    ],

    'completed_statuses' => collect(MutationStatus::cases())
        ->filter(fn($s) => str_ends_with($s->value, '_COMPLETED'))
        ->map(fn($s) => $s->value)
        ->push('SUCCESS')
        ->values()
        ->all(),
];
