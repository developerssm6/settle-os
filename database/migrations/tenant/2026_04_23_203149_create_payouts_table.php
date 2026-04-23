<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('policy_id');
            $table->unsignedBigInteger('partner_profile_id');
            $table->unsignedBigInteger('commission_rate_id')->nullable(); // rate snapshot FK; nullable if rate was deleted

            // Commission components (DECIMAL(14,4) — no floats; computed via brick/money)
            $table->decimal('od_commission', 14, 4)->default(0);
            $table->decimal('tp_commission', 14, 4)->default(0);
            $table->decimal('net_commission', 14, 4)->default(0);
            $table->decimal('flat_amount', 14, 4)->default(0);
            $table->decimal('total_commission', 14, 4)->default(0);  // = net_commission + flat_amount
            $table->decimal('tds_amount', 14, 4)->default(0);
            $table->decimal('net_po', 14, 4)->default(0);            // = total_commission - tds_amount
            $table->char('currency_code', 3)->default('INR');

            // Structured tax lines and full calculation trace
            $table->jsonb('tax_lines')->nullable();   // [{code, jurisdiction, basis, rate, amount, currency}]
            $table->jsonb('breakdown')->nullable();   // full calculation trace for audit

            $table->string('status', 20)->default('pending'); // PayoutStatus enum

            // Self-referential FK for void+correction pair
            $table->unsignedBigInteger('reversing_payout_id')->nullable();

            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('policy_id')->references('id')->on('policies')->restrictOnDelete();
            $table->foreign('partner_profile_id')->references('id')->on('partner_profiles')->restrictOnDelete();
            $table->foreign('commission_rate_id')->references('id')->on('commission_rates')->nullOnDelete();
            $table->foreign('reversing_payout_id')->references('id')->on('payouts')->nullOnDelete();

            $table->index(['partner_profile_id', 'status']);
            $table->index(['policy_id', 'status']);
        });

        // GIN indexes on jsonb columns for efficient querying
        DB::statement('CREATE INDEX payouts_tax_lines_gin ON payouts USING GIN (tax_lines)');
        DB::statement('CREATE INDEX payouts_breakdown_gin ON payouts USING GIN (breakdown)');

        // Immutability trigger — blocks financial column updates once status = 'processed'
        DB::statement("
            CREATE OR REPLACE FUNCTION payouts_block_processed_update()
            RETURNS TRIGGER LANGUAGE plpgsql AS \$\$
            BEGIN
                IF OLD.status = 'processed' THEN
                    RAISE EXCEPTION 'payout % is processed and immutable', OLD.id
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            \$\$
        ");

        DB::statement('
            CREATE TRIGGER payouts_immutability
            BEFORE UPDATE ON payouts
            FOR EACH ROW EXECUTE FUNCTION payouts_block_processed_update()
        ');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS payouts_immutability ON payouts');
        DB::statement('DROP FUNCTION IF EXISTS payouts_block_processed_update()');
        Schema::dropIfExists('payouts');
    }
};
