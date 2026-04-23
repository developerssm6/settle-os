<?php

namespace App\Enums;

enum TaxAppliesTo: string
{
    case GrossCommission = 'gross_commission';
    case NetCommission = 'net_commission';
    case Premium = 'premium';

    public function label(): string
    {
        return match ($this) {
            self::GrossCommission => 'Gross Commission',
            self::NetCommission => 'Net Commission',
            self::Premium => 'Premium',
        };
    }
}
