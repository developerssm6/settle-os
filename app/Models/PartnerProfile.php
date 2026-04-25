<?php

namespace App\Models;

use App\Enums\BusinessTypeSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PartnerProfile extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'business_type' => BusinessTypeSlug::class,
            'is_gst_registered' => 'boolean',
            'is_active' => 'boolean',
            'onboarded_on' => 'date',
            'pan' => 'encrypted',
            'gstin' => 'encrypted',
            'tan' => 'encrypted',
            'bank_account_number' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function policies(): HasMany
    {
        return $this->hasMany(Policy::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }
}
