<?php

namespace App\Services\Tax\Calculators;

use App\Data\MoneyData;
use App\Data\TaxLineData;
use App\Models\PartnerProfile;
use App\Models\TaxRule;
use App\Services\Tax\TaxCalculator;
use App\Support\Money;
use Carbon\CarbonInterface;

/**
 * Section 194D — TDS on insurance commission.
 *
 * Rate is rule-driven (`rate` column on TaxRule); the rule's `conditions->business_type`
 * array constrains which partner business-types this rate applies to. The
 * `annual_threshold` is treated as a per-payout floor for now (a simple safeguard);
 * a proper YTD-aggregated threshold lives in M5 reporting.
 */
class IndiaTdsCalculator implements TaxCalculator
{
    public function supports(TaxRule $rule): bool
    {
        return $rule->tax_type === 'tds' && $rule->jurisdiction === 'IN';
    }

    public function calculate(
        TaxRule $rule,
        Money $base,
        PartnerProfile $partner,
        CarbonInterface $asOf,
    ): array {
        $allowed = (array) ($rule->conditions['business_type'] ?? []);
        if ($allowed !== [] && ! in_array($partner->business_type->value, $allowed, true)) {
            return [];
        }

        $threshold = (float) ($rule->annual_threshold ?? 0);
        if ($threshold > 0 && (float) $base->toDecimal() < $threshold) {
            return [];
        }

        $amount = $base->percentage((string) $rule->rate);

        return [
            new TaxLineData(
                type: 'tds',
                rate: (string) $rule->rate,
                amount: MoneyData::fromMoney($amount),
                basis: $rule->applies_to->value,
            ),
        ];
    }
}
