<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_number', 60)->unique();
            $table->unsignedBigInteger('partner_profile_id');
            $table->unsignedBigInteger('insurer_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('business_type_id');  // FK → taxonomy_terms (motor|non_motor|health)

            // Financial fields
            $table->decimal('premium', 14, 4);
            $table->char('currency_code', 3)->default('INR');
            $table->decimal('sum_insured', 14, 4)->nullable();

            // Dates
            $table->date('policy_date');                 // issue/booking date — used for commission rate lookup
            $table->date('start_date');
            $table->date('end_date');

            $table->string('status', 20);                // PolicyStatus enum value
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('partner_profile_id')->references('id')->on('partner_profiles')->restrictOnDelete();
            $table->foreign('insurer_id')->references('id')->on('insurers')->restrictOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('business_type_id')->references('id')->on('taxonomy_terms')->restrictOnDelete();

            $table->index(['partner_profile_id', 'status']);
            $table->index(['insurer_id', 'policy_date']);
            $table->index(['policy_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policies');
    }
};
