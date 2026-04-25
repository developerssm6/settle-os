<?php

namespace App\Exceptions;

use App\Models\Payout;
use RuntimeException;

class PayoutImmutable extends RuntimeException
{
    public static function for(Payout $payout): self
    {
        return new self(sprintf(
            'Payout #%d is %s and cannot be recalculated. Use VoidPayout + a new CalculatePayout instead.',
            $payout->id,
            $payout->status->value,
        ));
    }
}
