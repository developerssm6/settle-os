<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('non_motor_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('policy_id');
            $table->unsignedBigInteger('policy_type_id')->nullable(); // FK → taxonomy_terms

            $table->decimal('sum_insured', 14, 4)->nullable();
            $table->jsonb('meta')->nullable();                        // product-specific attributes

            $table->timestamps();

            $table->foreign('policy_id')->references('id')->on('policies')->cascadeOnDelete();
            $table->foreign('policy_type_id')->references('id')->on('taxonomy_terms')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('non_motor_details');
    }
};
