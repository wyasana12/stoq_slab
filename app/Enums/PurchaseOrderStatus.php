<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';

    public function isFinal(): bool
    {
        return in_array($this, [
            self::REJECTED,
            self::CLOSED,
            self::CANCELLED,
        ]);
    }

    public function canTransition(self $newStatus)
    {
        return match ($this) {
            self::DRAFT => in_array($newStatus, [
                self::SUBMITTED,
                self::APPROVED,
                self::REJECTED,
                self::CLOSED,
                self::CANCELLED,
            ]),
            self::SUBMITTED => in_array($newStatus, [
                self::APPROVED,
                self::REJECTED,
                self::CLOSED,
                self::CANCELLED,
            ]),
            self::APPROVED => in_array($newStatus, [
                self::CLOSED
            ]),
        };
    }
}
