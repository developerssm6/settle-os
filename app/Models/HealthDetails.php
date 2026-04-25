<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthDetails extends Model
{
    protected $table = 'health_details';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sum_insured' => 'decimal:4',
            'members' => 'array',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class);
    }

    public function policyType(): BelongsTo
    {
        return $this->belongsTo(TaxonomyTerm::class, 'policy_type_id');
    }

    public function coverageType(): BelongsTo
    {
        return $this->belongsTo(TaxonomyTerm::class, 'coverage_type_id');
    }
}
