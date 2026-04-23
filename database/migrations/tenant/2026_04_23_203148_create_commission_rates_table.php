<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Required for EXCLUDE USING GIST with non-geometric types
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('commission_rates', function (Blueprint $table) {
            $table->id();

            // Primary dimension FKs (scalar, always present)
            $table->unsignedBigInteger('insurer_id');
            $table->unsignedBigInteger('business_type_id');  // FK → taxonomy_terms (motor|non_motor|health)
            $table->unsignedBigInteger('partner_id')->nullable(); // NULL = global rate

            // Motor discriminator — NULL for non-motor/health
            $table->unsignedBigInteger('vehicle_type_id')->nullable();

            // Variable vehicle attributes — only relevant dims stored; NULL for non-motor
            // Keys: coverage, age, fuel, subtype, engine, seat, weight, make  (taxonomy_term IDs)
            $table->jsonb('vehicle_attrs')->nullable();

            // Rate columns
            $table->decimal('od_percent', 6, 3)->default(0);   // Own Damage % (motor only)
            $table->decimal('tp_percent', 6, 3)->default(0);   // Third Party % (motor only)
            $table->decimal('net_percent', 6, 3)->default(0);  // Net/total % — overrides od+tp when > 0
            $table->decimal('flat_amount', 14, 4)->default(0); // Fixed addition on top of % commission
            $table->char('currency_code', 3)->default('INR');

            // Effective date window (PostgreSQL native daterange)
            // Stored as [from, to) — open-ended: [2025-04-01, infinity)
            $table->timestamps();

            $table->foreign('insurer_id')->references('id')->on('insurers')->restrictOnDelete();
            $table->foreign('business_type_id')->references('id')->on('taxonomy_terms')->restrictOnDelete();
            $table->foreign('partner_id')->references('id')->on('partner_profiles')->cascadeOnDelete();
            $table->foreign('vehicle_type_id')->references('id')->on('taxonomy_terms')->restrictOnDelete();

            $table->index(['insurer_id', 'business_type_id', 'partner_id']);
            $table->index('vehicle_type_id');
        });

        // Add daterange column (Blueprint has no native daterange helper)
        DB::statement("ALTER TABLE commission_rates ADD COLUMN effective_range daterange NOT NULL DEFAULT daterange(CURRENT_DATE, 'infinity')");

        // Deterministic composite key — PostgreSQL sorts jsonb keys alphabetically, so the
        // same set of dims always produces the same text regardless of insertion order.
        DB::statement("
            ALTER TABLE commission_rates
            ADD COLUMN dims_key text GENERATED ALWAYS AS (
                COALESCE(vehicle_type_id::text, '_')
                || ':'
                || COALESCE(vehicle_attrs::text, '{}')
            ) STORED
        ");

        // GIN index for vehicle_attrs containment queries
        DB::statement('CREATE INDEX commission_rates_vehicle_attrs_gin ON commission_rates USING GIN (vehicle_attrs)');

        // GiST index on effective_range for daterange containment queries
        DB::statement('CREATE INDEX commission_rates_effective_range_gist ON commission_rates USING GIST (effective_range)');

        // Exclusion constraint — prevents two rows for the same combination with overlapping date windows.
        // COALESCE(partner_id, 0) treats NULL as 0 so the constraint covers global rates too.
        DB::statement('
            ALTER TABLE commission_rates
            ADD CONSTRAINT commission_rates_no_overlap
            EXCLUDE USING GIST (
                insurer_id              WITH =,
                business_type_id        WITH =,
                COALESCE(partner_id, 0) WITH =,
                dims_key                WITH =,
                effective_range         WITH &&
            )
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rates');
    }
};
