<?php

namespace App\Enums;

enum TransferStatus: string
{
    case REQUESTED = 'requested';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case ON_DELIVERY = 'on_delivery';
    case RECEIVED = 'received';
    case COMPLETED = 'completed';


    public static function fromValue(string $value): self
    {
        $normalized = strtolower(str_replace([' ', '_'], '-', $value));

        return match ($normalized) {
            'requested' => self::REQUESTED,
            'approved' => self::APPROVED,
            'rejected' => self::REJECTED,
            'on-delivery', 'on_delivery' => self::ON_DELIVERY,
            'received' => self::RECEIVED,
            'completed' => self::COMPLETED,

            default => throw new \ValueError("Invalid TransferStatus value: {$value}"),
        };
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
                self::RECEIVED,
                self::COMPLETED,
            ], true),
            self::ON_DELIVERY => in_array($newStatus, [
                self::RECEIVED,
                self::COMPLETED,
            ], true),
            self::COMPLETED => in_array($newStatus, [
                self::RECEIVED,
            ], true),
            default => false,
        };
    }
}
