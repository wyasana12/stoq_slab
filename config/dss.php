<?php

use App\Enums\MutationStatus;

return [
    'default_history_days' => 30,
    'history_days'         => [7, 30, 60, 90],

    'fast_moving_days_of_stock' => 14,
    'slow_moving_days_of_stock' => 60,

    'warehouse_activity' => [
        'active_threshold'   => 150,
        'inactive_threshold' => 75,
    ],

    'recommendation' => [
        'fast_moving' => 'RESTOCK_OR_TRANSFER_IN',
        'slow_moving' => 'TRANSFER_OUT',
        'normal'      => 'HOLD',
    ],

    'completed_statuses' => collect(MutationStatus::cases())
        ->filter(fn($s) => str_ends_with($s->value, '_COMPLETED'))
        ->map(fn($s) => $s->value)
        ->push('SUCCESS')
        ->push(MutationStatus::EXPIRED_DISPOSAL->value)
        ->push(MutationStatus::DAMAGED_DISPOSAL->value)
        ->push(MutationStatus::PRODUCTION_DEFECT_DISPOSAL->value)
        ->values()
        ->all(),
];
