<?php

namespace App\Actions\Payout;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessPayouts
{
    use AsAction;

    /**
     * Mark each calculated payout as `processed`. Once processed, the DB trigger
     * blocks further updates — only a reversing payout can compensate.
     *
     * @param  Collection<int, Payout>  $payouts
     * @return Collection<int, Payout>
     */
    public function handle(Collection $payouts): Collection
    {
        return DB::transaction(function () use ($payouts) {
            $now = now();

            foreach ($payouts as $payout) {
                if ($payout->status !== PayoutStatus::Calculated) {
                    continue;
                }

                $payout->update([
                    'status' => PayoutStatus::Processed->value,
                    'processed_at' => $now,
                ]);
            }

            return $payouts->fresh();
        });
    }
}
