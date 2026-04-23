<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email', 320)->nullable();
            $table->string('phone', 20)->nullable();
            $table->date('dob')->nullable();
            $table->text('address')->nullable();
            $table->string('pan', 500)->nullable();      // encrypted in application layer
            $table->string('pan_hash', 64)->nullable();  // SHA-256 for lookups
            $table->timestamps();
            $table->softDeletes();

            $table->index('pan_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
