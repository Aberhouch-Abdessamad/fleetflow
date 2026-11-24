<?php

namespace App\Enums;

/**
 * Delivery state machine. The backend is the only authority on transitions;
 * any transition not listed in allowedTransitions() is rejected.
 */
enum DeliveryStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Pending = 'pending';
    case Assigned = 'assigned';
    case Accepted = 'accepted';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case Arrived = 'arrived';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Returned = 'returned';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Pending, self::Cancelled],
            self::Pending => [self::Assigned, self::Cancelled],
            self::Assigned => [self::Accepted, self::Pending, self::Cancelled],
            self::Accepted => [self::PickedUp, self::Pending, self::Cancelled],
            self::PickedUp => [self::InTransit, self::Failed],
            self::InTransit => [self::Arrived, self::Failed],
            self::Arrived => [self::Delivered, self::Failed],
            self::Failed => [self::Returned, self::Pending],
            self::Delivered, self::Cancelled, self::Returned => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /** Statuses a driver may set on their own delivery (assignment accept/reject handled separately). */
    public static function driverSettable(): array
    {
        return [self::PickedUp, self::InTransit, self::Arrived, self::Delivered, self::Failed];
    }

    /** Delivery is "active" — holds driver/vehicle capacity. */
    public function isActive(): bool
    {
        return in_array($this, [self::Assigned, self::Accepted, self::PickedUp, self::InTransit, self::Arrived], true);
    }

    /** Vehicle is physically on the road. */
    public function isOnRoad(): bool
    {
        return in_array($this, [self::PickedUp, self::InTransit, self::Arrived], true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /** @return list<string> */
    public static function activeValues(): array
    {
        return array_map(fn ($s) => $s->value, array_filter(self::cases(), fn ($s) => $s->isActive()));
    }
}
