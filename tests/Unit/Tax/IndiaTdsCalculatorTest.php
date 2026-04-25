<?php

use App\Models\PartnerProfile;
use App\Models\TaxRule;
use App\Services\Tax\Calculators\IndiaTdsCalculator;
use App\Support\Money;

function makeTdsRule(array $overrides = []): TaxRule
{
    return new TaxRule(array_merge([
        'code' => 'TDS_194D_IND',
        'tax_type' => 'tds',
        'jurisdiction' => 'IN',
        'applies_to' => 'net_commission',
        'rate' => '0.05000',
        'annual_threshold' => '0',
        'conditions' => ['business_type' => ['individual', 'huf', 'proprietor']],
        'is_active' => true,
    ], $overrides));
}

function partner(string $businessType, string $state = 'KA', bool $gst = false): PartnerProfile
{
    return new PartnerProfile([
        'business_type' => $businessType,
        'state_code' => $state,
        'is_gst_registered' => $gst,
    ]);
}

it('supports only TDS rules in IN jurisdiction', function () {
    $calc = new IndiaTdsCalculator;

    expect($calc->supports(makeTdsRule()))->toBeTrue()
        ->and($calc->supports(makeTdsRule(['tax_type' => 'gst'])))->toBeFalse()
        ->and($calc->supports(makeTdsRule(['jurisdiction' => 'US'])))->toBeFalse();
});

it('produces a TDS line for an individual partner', function () {
    $lines = (new IndiaTdsCalculator)->calculate(
        makeTdsRule(),
        Money::of('1000.00'),
        partner('individual'),
        now(),
    );

    expect($lines)->toHaveCount(1)
        ->and($lines[0]->type)->toBe('tds')
        ->and($lines[0]->rate)->toBe('0.05000')
        ->and($lines[0]->amount->amount)->toBe('50.00');
});

it('skips when partner business type is outside the rule conditions', function () {
    $lines = (new IndiaTdsCalculator)->calculate(
        makeTdsRule(),  // individual/huf/proprietor only
        Money::of('1000.00'),
        partner('private_ltd'),
        now(),
    );

    expect($lines)->toBeEmpty();
});

it('skips when base amount is below the per-payout threshold', function () {
    $lines = (new IndiaTdsCalculator)->calculate(
        makeTdsRule(['annual_threshold' => '15000']),
        Money::of('500.00'),
        partner('individual'),
        now(),
    );

    expect($lines)->toBeEmpty();
});

it('applies when base meets or exceeds the threshold', function () {
    $lines = (new IndiaTdsCalculator)->calculate(
        makeTdsRule(['annual_threshold' => '15000']),
        Money::of('20000.00'),
        partner('individual'),
        now(),
    );

    expect($lines)->toHaveCount(1)
        ->and($lines[0]->amount->amount)->toBe('1000.00');
});

it('rounds half-up at two decimals', function () {
    $lines = (new IndiaTdsCalculator)->calculate(
        makeTdsRule(),
        Money::of('2052.56'),
        partner('individual'),
        now(),
    );

    expect($lines[0]->amount->amount)->toBe('102.63');
});
