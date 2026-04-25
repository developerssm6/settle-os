<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceSequence extends Model
{
    protected $guarded = [];

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(InvoiceIssuer::class, 'issuer_id');
    }
}
