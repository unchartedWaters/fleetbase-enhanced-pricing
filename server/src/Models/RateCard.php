<?php

namespace Fleetbase\EnhancedPricing\Models;

/**
 * One of a customer's rate cards, for example one per branch or site.
 *
 * @property string            $pricing_customer_uuid
 * @property string            $label
 * @property array<mixed>|null $meta
 */
final class RateCard extends PricingModel
{
    protected const PARENTS = ['pricing_customer_uuid' => Customer::class];

    protected $table = 'enhanced_pricing_rate_cards';

    protected $fillable = ['company_uuid', 'pricing_customer_uuid', 'label', 'meta'];

    /** @var array<string, string> */
    protected $casts = ['meta' => 'array'];

    public function customer(): ?Customer
    {
        return Customer::firstOf(Customer::ofCompany($this->company_uuid)->whereKey($this->pricing_customer_uuid));
    }
}
