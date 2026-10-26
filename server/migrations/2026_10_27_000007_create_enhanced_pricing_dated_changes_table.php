<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Dated changes to a profile's global values. live is 1, or NULL once cancelled, so a cancelled
     * change never blocks its date in the unique key.
     */
    public function up(): void
    {
        Schema::create('enhanced_pricing_dated_changes', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->char('company_uuid', 36);
            $table->char('profile_uuid', 36);
            $table->string('path');
            $table->json('value')->nullable();
            $table->date('effective_from');
            $table->char('added_by_uuid', 36)->nullable();
            $table->dateTime('added_at');
            $table->char('cancelled_by_uuid', 36)->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->unsignedTinyInteger('live')->nullable();
            $table->unsignedInteger('checked_version')->nullable();
            $table->json('problems')->nullable();
            $table->timestamps();

            $table->foreign('company_uuid', 'ep_changes_company_fk')
                ->references('uuid')->on('companies')->cascadeOnDelete();
            $table->foreign('profile_uuid', 'ep_changes_profile_fk')
                ->references('uuid')->on('enhanced_pricing_profiles')->cascadeOnDelete();
            $table->unique(
                ['company_uuid', 'profile_uuid', 'path', 'effective_from', 'live'],
                'ep_changes_live_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enhanced_pricing_dated_changes');
    }
};
