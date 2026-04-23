<?php

namespace App\Support;

use Brick\Math\RoundingMode;
use Brick\Money\Money as BrickMoney;

/**
 * Thin wrapper around brick/money to enforce INR with 2 decimal places
 * and provide helpers used throughout commission/payout calculations.
 */
final class Money
{
    private BrickMoney $money;

    private function __construct(BrickMoney $money)
    {
        $this->money = $money;
    }

    /** Create from a decimal string or integer paise (smallest unit). */
    public static function of(string|int|float $amount, string $currency = 'INR'): self
    {
        if (is_int($amount)) {
            $brick = BrickMoney::ofMinor($amount, $currency);
        } else {
            $brick = BrickMoney::of((string) $amount, $currency, null, RoundingMode::HALF_UP);
        }

        return new self($brick);
    }

    public static function zero(string $currency = 'INR'): self
    {
        return self::of('0.00', $currency);
    }

    public function add(Money $other): self
    {
        return new self($this->money->plus($other->money, RoundingMode::HALF_UP));
    }

    public function subtract(Money $other): self
    {
        return new self($this->money->minus($other->money, RoundingMode::HALF_UP));
    }

    public function multiply(string|float $factor): self
    {
        return new self($this->money->multipliedBy((string) $factor, RoundingMode::HALF_UP));
    }

    public function percentage(string|float $rate): self
    {
        return $this->multiply($rate);
    }

    public function isZero(): bool
    {
        return $this->money->isZero();
    }

    public function isPositive(): bool
    {
        return $this->money->isPositive();
    }

    public function isGreaterThan(Money $other): bool
    {
        return $this->money->isGreaterThan($other->money);
    }

    public function isGreaterThanOrEqualTo(Money $other): bool
    {
        return $this->money->isGreaterThanOrEqualTo($other->money);
    }

    /** Return the amount as a decimal string (e.g. "1234.56"). */
    public function toDecimal(): string
    {
        return (string) $this->money->getAmount();
    }

    /** Return the amount in paise (smallest unit, integer). */
    public function toMinorUnits(): int
    {
        return (int) $this->money->getMinorAmount()->toInt();
    }

    public function getCurrency(): string
    {
        return $this->money->getCurrency()->getCurrencyCode();
    }

    public function format(): string
    {
        return '₹'.number_format((float) $this->toDecimal(), 2, '.', ',');
    }

    public function __toString(): string
    {
        return $this->toDecimal();
    }
}
