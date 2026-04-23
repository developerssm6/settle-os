<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motor_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('policy_id');

            // Vehicle dimension FKs (all reference taxonomy_terms)
            $table->unsignedBigInteger('vehicle_type_id');
            $table->unsignedBigInteger('coverage_type_id');   // OD only | TP only | OD+TP
            $table->unsignedBigInteger('vehicle_age_id');
            $table->unsignedBigInteger('fuel_type_id')->nullable();
            $table->unsignedBigInteger('vehicle_subtype_id')->nullable();
            $table->unsignedBigInteger('engine_capacity_id')->nullable();
            $table->unsignedBigInteger('seat_capacity_id')->nullable();
            $table->unsignedBigInteger('weight_type_id')->nullable();
            $table->unsignedBigInteger('vehicle_make_id')->nullable(); // used for MISD only

            // Premium split — used for OD/TP commission calculation
            $table->decimal('own_damage', 14, 4)->default(0);
            $table->decimal('third_party', 14, 4)->default(0);

            // Vehicle identity
            $table->string('registration_number', 20)->nullable();
            $table->string('vehicle_model')->nullable();     // free text; not a FK
            $table->smallInteger('manufacture_year')->nullable();
            $table->string('engine_number', 50)->nullable();
            $table->string('chassis_number', 50)->nullable();

            $table->timestamps();

            $table->foreign('policy_id')->references('id')->on('policies')->cascadeOnDelete();
            $table->foreign('vehicle_type_id')->references('id')->on('taxonomy_terms')->restrictOnDelete();
            $table->foreign('coverage_type_id')->references('id')->on('taxonomy_terms')->restrictOnDelete();
            $table->foreign('vehicle_age_id')->references('id')->on('taxonomy_terms')->restrictOnDelete();
            $table->foreign('fuel_type_id')->references('id')->on('taxonomy_terms')->restrictOnDelete();
            $table->foreign('vehicle_subtype_id')->references('id')->on('taxonomy_terms')->restrictOnDelete();
            $table->foreign('engine_capacity_id')->references('id')->on('taxonomy_terms')->restrictOnDelete();
            $table->foreign('seat_capacity_id')->references('id')->on('taxonomy_terms')->restrictOnDelete();
            $table->foreign('weight_type_id')->references('id')->on('taxonomy_terms')->restrictOnDelete();
            $table->foreign('vehicle_make_id')->references('id')->on('taxonomy_terms')->restrictOnDelete();

            $table->index(['vehicle_type_id', 'coverage_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motor_details');
    }
};
