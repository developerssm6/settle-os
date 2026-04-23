<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class TaxLineData extends Data
{
    public function __construct(
        public readonly string $type,     // 'tds' | 'cgst' | 'sgst' | 'igst' | 'gst'
        public readonly string $rate,     // decimal fraction e.g. "0.05"
        public readonly MoneyData $amount,
        public readonly string $basis,    // App\Enums\TaxAppliesTo value
    ) {}
}
