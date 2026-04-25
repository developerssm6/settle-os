<?php

use App\Support\Money;

it('parses a decimal string and round-trips', function () {
    $m = Money::of('1234.56');

    expect($m->toDecimal())->toBe('1234.56')
        ->and($m->getCurrency())->toBe('INR');
});

it('adds amounts in the same currency', function () {
    $a = Money::of('1000.00');
    $b = Money::of('234.56');

    expect($a->add($b)->toDecimal())->toBe('1234.56');
});

it('subtracts amounts in the same currency', function () {
    expect(Money::of('500.00')->subtract(Money::of('200.50'))->toDecimal())
        ->toBe('299.50');
});

it('applies a percentage as a decimal multiplier', function () {
    // 5% of 2052.56 = 102.628 → rounded HALF_UP at 2 decimals = 102.63
    expect(Money::of('2052.56')->percentage('0.05')->toDecimal())->toBe('102.63');
});

it('reports zero and positive correctly', function () {
    expect(Money::zero()->isZero())->toBeTrue()
        ->and(Money::of('1.00')->isPositive())->toBeTrue()
        ->and(Money::of('1.00')->isZero())->toBeFalse();
});

it('formats INR with the rupee sign and thousands separator', function () {
    expect(Money::of('123456.78')->format())->toBe('₹123,456.78');
});

it('preserves currency through arithmetic', function () {
    $usd = Money::of('100.00', 'USD');

    expect($usd->add(Money::of('50.00', 'USD'))->getCurrency())->toBe('USD');
});
