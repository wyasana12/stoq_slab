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

    public static function fromValue(string $value): self
    {
        $normalized = strtolower($value);

        return match ($normalized) {
            'requested', 'pending' => self::REQUESTED,
            'approved' => self::APPROVED,
            'in-progress', 'in_progress' => self::IN_PROGRESS,
            'restocked', 'success' => self::RESTOCKED,
            'failed' => self::FAILED,
            'cancelled', 'canceled' => self::CANCELLED,
            default => throw new \ValueError("Invalid RestockStatus value: {$value}"),
        };
    }

    public static function values(): array
    {
        return array_map(fn(self $status) => $status->value, self::cases());
    }

    public function isFinal(): bool
    {
        return in_array($this, [
            self::RESTOCKED,
            self::FAILED,
            self::CANCELLED,
        ], true);
    }

    public function canTransition(self $newStatus): bool
    {
        return match ($this) {
            self::REQUESTED => in_array($newStatus, [
                self::APPROVED,
                self::IN_PROGRESS,
                self::RESTOCKED,
                self::FAILED,
                self::CANCELLED,
            ], true),
            self::APPROVED => in_array($newStatus, [
                self::IN_PROGRESS,
                self::RESTOCKED,
            ], true),
            self::IN_PROGRESS => in_array($newStatus, [
                self::RESTOCKED,
            ], true),
            default => false,
        };
    }
}
