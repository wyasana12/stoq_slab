<?php

namespace App\Enums;

enum TransferStatus: string
{
    case DRAFT = 'draft';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case ON_DELIVERY = 'on_delivery';
    case RECEIVED = 'received';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public static function fromValue(string $value): self
    {
        $normalized = strtolower(str_replace([' ', '_'], '-', $value));

        return match ($normalized) {
            'draft' => self::DRAFT,
            'approved' => self::APPROVED,
            'rejected' => self::REJECTED,
            'on-delivery', 'on_delivery' => self::ON_DELIVERY,
            'received' => self::RECEIVED,
            'completed' => self::COMPLETED,
            'cancelled', 'canceled' => self::CANCELLED,
            default => throw new \ValueError("Invalid TransferStatus value: {$value}"),
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::REJECTED,
            self::CANCELLED,
        ], true);
    }

    public function canTransition(self $newStatus): bool
    {
        return match ($this) {
            self::DRAFT => in_array($newStatus, [
                self::APPROVED,
                self::REJECTED,
                self::COMPLETED,
                self::CANCELLED,
            ], true),
            self::APPROVED => in_array($newStatus, [
                self::ON_DELIVERY,
                self::RECEIVED,
                self::COMPLETED,
                self::CANCELLED,
            ], true),
            self::ON_DELIVERY => in_array($newStatus, [
                self::RECEIVED,
                self::COMPLETED,
                self::CANCELLED,
            ], true),
            default => false,
        };
    }
}
