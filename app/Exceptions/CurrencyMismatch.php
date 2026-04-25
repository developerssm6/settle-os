<?php

namespace App\Exceptions;

use App\Models\CommissionRate;
use App\Models\Policy;
use RuntimeException;

class CurrencyMismatch extends RuntimeException
{
    public static function between(CommissionRate $rate, Policy $policy): self
    {
        return new self(sprintf(
            'Currency mismatch: policy #%d is in %s but commission rate #%d is in %s. The ledger never converts silently.',
            $policy->id,
            $policy->currency_code,
            $rate->id,
            $rate->currency_code,
        ));
    }
}
