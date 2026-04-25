<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MotorDetails extends Model
{
    protected $table = 'motor_details';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'own_damage' => 'decimal:4',
            'third_party' => 'decimal:4',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class);
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(TaxonomyTerm::class, 'vehicle_type_id');
    }

    public function coverageType(): BelongsTo
    {
        return $this->belongsTo(TaxonomyTerm::class, 'coverage_type_id');
    }

    public function vehicleAge(): BelongsTo
    {
        return $this->belongsTo(TaxonomyTerm::class, 'vehicle_age_id');
    }

    public function fuelType(): BelongsTo
    {
        return $this->belongsTo(TaxonomyTerm::class, 'fuel_type_id');
    }
}
