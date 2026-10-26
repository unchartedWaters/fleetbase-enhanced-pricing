<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * A company's pricing profiles. currency and distance_unit come from the first version and never
     * change; current_version is the newest saved version.
     */
    public function up(): void
    {
        Schema::create('enhanced_pricing_profiles', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->char('company_uuid', 36);
            $table->string('key');
            $table->string('name');
            $table->char('currency', 3);
            $table->string('distance_unit', 2);
            $table->unsignedInteger('current_version')->nullable();
            $table->timestamps();

            $table->foreign('company_uuid', 'ep_profiles_company_fk')
                ->references('uuid')->on('companies')->cascadeOnDelete();
            $table->unique(['company_uuid', 'key'], 'ep_profiles_company_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enhanced_pricing_profiles');
    }
};
