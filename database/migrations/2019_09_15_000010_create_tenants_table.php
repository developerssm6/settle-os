<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();         // slug, e.g. "acme"

            $table->string('name');                          // display name
            $table->string('plan')->default('standard');     // future billing tier
            $table->boolean('is_active')->default(true);

            $table->string('region', 10)->nullable();        // e.g. IN
            $table->string('timezone', 50)->default('Asia/Kolkata');
            $table->char('default_currency', 3)->default('INR');
            $table->string('default_locale', 10)->default('en_IN');
            $table->string('tax_regime_slug', 20)->default('IN_GST'); // future: multi-jurisdiction

            $table->timestamps();
            $table->json('data')->nullable();                // stancl/tenancy internal use
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
