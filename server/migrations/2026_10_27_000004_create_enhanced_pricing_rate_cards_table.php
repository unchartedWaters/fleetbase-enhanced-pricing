<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * A customer's rate cards, for example one per branch.
     */
    public function up(): void
    {
        Schema::create('enhanced_pricing_rate_cards', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->char('company_uuid', 36);
            $table->char('pricing_customer_uuid', 36);
            $table->string('label');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('company_uuid', 'ep_cards_company_fk')
                ->references('uuid')->on('companies')->cascadeOnDelete();
            $table->foreign('pricing_customer_uuid', 'ep_cards_customer_fk')
                ->references('uuid')->on('enhanced_pricing_customers')->cascadeOnDelete();
            $table->unique(['pricing_customer_uuid', 'label'], 'ep_cards_customer_label_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enhanced_pricing_rate_cards');
    }
};
