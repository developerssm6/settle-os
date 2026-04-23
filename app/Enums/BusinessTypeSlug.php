<?php

namespace App\Enums;

enum BusinessTypeSlug: string
{
    case Individual = 'individual';
    case Proprietor = 'proprietor';
    case Partnership = 'partnership';
    case LLP = 'llp';
    case PrivateLtd = 'private_ltd';
    case PublicLtd = 'public_ltd';
    case HUF = 'huf';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Individual',
            self::Proprietor => 'Sole Proprietor',
            self::Partnership => 'Partnership',
            self::LLP => 'LLP',
            self::PrivateLtd => 'Private Limited',
            self::PublicLtd => 'Public Limited',
            self::HUF => 'HUF',
        };
    }

    public function isCompanyForTds(): bool
    {
        return in_array($this, [
            self::PrivateLtd,
            self::PublicLtd,
            self::LLP,
            self::Partnership,
        ]);
    }
}
