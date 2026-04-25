<?php

namespace App\Actions\Payout;

use App\Enums\PayoutStatus;
use App\Exceptions\InvalidPayoutTransition;
use App\Models\Payout;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class VoidPayout
{
    use AsAction;

    /**
     * Void $payout and return the *reversing* payout (or the voided original
     * itself when the payout was still in `calculated` status and never hit
     * the ledger).
     *
     * - Calculated: flip the original to `voided` in place. Nothing on the
     *   ledger to reverse, so no second row is needed.
     * - Processed: the DB trigger blocks any UPDATE on the original, so we
     *   INSERT a reversing payout with negated amounts and `status=processed`.
     *   Sum of the original + reversal = 0 in the ledger.
     */
    public function handle(Payout $payout, string $reason): Payout
    {
        return DB::transaction(function () use ($payout, $reason) {
            $payout = Payout::query()
                ->whereKey($payout->id)
                ->lockForUpdate()
                ->firstOrFail();

            return match ($payout->status) {
                PayoutStatus::Calculated => $this->voidCalculated($payout, $reason),
                PayoutStatus::Processed => $this->reverseProcessed($payout, $reason),
                default => throw InvalidPayoutTransition::from($payout, PayoutStatus::Voided),
            };
        });
    }

    private function voidCalculated(Payout $payout, string $reason): Payout
    {
        $payout->update([
            'status' => PayoutStatus::Voided->value,
            'breakdown' => [
                ...($payout->breakdown ?? []),
                'voided_at' => now()->toIso8601String(),
                'void_reason' => $reason,
            ],
        ]);

        return $payout;
    }

    private function reverseProcessed(Payout $payout, string $reason): Payout
    {
        $reversedTaxLines = array_map(
            function (array $line): array {
                $amount = $line['amount'] ?? null;
                if (\is_array($amount) && isset($amount['amount'])) {
                    $line['amount']['amount'] = bcmul($amount['amount'], '-1', 4);
                }

                return $line;
            },
            $payout->tax_lines ?? [],
        );

        return Payout::query()->create([
            'policy_id' => $payout->policy_id,
            'partner_profile_id' => $payout->partner_profile_id,
            'commission_rate_id' => $payout->commission_rate_id,
            'reversing_payout_id' => $payout->id,
            'od_commission' => bcmul((string) $payout->od_commission, '-1', 4),
            'tp_commission' => bcmul((string) $payout->tp_commission, '-1', 4),
            'net_commission' => bcmul((string) $payout->net_commission, '-1', 4),
            'flat_amount' => bcmul((string) $payout->flat_amount, '-1', 4),
            'total_commission' => bcmul((string) $payout->total_commission, '-1', 4),
            'tds_amount' => bcmul((string) $payout->tds_amount, '-1', 4),
            'net_po' => bcmul((string) $payout->net_po, '-1', 4),
            'currency_code' => $payout->currency_code,
            'tax_lines' => $reversedTaxLines,
            'breakdown' => [
                'reversal_of' => $payout->id,
                'reason' => $reason,
                'reversed_at' => now()->toIso8601String(),
            ],
            'status' => PayoutStatus::Processed->value,
            'processed_at' => now(),
        ]);
    }
}
