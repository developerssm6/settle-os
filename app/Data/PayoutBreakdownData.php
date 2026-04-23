<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class PayoutBreakdownData extends Data
{
    public function __construct(
        public readonly MoneyData $grossCommission,
        public readonly MoneyData $tdsDeducted,
        public readonly MoneyData $gstCharged,
        public readonly MoneyData $netPayable,
        public readonly int $policyCount,
        public readonly string $periodFrom,   // Y-m-d
        public readonly string $periodTo,     // Y-m-d
        /** @var TaxLineData[] */
        public readonly array $taxLines = [],
    ) {}
}
