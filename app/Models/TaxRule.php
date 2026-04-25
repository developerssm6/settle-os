<?php

namespace App\Models;

use App\Enums\TaxAppliesTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TaxRule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'applies_to' => TaxAppliesTo::class,
            'rate' => 'decimal:5',
            'annual_threshold' => 'decimal:4',
            'conditions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForJurisdiction(Builder $query, string $jurisdiction): Builder
    {
        return $query->where('jurisdiction', $jurisdiction);
    }

    public function scopeAppliesTo(Builder $query, TaxAppliesTo $applies): Builder
    {
        return $query->where('applies_to', $applies->value);
    }

    public function scopeOfTaxType(Builder $query, string $type): Builder
    {
        return $query->where('tax_type', $type);
    }

    /** Filter rules whose Postgres `effective_range` contains the given date. */
    public function scopeEffectiveOn(Builder $query, \DateTimeInterface $date): Builder
    {
        return $query->whereRaw('effective_range @> ?::date', [$date->format('Y-m-d')]);
    }
}
