<?php

namespace App\Enums;

enum TaxIdType: string
{
    case PAN = 'pan';
    case GSTIN = 'gstin';
    case TAN = 'tan';
    case Aadhaar = 'aadhaar';

    public function label(): string
    {
        return match ($this) {
            self::PAN => 'PAN',
            self::GSTIN => 'GSTIN',
            self::TAN => 'TAN',
            self::Aadhaar => 'Aadhaar',
        };
    }

    public function pattern(): string
    {
        return match ($this) {
            self::PAN => '/^[A-Z]{5}[0-9]{4}[A-Z]$/',
            self::GSTIN => '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/',
            self::TAN => '/^[A-Z]{4}[0-9]{5}[A-Z]$/',
            self::Aadhaar => '/^[0-9]{12}$/',
        };
    }
}
