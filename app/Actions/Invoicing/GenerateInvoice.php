<?php

namespace App\Actions\Invoicing;

use App\Actions\Tax\ApplyTaxRules;
use App\Data\TaxLineData;
use App\Enums\TaxAppliesTo;
use App\Models\Invoice;
use App\Models\InvoiceIssuer;
use App\Models\Payout;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateInvoice
{
    use AsAction;

    public function __construct(
        private readonly AllocateInvoiceNumber $allocator,
        private readonly ApplyTaxRules $applyTaxes,
    ) {}

    public function handle(Payout $payout, ?InvoiceIssuer $issuer = null): Invoice
    {
        return DB::transaction(function () use ($payout, $issuer) {
            $issuer ??= InvoiceIssuer::query()->active()->firstOrFail();

            $partner = $payout->partnerProfile;
            $issuedAt = now();
            $fiscalYear = $issuedAt->month >= ((int) config('mis.defaults.invoice.fy_start_month.value', 4))
                ? $issuedAt->year
                : $issuedAt->year - 1;

            $subtotal = Money::of((string) $payout->total_commission, $payout->currency_code);

            $taxLines = $this->applyTaxes->handle(
                base: $subtotal,
                partner: $partner,
                appliesTo: TaxAppliesTo::NetCommission,
                jurisdiction: 'IN',
                asOf: $issuedAt,
                issuer: $issuer,
            );

            $gstLines = array_values(array_filter(
                $taxLines,
                fn (TaxLineData $l) => in_array($l->type, ['cgst', 'sgst', 'igst', 'gst'], true),
            ));

            $taxTotal = Money::zero($payout->currency_code);
            foreach ($gstLines as $line) {
                $taxTotal = $taxTotal->add(Money::of($line->amount->amount, $line->amount->currency));
            }

            $total = $subtotal->add($taxTotal);

            $invoiceNumber = $this->allocator->handle($issuer, $fiscalYear);

            $lineItems = [
                [
                    'description' => sprintf('Insurance commission — policy %s', $payout->policy?->policy_number ?? '#'.$payout->policy_id),
                    'qty' => 1,
                    'unit_price' => $subtotal->toDecimal(),
                    'amount' => $subtotal->toDecimal(),
                ],
            ];

            return Invoice::query()->create([
                'invoice_number' => $invoiceNumber,
                'issuer_id' => $issuer->id,
                'partner_profile_id' => $partner->id,
                'payout_id' => $payout->id,
                'period_from' => $payout->processed_at?->toDateString() ?? $issuedAt->toDateString(),
                'period_to' => $issuedAt->toDateString(),
                'due_date' => $issuedAt->copy()->addDays(15)->toDateString(),
                'subtotal' => $subtotal->toDecimal(),
                'tax_amount' => $taxTotal->toDecimal(),
                'total' => $total->toDecimal(),
                'currency_code' => $payout->currency_code,
                'line_items' => $lineItems,
                'tax_lines' => array_map(fn (TaxLineData $l) => $l->toArray(), $gstLines),
                'issued_at' => $issuedAt,
            ]);
        });
    }
}
