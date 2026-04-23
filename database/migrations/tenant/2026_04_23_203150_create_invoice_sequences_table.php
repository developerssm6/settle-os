<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('issuer_id');
            $table->string('prefix', 10)->default('INV');
            $table->unsignedSmallInteger('fiscal_year');    // e.g. 2025 for FY 2025-26
            $table->unsignedInteger('next_value')->default(1); // incremented inside SELECT FOR UPDATE transaction
            $table->timestamps();

            $table->unique(['issuer_id', 'prefix', 'fiscal_year']);
            $table->foreign('issuer_id')->references('id')->on('invoice_issuers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
    }
};
