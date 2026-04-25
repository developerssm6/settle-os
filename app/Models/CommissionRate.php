<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionRate extends Model
{
    protected $guarded = [];

    public $timestamps = true;

    /**
     * `effective_range` and `dims_key` are managed by Postgres directly
     * (daterange + STORED generated column). Don't try to mass-assign them.
     */
    protected $hidden = ['dims_key'];

    protected function casts(): array
    {
        return [
            'vehicle_attrs' => 'array',
            'od_percent' => 'decimal:3',
            'tp_percent' => 'decimal:3',
            'net_percent' => 'decimal:3',
            'flat_amount' => 'decimal:4',
        ];
    }

    public function insurer(): BelongsTo
    {
        return $this->belongsTo(Insurer::class);
    }

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(TaxonomyTerm::class, 'business_type_id');
    }

    public function partnerProfile(): BelongsTo
    {
        return $this->belongsTo(PartnerProfile::class, 'partner_id');
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(TaxonomyTerm::class, 'vehicle_type_id');
    }
}
