<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Manual charges added to orders, billed from what is stored here. Never changed, only removed.
     */
    public function up(): void
    {
        Schema::create('enhanced_pricing_manual_charges', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->char('company_uuid', 36);
            $table->char('order_uuid', 36);
            $table->string('charge_key');
            $table->string('product');
            $table->decimal('quantity', 13, 6)->nullable();
            $table->bigInteger('amount');
            $table->char('profile_version_uuid', 36);
            $table->string('line_name');
            $table->text('note')->nullable();
            $table->char('added_by_uuid', 36)->nullable();
            $table->dateTime('added_at');
            $table->char('removed_by_uuid', 36)->nullable();
            $table->dateTime('removed_at')->nullable();
            $table->timestamps();

            $table->foreign('company_uuid', 'ep_charges_company_fk')
                ->references('uuid')->on('companies')->cascadeOnDelete();
            $table->foreign('profile_version_uuid', 'ep_charges_version_fk')
                ->references('uuid')->on('enhanced_pricing_profile_versions')->cascadeOnDelete();
            $table->index(['company_uuid', 'order_uuid'], 'ep_charges_company_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enhanced_pricing_manual_charges');
    }
};
