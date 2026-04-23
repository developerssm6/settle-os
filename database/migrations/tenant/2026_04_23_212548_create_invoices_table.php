<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 30)->unique(); // gapless, allocated from invoice_sequences
            $table->unsignedBigInteger('issuer_id');
            $table->unsignedBigInteger('partner_profile_id');
            $table->unsignedBigInteger('payout_id')->nullable();

            $table->date('period_from');
            $table->date('period_to');
            $table->date('due_date')->nullable();

            // Financials (all DECIMAL(14,4) — no floats)
            $table->decimal('subtotal', 14, 4);
            $table->decimal('tax_amount', 14, 4)->default(0);
            $table->decimal('total', 14, 4);
            $table->char('currency_code', 3)->default('INR');

            // Structured line items and tax breakdown
            $table->jsonb('line_items');              // [{description, qty, unit_price, amount}]
            $table->jsonb('tax_lines')->nullable();   // [{code, jurisdiction, basis, rate, amount}]

            // Immutable once issued
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->foreign('issuer_id')->references('id')->on('invoice_issuers')->restrictOnDelete();
            $table->foreign('partner_profile_id')->references('id')->on('partner_profiles')->restrictOnDelete();
            $table->foreign('payout_id')->references('id')->on('payouts')->nullOnDelete();

            $table->index(['partner_profile_id', 'issued_at']);
            $table->index('period_from');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
