<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('code', 20)->unique();        // PTR-0001
            $table->string('display_name');
            $table->string('business_type', 20);         // BusinessTypeSlug value
            $table->string('state_code', 2)->nullable(); // ISO 3166-2 state, for GST intra/inter determination

            // KYC — stored encrypted in application layer; plain column at DB level
            $table->string('pan', 500)->nullable();
            $table->string('pan_hash', 64)->nullable();  // SHA-256 of normalised PAN for lookups
            $table->string('gstin', 500)->nullable();
            $table->string('tan', 500)->nullable();

            $table->boolean('is_gst_registered')->default(false);

            // Bank details — encrypted in application layer
            $table->string('bank_account_number', 500)->nullable();
            $table->string('bank_ifsc', 20)->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_name')->nullable();

            $table->boolean('is_active')->default(true);
            $table->date('onboarded_on')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('pan_hash');
            $table->index(['is_active', 'business_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_profiles');
    }
};
