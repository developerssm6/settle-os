<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Insurer extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function policies(): HasMany
    {
        return $this->hasMany(Policy::class);
    }

    public function commissionRates(): HasMany
    {
        return $this->hasMany(CommissionRate::class);
    }
}
