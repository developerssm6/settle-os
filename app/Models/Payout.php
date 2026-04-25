<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'od_commission' => 'decimal:4',
            'tp_commission' => 'decimal:4',
            'net_commission' => 'decimal:4',
            'flat_amount' => 'decimal:4',
            'total_commission' => 'decimal:4',
            'tds_amount' => 'decimal:4',
            'net_po' => 'decimal:4',
            'tax_lines' => 'array',
            'breakdown' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class);
    }

    public function partnerProfile(): BelongsTo
    {
        return $this->belongsTo(PartnerProfile::class);
    }

    public function commissionRate(): BelongsTo
    {
        return $this->belongsTo(CommissionRate::class);
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversing_payout_id');
    }

    public function scopeOriginal(Builder $query): Builder
    {
        return $query->whereNull('reversing_payout_id');
    }

    public function scopeReversal(Builder $query): Builder
    {
        return $query->whereNotNull('reversing_payout_id');
    }

    public function scopeStatus(Builder $query, PayoutStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }
}
