<?php

namespace App\Actions\Payout;

use App\Actions\Commission\ResolveCommissionRate;
use App\Actions\Tax\ApplyTaxRules;
use App\Data\TaxLineData;
use App\Enums\PayoutStatus;
use App\Enums\TaxAppliesTo;
use App\Exceptions\CurrencyMismatch;
use App\Exceptions\NoApplicableRate;
use App\Exceptions\PayoutImmutable;
use App\Models\CommissionRate;
use App\Models\InvoiceIssuer;
use App\Models\Payout;
use App\Models\Policy;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculatePayout
{
    use AsAction;

    public function __construct(
        private readonly ResolveCommissionRate $resolveRate,
        private readonly ApplyTaxRules $applyTaxes,
    ) {}

    public function handle(Policy $policy): Payout
    {
        return DB::transaction(function () use ($policy) {
            // Lock the policy row to serialize concurrent calculations for the same policy.
            $policy = Policy::query()
                ->whereKey($policy->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = Payout::query()
                ->where('policy_id', $policy->id)
                ->original()
                ->lockForUpdate()
                ->first();

            if ($existing !== null && in_array($existing->status, [PayoutStatus::Processed, PayoutStatus::Voided], true)) {
                throw PayoutImmutable::for($existing);
            }

            $rate = $this->resolveRate->handle($policy)
                ?? throw NoApplicableRate::for($policy);

            if ($rate->currency_code !== $policy->currency_code) {
                throw CurrencyMismatch::between($rate, $policy);
            }

            $partner = $policy->partnerProfile;
            $components = $this->commissionComponents($policy, $rate);
            $totalCommission = $components['total'];

            // Issuer drives intra/inter-state GST classification. We use the
            // single active issuer here; a tenant supporting multiple issuers
            // would resolve which one based on partner segmentation.
            $issuer = InvoiceIssuer::query()->active()->first();

            $taxLines = $this->applyTaxes->handle(
                base: $totalCommission,
                partner: $partner,
                appliesTo: TaxAppliesTo::NetCommission,
                jurisdiction: 'IN',
                asOf: $policy->policy_date,
                issuer: $issuer,
            );

            $tdsAmount = $this->sumByType($taxLines, 'tds', $policy->currency_code);
            $netPo = $totalCommission->subtract($tdsAmount);

            $attributes = [
                'partner_profile_id' => $policy->partner_profile_id,
                'commission_rate_id' => $rate->id,
                'od_commission' => $components['od']->toDecimal(),
                'tp_commission' => $components['tp']->toDecimal(),
                'net_commission' => $components['net']->toDecimal(),
                'flat_amount' => $components['flat']->toDecimal(),
                'total_commission' => $totalCommission->toDecimal(),
                'tds_amount' => $tdsAmount->toDecimal(),
                'net_po' => $netPo->toDecimal(),
                'currency_code' => $policy->currency_code,
                'tax_lines' => array_map(fn (TaxLineData $l) => $l->toArray(), $taxLines),
                'breakdown' => [
                    'rate_id' => $rate->id,
                    'components' => [
                        'od' => $components['od']->toDecimal(),
                        'tp' => $components['tp']->toDecimal(),
                        'net' => $components['net']->toDecimal(),
                        'flat' => $components['flat']->toDecimal(),
                        'total' => $totalCommission->toDecimal(),
                    ],
                    'taxes' => array_map(fn (TaxLineData $l) => $l->toArray(), $taxLines),
                    'as_of' => $policy->policy_date->format('Y-m-d'),
                ],
                'status' => PayoutStatus::Calculated->value,
            ];

            return Payout::query()->updateOrCreate(
                [
                    'policy_id' => $policy->id,
                    'reversing_payout_id' => null,
                ],
                $attributes,
            );
        });
    }

    /**
     * @return array{od: Money, tp: Money, net: Money, flat: Money, total: Money}
     */
    private function commissionComponents(Policy $policy, CommissionRate $rate): array
    {
        $currency = $policy->currency_code;
        $premium = Money::of((string) $policy->premium, $currency);
        $flat = Money::of((string) $rate->flat_amount, $currency);

        $netRate = (string) $rate->net_percent;
        if ((float) $netRate > 0) {
            $net = $premium->percentage(bcdiv($netRate, '100', 6));

            return [
                'od' => Money::zero($currency),
                'tp' => Money::zero($currency),
                'net' => $net,
                'flat' => $flat,
                'total' => $net->add($flat),
            ];
        }

        $motor = $policy->motorDetails;
        if ($motor === null) {
            // No OD/TP split available; fall back to premium-times-od_percent.
            $net = $premium->percentage(bcdiv((string) $rate->od_percent, '100', 6));

            return [
                'od' => $net,
                'tp' => Money::zero($currency),
                'net' => $net,
                'flat' => $flat,
                'total' => $net->add($flat),
            ];
        }

        $od = Money::of((string) $motor->own_damage, $currency)
            ->percentage(bcdiv((string) $rate->od_percent, '100', 6));
        $tp = Money::of((string) $motor->third_party, $currency)
            ->percentage(bcdiv((string) $rate->tp_percent, '100', 6));
        $net = $od->add($tp);

        return [
            'od' => $od,
            'tp' => $tp,
            'net' => $net,
            'flat' => $flat,
            'total' => $net->add($flat),
        ];
    }

    /**
     * @param  array<int, TaxLineData>  $lines
     */
    private function sumByType(array $lines, string $type, string $currency): Money
    {
        $sum = Money::zero($currency);
        foreach ($lines as $line) {
            if ($line->type === $type) {
                $sum = $sum->add(Money::of($line->amount->amount, $line->amount->currency));
            }
        }

        return $sum;
    }
}
