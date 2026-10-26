<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * The Fleet-Ops customers set up for pricing, each with one profile and its overrides.
     * checked_version and problems record the last check against a profile version.
     */
    public function up(): void
    {
        Schema::create('enhanced_pricing_customers', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->char('company_uuid', 36);
            $table->char('customer_uuid', 36);
            $table->char('profile_uuid', 36);
            $table->json('overrides')->nullable();
            $table->unsignedInteger('checked_version')->nullable();
            $table->json('problems')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('company_uuid', 'ep_customers_company_fk')
                ->references('uuid')->on('companies')->cascadeOnDelete();
            $table->foreign('profile_uuid', 'ep_customers_profile_fk')
                ->references('uuid')->on('enhanced_pricing_profiles')->cascadeOnDelete();
            $table->unique(['company_uuid', 'customer_uuid'], 'ep_customers_company_customer_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enhanced_pricing_customers');
    }
};
