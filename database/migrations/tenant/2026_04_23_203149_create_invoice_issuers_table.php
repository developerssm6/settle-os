<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_issuers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('address')->nullable();
            $table->char('state_code', 2)->nullable();   // ISO 3166-2 state code, for GST intra/inter
            $table->char('currency_code', 3)->default('INR');

            // Identity docs — encrypted in application layer
            $table->string('pan', 500)->nullable();
            $table->string('gstin', 500)->nullable();
            $table->string('tan', 500)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_issuers');
    }
};
