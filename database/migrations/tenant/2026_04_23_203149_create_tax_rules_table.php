<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();        // TDS_194D | GST_997161 | CGST_997161 | SGST_997161
            $table->string('tax_type', 10);              // tds | gst
            $table->string('jurisdiction', 10);          // IN | IN-OD | IN-MH …
            $table->string('applies_to', 20);            // TaxAppliesTo: net_commission | total_commission | premium

            $table->decimal('rate', 6, 5);               // 0.05000 = 5%
            $table->decimal('annual_threshold', 14, 4)->default(0); // Section 194D: ₹15,000 YTD threshold

            // Conditions jsonb — e.g. {"business_type": ["individual","huf","proprietor"]}
            // NULL = applies unconditionally
            $table->jsonb('conditions')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tax_type', 'jurisdiction', 'is_active']);
        });

        // Add daterange column (Blueprint has no native daterange helper)
        DB::statement("ALTER TABLE tax_rules ADD COLUMN effective_range daterange NOT NULL DEFAULT daterange(CURRENT_DATE, 'infinity')");

        // GIN index on conditions for jsonb containment queries
        DB::statement('CREATE INDEX tax_rules_conditions_gin ON tax_rules USING GIN (conditions)');

        // GiST index on effective_range
        DB::statement('CREATE INDEX tax_rules_effective_range_gist ON tax_rules USING GIST (effective_range)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rules');
    }
};
