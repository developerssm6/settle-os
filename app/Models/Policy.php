<?php

namespace App\Models;

use App\Enums\PolicyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Policy extends Model
{
    use SoftDeletes;

    protected $table = 'policies';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => PolicyStatus::class,
            'premium' => 'decimal:4',
            'sum_insured' => 'decimal:4',
            'policy_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function partnerProfile(): BelongsTo
    {
        return $this->belongsTo(PartnerProfile::class);
    }

    public function insurer(): BelongsTo
    {
        return $this->belongsTo(Insurer::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(TaxonomyTerm::class, 'business_type_id');
    }

    public function motorDetails(): HasOne
    {
        return $this->hasOne(MotorDetails::class);
    }

    public function nonMotorDetails(): HasOne
    {
        return $this->hasOne(NonMotorDetails::class);
    }

    public function healthDetails(): HasOne
    {
        return $this->hasOne(HealthDetails::class);
    }

    public function payout(): HasOne
    {
        return $this->hasOne(Payout::class)->whereNull('reversing_payout_id');
    }
}
