<?php

namespace App\Enums;

enum RestockStatus: string
{
    case REQUESTED = 'requested';
    case APPROVED = 'approved';
    case ON_DELIVERY = 'on_delivery';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';

    public static function fromValue(string $value): self
    {
        $normalized = strtolower($value);

        return match ($normalized) {
            'requested', 'pending' => self::REQUESTED,
            'approved' => self::APPROVED,
            'on_delivery', 'on-delivery', 'in-progress', 'in_progress' => self::ON_DELIVERY,
            'completed', 'restocked', 'success' => self::COMPLETED,
            'rejected', 'ditolak' => self::REJECTED,
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
            self::COMPLETED,
            self::REJECTED,
        ], true);
    }

    public function canTransition(self $newStatus): bool
    {
        return match ($this) {
            self::REQUESTED => in_array($newStatus, [
                self::APPROVED,
                self::REJECTED,
            ], true),
            self::APPROVED => in_array($newStatus, [
                self::ON_DELIVERY,
                self::COMPLETED,
                self::REJECTED,
            ], true),
            self::ON_DELIVERY => in_array($newStatus, [
                self::COMPLETED,
            ], true),
            default => false,
        };
    }
}
