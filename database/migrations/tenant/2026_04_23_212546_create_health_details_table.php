<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('policy_id');
            $table->unsignedBigInteger('policy_type_id')->nullable();     // FK → taxonomy_terms
            $table->unsignedBigInteger('coverage_type_id')->nullable();   // individual | floater

            $table->decimal('sum_insured', 14, 4)->nullable();
            $table->unsignedSmallInteger('member_count')->default(1);
            $table->jsonb('members')->nullable();                         // [{name, dob, relationship}]

            $table->timestamps();

            $table->foreign('policy_id')->references('id')->on('policies')->cascadeOnDelete();
            $table->foreign('policy_type_id')->references('id')->on('taxonomy_terms')->restrictOnDelete();
            $table->foreign('coverage_type_id')->references('id')->on('taxonomy_terms')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_details');
    }
};
