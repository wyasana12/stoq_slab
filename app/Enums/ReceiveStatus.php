<?php

namespace App\Enums;

enum ReceiveStatus: string
{
    case PROCESS = 'process';
    case PARTIAL = 'partially-received';
    case FULL = 'received-in-fully';
    case REJECT = 'rejected';

    public function isFinal(): bool
    {
        return in_array($this, [
            self::FULL,
            self::REJECT,
        ]);
    }

    public function canTransition(self $newStatus)
    {
        return match ($this) {
            self::PROCESS => in_array($newStatus, [
                self::PARTIAL,
                self::FULL,
                self::REJECT,
            ]),
            self::PARTIAL => in_array($newStatus, [
                self::FULL,
            ]),
            default => false
        };
    }
}
