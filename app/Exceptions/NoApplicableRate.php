<?php

namespace App\Exceptions;

use App\Models\Policy;
use RuntimeException;

class NoApplicableRate extends RuntimeException
{
    public static function for(Policy $policy): self
    {
        return new self(sprintf(
            'No applicable commission rate for policy %s (#%d) on %s.',
            $policy->policy_number,
            $policy->id,
            $policy->policy_date?->format('Y-m-d') ?? 'n/a',
        ));
    }
}
