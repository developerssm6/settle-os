<?php

namespace App\Actions\Commission;

use App\Models\CommissionRate;
use App\Models\Policy;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveCommissionRate
{
    use AsAction;

    /**
     * Resolve the commission rate for $policy on $asOf.
     *
     * Partner override wins over global rate; the Postgres EXCLUDE constraint
     * guarantees at most one matching row per (insurer, business_type, partner,
     * dims_key, effective_range) so no `latest()` tiebreaker is needed.
     *
     * Vehicle-attribute matching (coverage/age/fuel/etc.) is handled via the
     * `dims_key` generated column when those rates are seeded with `vehicle_attrs`.
     * The seed data uses `vehicle_attrs = NULL`, which produces dims_key '<vid>:{}'.
     */
    public function handle(Policy $policy, ?CarbonInterface $asOf = null): ?CommissionRate
    {
        $asOf ??= $policy->policy_date;

        $base = fn (): Builder => CommissionRate::query()
            ->where('insurer_id', $policy->insurer_id)
            ->where('business_type_id', $policy->business_type_id)
            ->whereRaw('effective_range @> ?::date', [$asOf->format('Y-m-d')])
            ->where(function (Builder $q) use ($policy) {
                $vehicleTypeId = $policy->motorDetails?->vehicle_type_id;
                if ($vehicleTypeId !== null) {
                    $q->where('vehicle_type_id', $vehicleTypeId);
                } else {
                    $q->whereNull('vehicle_type_id');
                }
            })
            ->whereNull('vehicle_attrs');

        return $base()->where('partner_id', $policy->partner_profile_id)->first()
            ?? $base()->whereNull('partner_id')->first();
    }
}
