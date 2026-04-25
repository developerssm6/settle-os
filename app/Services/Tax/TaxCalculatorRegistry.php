<?php

namespace App\Services\Tax;

use App\Models\TaxRule;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

class TaxCalculatorRegistry
{
    /** @var array<string, class-string<TaxCalculator>> */
    private array $strategies;

    /**
     * @param  array<string, class-string<TaxCalculator>>|null  $strategies
     */
    public function __construct(
        private readonly Container $container,
        ?array $strategies = null,
    ) {
        $this->strategies = $strategies ?? config('mis.tax.strategies', []);
    }

    public function for(TaxRule $rule): TaxCalculator
    {
        foreach ($this->strategies as $key => $class) {
            $calculator = $this->container->make($class);
            if ($calculator->supports($rule)) {
                return $calculator;
            }
        }

        throw new RuntimeException(sprintf(
            'No tax calculator registered for rule %s (tax_type=%s, jurisdiction=%s).',
            $rule->code,
            $rule->tax_type,
            $rule->jurisdiction,
        ));
    }
}
