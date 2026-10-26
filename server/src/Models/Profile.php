<?php

namespace Fleetbase\EnhancedPricing\Models;

/**
 * A company's pricing profile. Its currency and distance unit come from the first version and
 * never change; current_version is the newest saved version.
 *
 * @property string   $key
 * @property string   $name
 * @property string   $currency
 * @property string   $distance_unit
 * @property int|null $current_version
 */
final class Profile extends PricingModel
{
    protected $table = 'enhanced_pricing_profiles';

    protected $fillable = ['company_uuid', 'key', 'name', 'currency', 'distance_unit', 'current_version'];

    /** @var array<string, string> */
    protected $casts = ['current_version' => 'integer'];

    public function currentVersion(): ?ProfileVersion
    {
        return ProfileVersion::firstOf(
            ProfileVersion::ofCompany($this->company_uuid)
                ->where('profile_uuid', $this->uuid)
                ->where('version', $this->current_version),
        );
    }
}
