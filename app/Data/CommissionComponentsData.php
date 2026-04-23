<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class CommissionComponentsData extends Data
{
    public function __construct(
        public readonly MoneyData $gross,
        public readonly MoneyData $tds,
        public readonly MoneyData $gst,
        public readonly MoneyData $net,
        /** @var TaxLineData[] */
        public readonly array $taxLines = [],
    ) {}
}
