<?php

namespace App\Exceptions;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use RuntimeException;

class InvalidPayoutTransition extends RuntimeException
{
    public static function from(Payout $payout, PayoutStatus $target): self
    {
        return new self(sprintf(
            'Payout #%d cannot transition from %s to %s.',
            $payout->id,
            $payout->status->value,
            $target->value,
        ));
    }
}
