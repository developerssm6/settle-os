<?php

namespace App\Actions\Invoicing;

use App\Models\InvoiceIssuer;
use App\Models\InvoiceSequence;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AllocateInvoiceNumber
{
    use AsAction;

    /**
     * Atomically reserve the next invoice number for $issuer in $fiscalYear.
     *
     * Must be called inside the same DB transaction that creates the Invoice
     * row, so a rollback releases the reservation and the next caller gets the
     * same value — yielding a gapless sequence under normal operation.
     */
    public function handle(InvoiceIssuer $issuer, int $fiscalYear, ?string $prefix = null): string
    {
        return DB::transaction(function () use ($issuer, $fiscalYear, $prefix) {
            $prefix ??= (string) config('mis.defaults.invoice.prefix.value', 'INV');

            $sequence = InvoiceSequence::query()
                ->where('issuer_id', $issuer->id)
                ->where('prefix', $prefix)
                ->where('fiscal_year', $fiscalYear)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                $sequence = InvoiceSequence::query()->create([
                    'issuer_id' => $issuer->id,
                    'prefix' => $prefix,
                    'fiscal_year' => $fiscalYear,
                    'next_value' => 1,
                ]);
            }

            $value = (int) $sequence->next_value;
            $sequence->increment('next_value');

            return sprintf(
                '%s/%04d-%02d/%06d',
                $prefix,
                $fiscalYear,
                ($fiscalYear + 1) % 100,
                $value,
            );
        });
    }
}
