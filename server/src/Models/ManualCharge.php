<?php

namespace Fleetbase\EnhancedPricing\Models;

/**
 * A manual charge on an order, billed from what is stored here. It is never edited or deleted;
 * removing it sets removed_by_uuid and removed_at, once.
 *
 * @property string      $order_uuid
 * @property string      $charge_key
 * @property string      $product
 * @property string|null $quantity
 * @property int         $amount
 * @property string      $profile_version_uuid
 * @property string      $line_name
 * @property string|null $note
 * @property string|null $added_by_uuid
 * @property string      $added_at
 * @property string|null $removed_by_uuid
 * @property string|null $removed_at
 */
final class ManualCharge extends PricingModel
{
    protected const PARENTS = ['profile_version_uuid' => ProfileVersion::class];

    private const REMOVAL = ['removed_by_uuid', 'removed_at', 'updated_at'];

    protected $table = 'enhanced_pricing_manual_charges';

    protected $fillable = [
        'company_uuid',
        'order_uuid',
        'charge_key',
        'product',
        'quantity',
        'amount',
        'profile_version_uuid',
        'line_name',
        'note',
        'added_by_uuid',
        'added_at',
        'removed_by_uuid',
        'removed_at',
    ];

    /** @var array<string, string> */
    protected $casts = ['quantity' => 'decimal:6', 'amount' => 'integer'];

    protected static function booted(): void
    {
        parent::booted();
        static::updating(static function (ManualCharge $charge): void {
            $removal = $charge->getOriginal('removed_at') === null
                && $charge->removed_at !== null
                && array_diff(array_keys($charge->getDirty()), self::REMOVAL) === [];
            if ($removal === false) {
                throw new ImmutableRecord(self::class);
            }
        });
        static::deleting(static function (): void {
            throw new ImmutableRecord(self::class);
        });
    }
}
