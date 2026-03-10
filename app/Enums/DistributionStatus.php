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
                self::APPROVED,
                self::REJECTED,
                self::PREPARING,
                self::SHIPPED,
                self::DELIVERED,
                self::COMPLETED,
                self::CANCELED,
            ]),
        }    ;
    }
}
