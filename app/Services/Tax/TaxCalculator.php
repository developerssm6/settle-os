<?php

namespace App\Services\Tax;

use App\Data\TaxLineData;
use App\Models\PartnerProfile;
use App\Models\TaxRule;
use App\Support\Money;
use Carbon\CarbonInterface;

interface TaxCalculator
{
    /**
     * Whether this calculator handles the given rule.
     * Used by the registry to dispatch.
     */
    public function supports(TaxRule $rule): bool;

    /**
     * Apply $rule to the $base money amount for the given $partner on $asOf,
     * returning zero or more tax lines. Returning an empty array means the
     * rule did not produce a deduction (e.g. threshold not crossed).
     *
     * @return array<int, TaxLineData>
     */
    public function calculate(
        TaxRule $rule,
        Money $base,
        PartnerProfile $partner,
        CarbonInterface $asOf,
    ): array;
}
