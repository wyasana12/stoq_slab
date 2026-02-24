<?php

namespace App\Enums;

enum TransferStatus: string
{
    case DRAFT = 'draft';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case RECEIVED = 'received';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function isFinal(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::REJECTED,
            self::CANCELLED,
        ]);
    }

    public function canTransition(self $newStatus)
    {
        return match ($this) {
            self::DRAFT => in_array($newStatus, [
                self::APPROVED,
                self::REJECTED,
                self::COMPLETED,
                self::CANCELLED,
            ]),
            self::APPROVED => in_array($newStatus, [
                self::RECEIVED,
                self::COMPLETED,
            ]),
        };
    }
}
