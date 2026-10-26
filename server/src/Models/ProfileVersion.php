<?php

namespace Fleetbase\EnhancedPricing\Models;

/**
 * A saved version of a profile. It never changes and is never deleted, so an order priced with it
 * can be priced again the same way. definition is the profile as saved.
 *
 * @property string               $profile_uuid
 * @property int                  $version
 * @property array<string, mixed> $definition
 * @property array<mixed>|null    $meta
 * @property string|null          $created_by_uuid
 */
final class ProfileVersion extends PricingModel
{
    protected const PARENTS = ['profile_uuid' => Profile::class];

    protected $table = 'enhanced_pricing_profile_versions';

    protected $fillable = ['company_uuid', 'profile_uuid', 'version', 'definition', 'meta', 'created_by_uuid'];

    /** @var array<string, string> */
    protected $casts = ['version' => 'integer', 'definition' => 'array', 'meta' => 'array'];

    protected static function booted(): void
    {
        parent::booted();
        static::updating(static function (): void {
            throw new ImmutableRecord(self::class);
        });
        static::deleting(static function (): void {
            throw new ImmutableRecord(self::class);
        });
    }
}
