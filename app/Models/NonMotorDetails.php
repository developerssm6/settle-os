<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NonMotorDetails extends Model
{
    protected $table = 'non_motor_details';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sum_insured' => 'decimal:4',
            'meta' => 'array',
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
}
