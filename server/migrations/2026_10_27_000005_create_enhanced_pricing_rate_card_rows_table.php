<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Rows of the profile's rate tables on a card. row_key is the SHA-256 of the key columns' values
     * as a JSON array, which key_values keeps; it is set once and never recomputed.
     */
    public function up(): void
    {
        Schema::create('enhanced_pricing_rate_card_rows', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->char('company_uuid', 36);
            $table->char('rate_card_uuid', 36);
            $table->string('table_key');
            $table->char('row_key', 64);
            $table->json('key_values')->nullable();
            $table->json('values')->nullable();
            $table->unsignedInteger('checked_version')->nullable();
            $table->json('problems')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('company_uuid', 'ep_rows_company_fk')
                ->references('uuid')->on('companies')->cascadeOnDelete();
            $table->foreign('rate_card_uuid', 'ep_rows_card_fk')
                ->references('uuid')->on('enhanced_pricing_rate_cards')->cascadeOnDelete();
            $table->unique(['rate_card_uuid', 'table_key', 'row_key'], 'ep_rows_card_table_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enhanced_pricing_rate_card_rows');
    }
};
