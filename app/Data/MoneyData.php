<?php

namespace App\Data;

use App\Support\Money;
use Spatie\LaravelData\Data;

class MoneyData extends Data
{
    public function __construct(
        public readonly string $amount,
        public readonly string $currency = 'INR',
    ) {}

    public static function fromMoney(Money $money): self
    {
        return new self(
            amount: $money->toDecimal(),
            currency: $money->getCurrency(),
        );
    }

    public function toMoney(): Money
    {
        return Money::of($this->amount, $this->currency);
    }
}
