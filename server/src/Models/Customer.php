<?php

namespace Fleetbase\EnhancedPricing\Models;

use Fleetbase\EnhancedPricing\Profile\OverrideValidator;
use Fleetbase\EnhancedPricing\Store\StoreRefused;

/**
 * A Fleet-Ops customer set up for pricing: its profile and its overrides of the profile's values.
 * customer_uuid is the Fleet-Ops contact, named as on orders. checked_version and problems record
 * the last check of the overrides against a profile version; problems is NULL when they fit.
 *
 * @property string                          $customer_uuid
 * @property string                          $profile_uuid
 * @property array<string, mixed>|null       $overrides
 * @property int|null                        $checked_version
 * @property list<array<string, mixed>>|null $problems
 * @property array<mixed>|null               $meta
 */
final class Customer extends PricingModel
{
    protected const PARENTS = ['profile_uuid' => Profile::class];

    protected $table = 'enhanced_pricing_customers';

    protected $fillable = [
        'company_uuid',
        'customer_uuid',
        'profile_uuid',
        'overrides',
        'checked_version',
        'problems',
        'meta',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'overrides'       => 'array',
        'checked_version' => 'integer',
        'problems'        => 'array',
        'meta'            => 'array',
    ];

    public function profile(): ?Profile
    {
        return Profile::firstOf(Profile::ofCompany($this->company_uuid)->whereKey($this->profile_uuid));
    }

    public function hasCards(): bool
    {
        return RateCard::ofCompany($this->company_uuid)->where('pricing_customer_uuid', $this->uuid)->exists();
    }

    protected static function booted(): void
    {
        parent::booted();
        static::saving(static function (Customer $customer): void {
            if ($customer->exists === false || $customer->isDirty(['overrides', 'profile_uuid'])) {
                $customer->checkOverrides();
            }
        });
    }

    /**
     * Overrides are checked against the profile's current version whenever they or the profile
     * change, and only then, so a save of meta alone never fails on a stale customer.
     *
     * @throws StoreRefused
     */
    protected function checkOverrides(): void
    {
        $profile = $this->profile();
        $version = $profile?->currentVersion();
        if ($profile === null || $version === null) {
            throw new CrossCompanyReference(self::class, 'profile_uuid');
        }
        $problems = (new OverrideValidator())->check($version->definition, $this->overrides ?? []);
        if ($problems !== []) {
            throw new StoreRefused($problems);
        }
        $this->checked_version = $version->version;
        $this->problems        = null;
    }
}
