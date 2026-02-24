<?php

namespace App\Enums;

enum ReturnStatus: string
{
    case REQUESTED = 'requested';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case RETURNING = 'returning';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function isFinal(): bool
    {
        return in_array($this, [
            self::REJECTED,
            self::COMPLETED,
            self::CANCELLED,
        ]);
    }

    public function canTransition(self $newStatus)
    {
        return match ($this) {
            self::REQUESTED => in_array($newStatus, [
                self::APPROVED,
                self::REJECTED,
                self::RETURNING,
                self::COMPLETED,
                self::CANCELLED,
            ]),
            self::APPROVED => in_array($newStatus, [
                self::RETURNING,
                self::COMPLETED,
            ]),
        };
    }
}
