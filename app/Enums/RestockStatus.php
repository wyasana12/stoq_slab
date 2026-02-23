<?php

namespace App\Enums;

enum RestockStatus: string
{
    case REQUESTED = 'requested';
    case APPROVED = 'approved';
    case IN_PROGRESS = 'in-progress';
    case RESTOCKED = 'restocked';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function isFinal(): bool
    {
        return in_array($this, [
            self::RESTOCKED,
            self::FAILED,
            self::CANCELLED,
        ]);
    }

    public function canTransition(self $newStatus)
    {
        return match ($this) {
            self::REQUESTED => in_array($newStatus, [
                self::APPROVED,
                self::IN_PROGRESS,
                self::RESTOCKED,
                self::FAILED,
                self::CANCELLED,
            ]),
            self::APPROVED => in_array($newStatus, [
                self::IN_PROGRESS,
                self::RESTOCKED,
            ]),
            self::IN_PROGRESS => in_array($newStatus, [
                self::RESTOCKED
            ]),
        }    ;
    }
}
