<?php

namespace App\Actions\Tax;

use App\Data\TaxLineData;
use App\Enums\TaxAppliesTo;
use App\Models\InvoiceIssuer;
use App\Models\PartnerProfile;
use App\Models\TaxRule;
use App\Services\Tax\Calculators\IndiaGstCalculator;
use App\Services\Tax\TaxCalculatorRegistry;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Container\Container;
use Lorisleiva\Actions\Concerns\AsAction;

class ApplyTaxRules
{
    use AsAction;

    public function __construct(
        private readonly TaxCalculatorRegistry $registry,
        private readonly Container $container,
    ) {}

    /**
     * Apply every active tax rule that matches (jurisdiction, applies_to, effective on $asOf)
     * to $base for $partner. The IndiaGstCalculator needs the issuer for intra/inter-state
     * determination, so it gets resolved per-call with the issuer bound.
     *
     * @return array<int, TaxLineData>
     */
    public function handle(
        Money $base,
        PartnerProfile $partner,
        TaxAppliesTo $appliesTo,
        string $jurisdiction,
        CarbonInterface $asOf,
        ?InvoiceIssuer $issuer = null,
    ): array {
        $rules = TaxRule::query()
            ->active()
            ->forJurisdiction($jurisdiction)
            ->appliesTo($appliesTo)
            ->effectiveOn($asOf)
            ->get();

        if ($issuer !== null) {
            $this->container->instance(IndiaGstCalculator::class, new IndiaGstCalculator($issuer));
        }

        $lines = [];
        foreach ($rules as $rule) {
            $calculator = $this->registry->for($rule);
            foreach ($calculator->calculate($rule, $base, $partner, $asOf) as $line) {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}
