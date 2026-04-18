<?php

namespace App\Enums;

enum DistributionStatus: string
{
    case DRAFT = 'draft';
    case WAITING_APPROVAL = 'waiting-approval';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case PREPARING = 'preparing';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case COMPLETED = 'completed';
    case CANCELED = 'canceled';

    public function isFinal(): bool
    {
        return in_array($this, [
            self::REJECTED,
            self::COMPLETED,
            self::CANCELED,
        ]);
    }
    
    public function canTransition(self $newStatus)
    {
        return match ($this) {
            self::DRAFT => in_array($newStatus, [
                self::WAITING_APPROVAL,
                self::CANCELED,
            ], true),
            self::WAITING_APPROVAL => in_array($newStatus, [
                self::APPROVED,
                self::REJECTED,
                self::CANCELED,
            ], true),
            self::APPROVED => in_array($newStatus, [
                self::PREPARING,
                self::CANCELED,
            ], true),
            self::REJECTED => false,
            self::PREPARING => in_array($newStatus, [
                self::SHIPPED,
                self::CANCELED,
            ], true),
            self::SHIPPED => in_array($newStatus, [
                self::DELIVERED,
                self::CANCELED,
            ], true),
            self::DELIVERED => in_array($newStatus, [
                self::COMPLETED,
            ], true),
            self::COMPLETED => false,
            self::CANCELED => false,
        };
    }
}
