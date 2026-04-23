<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomy_terms', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);                  // vehicle_type|fuel_type|engine_capacity|coverage_type|vehicle_age|seat_capacity|weight_type|vehicle_subtype|vehicle_make|business_type|policy_type
            $table->string('name');
            $table->string('slug', 60);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->jsonb('meta')->nullable();           // extra attributes per type
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['type', 'slug']);
            $table->foreign('parent_id')->references('id')->on('taxonomy_terms')->nullOnDelete();
            $table->index(['type', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_terms');
    }
};
