<?php

namespace App\Enums;

enum PolicyStatus: string
{
    case Active = 'active';
    case Upcoming = 'upcoming';
    case Expired = 'expired';
    case Lapsed = 'lapsed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Upcoming => 'Upcoming',
            self::Expired => 'Expired',
            self::Lapsed => 'Lapsed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Upcoming => 'info',
            self::Expired => 'secondary',
            self::Lapsed => 'warning',
            self::Cancelled => 'error',
        };
    }
}
