<?php

namespace App\Services\Tax\Calculators;

use App\Data\MoneyData;
use App\Data\TaxLineData;
use App\Models\InvoiceIssuer;
use App\Models\PartnerProfile;
use App\Models\TaxRule;
use App\Services\Tax\TaxCalculator;
use App\Support\Money;
use Carbon\CarbonInterface;

/**
 * GST on insurance intermediary services (SAC 997161).
 *
 * Intra-state (issuer state == partner state) splits into CGST + SGST
 * (each at half the IGST rate). Inter-state uses IGST. The rule's
 * `conditions->intra_state` flag controls which side of the fork it serves.
 *
 * Tax type derived from rule code (CGST_/SGST_/IGST_); `type` on the resulting
 * TaxLine is used by reports and partner statements.
 */
class IndiaGstCalculator implements TaxCalculator
{
    public function __construct(
        private readonly ?InvoiceIssuer $issuer = null,
    ) {}

    public function supports(TaxRule $rule): bool
    {
        return $rule->tax_type === 'gst' && $rule->jurisdiction === 'IN';
    }

    public function calculate(
        TaxRule $rule,
        Money $base,
        PartnerProfile $partner,
        CarbonInterface $asOf,
    ): array {
        if (! $partner->is_gst_registered) {
            return [];
        }

        $intraStateRule = (bool) ($rule->conditions['intra_state'] ?? false);
        $isIntraState = $this->isIntraState($partner);

        if ($intraStateRule !== $isIntraState) {
            return [];
        }

        $amount = $base->percentage((string) $rule->rate);
        $type = $this->codeFromRule($rule);

        return [
            new TaxLineData(
                type: $type,
                rate: (string) $rule->rate,
                amount: MoneyData::fromMoney($amount),
                basis: $rule->applies_to->value,
            ),
        ];
    }

    private function isIntraState(PartnerProfile $partner): bool
    {
        if ($this->issuer === null) {
            return false;
        }

        if ($partner->state_code === null || $this->issuer->state_code === null) {
            return false;
        }

        return strtoupper($partner->state_code) === strtoupper($this->issuer->state_code);
    }

    private function codeFromRule(TaxRule $rule): string
    {
        return match (true) {
            str_starts_with($rule->code, 'CGST_') => 'cgst',
            str_starts_with($rule->code, 'SGST_') => 'sgst',
            str_starts_with($rule->code, 'IGST_') => 'igst',
            default => 'gst',
        };
    }
}
