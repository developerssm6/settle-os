<?php

use App\Models\InvoiceIssuer;
use App\Models\PartnerProfile;
use App\Models\TaxRule;
use App\Services\Tax\Calculators\IndiaGstCalculator;
use App\Support\Money;

function makeGstRule(string $code, float $rate, bool $intraState): TaxRule
{
    return new TaxRule([
        'code' => $code,
        'tax_type' => 'gst',
        'jurisdiction' => 'IN',
        'applies_to' => 'net_commission',
        'rate' => $rate,
        'annual_threshold' => '0',
        'conditions' => ['intra_state' => $intraState],
        'is_active' => true,
    ]);
}

function gstPartner(string $state, bool $registered): PartnerProfile
{
    return new PartnerProfile([
        'business_type' => 'individual',
        'state_code' => $state,
        'is_gst_registered' => $registered,
    ]);
}

function issuerIn(string $state): InvoiceIssuer
{
    return new InvoiceIssuer([
        'name' => 'Test Issuer',
        'state_code' => $state,
        'is_active' => true,
    ]);
}

it('supports only GST rules in IN jurisdiction', function () {
    $calc = new IndiaGstCalculator;

    expect($calc->supports(makeGstRule('CGST_997161', 0.09, true)))->toBeTrue()
        ->and($calc->supports(makeGstRule('TDS_194D_IND', 0.05, true)
            ->forceFill(['tax_type' => 'tds'])))->toBeFalse();
});

it('skips partners that are not GST registered', function () {
    $lines = (new IndiaGstCalculator(issuerIn('KA')))->calculate(
        makeGstRule('CGST_997161', 0.09, true),
        Money::of('1000.00'),
        gstPartner('KA', registered: false),
        now(),
    );

    expect($lines)->toBeEmpty();
});

it('emits CGST and SGST for intra-state partner', function () {
    $calc = new IndiaGstCalculator(issuerIn('KA'));
    $partner = gstPartner('KA', registered: true);
    $base = Money::of('1000.00');

    $cgst = $calc->calculate(makeGstRule('CGST_997161', 0.09, true), $base, $partner, now());
    $sgst = $calc->calculate(makeGstRule('SGST_997161', 0.09, true), $base, $partner, now());
    $igst = $calc->calculate(makeGstRule('IGST_997161', 0.18, false), $base, $partner, now());

    expect($cgst[0]->type)->toBe('cgst')
        ->and($cgst[0]->amount->amount)->toBe('90.00')
        ->and($sgst[0]->type)->toBe('sgst')
        ->and($sgst[0]->amount->amount)->toBe('90.00')
        ->and($igst)->toBeEmpty();   // inter-state rule shouldn't fire intra-state
});

it('emits IGST for inter-state partner', function () {
    $calc = new IndiaGstCalculator(issuerIn('KA'));
    $partner = gstPartner('MH', registered: true);
    $base = Money::of('1000.00');

    $cgst = $calc->calculate(makeGstRule('CGST_997161', 0.09, true), $base, $partner, now());
    $igst = $calc->calculate(makeGstRule('IGST_997161', 0.18, false), $base, $partner, now());

    expect($cgst)->toBeEmpty()
        ->and($igst[0]->type)->toBe('igst')
        ->and($igst[0]->amount->amount)->toBe('180.00');
});

it('treats missing issuer as inter-state', function () {
    $calc = new IndiaGstCalculator;   // no issuer
    $partner = gstPartner('KA', registered: true);

    $cgst = $calc->calculate(makeGstRule('CGST_997161', 0.09, true), Money::of('1000.00'), $partner, now());
    $igst = $calc->calculate(makeGstRule('IGST_997161', 0.18, false), Money::of('1000.00'), $partner, now());

    expect($cgst)->toBeEmpty()
        ->and($igst[0]->type)->toBe('igst');
});
