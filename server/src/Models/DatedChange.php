<?php

namespace Fleetbase\EnhancedPricing\Models;

/**
 * A dated change to one of a profile's global values. live is 1, or NULL once cancelled, so a
 * cancelled change never blocks its date. Only cancelling and the recheck columns ever change.
 *
 * @property string                          $profile_uuid
 * @property string                          $path
 * @property mixed                           $value
 * @property string                          $effective_from
 * @property string|null                     $added_by_uuid
 * @property string                          $added_at
 * @property string|null                     $cancelled_by_uuid
 * @property string|null                     $cancelled_at
 * @property int|null                        $live
 * @property int|null                        $checked_version
 * @property list<array<string, mixed>>|null $problems
 */
final class DatedChange extends PricingModel
{
    protected const PARENTS = ['profile_uuid' => Profile::class];

    private const FIXED = ['company_uuid', 'profile_uuid', 'path', 'value', 'effective_from', 'added_at'];

    protected $table = 'enhanced_pricing_dated_changes';

    protected $fillable = [
        'company_uuid',
        'profile_uuid',
        'path',
        'value',
        'effective_from',
        'added_by_uuid',
        'added_at',
        'cancelled_by_uuid',
        'cancelled_at',
        'live',
        'checked_version',
        'problems',
    ];

    /** @var array<string, string> */
    protected $casts = ['value' => 'json', 'live' => 'integer', 'checked_version' => 'integer', 'problems' => 'array'];

    protected static function booted(): void
    {
        parent::booted();
        static::updating(static function (DatedChange $change): void {
            $revived = $change->getOriginal('live') === null && $change->live !== null;
            if ($revived || $change->isDirty(self::FIXED)) {
                throw new ImmutableRecord(self::class);
            }
        });
        static::deleting(static function (): void {
            throw new ImmutableRecord(self::class);
        });
    }
}
