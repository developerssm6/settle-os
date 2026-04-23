<?php

namespace App\Enums;

enum PayoutStatus: string
{
    case Pending = 'pending';
    case Calculated = 'calculated';
    case Processed = 'processed';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Calculated => 'Calculated',
            self::Processed => 'Processed',
            self::Voided => 'Voided',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Calculated => 'info',
            self::Processed => 'success',
            self::Voided => 'error',
        };
    }

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Calculated],
            self::Calculated => [self::Processed, self::Voided],
            self::Processed => [],
            self::Voided => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), strict: true);
    }
}
