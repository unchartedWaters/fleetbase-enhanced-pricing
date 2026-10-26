<?php

namespace Fleetbase\EnhancedPricing\Models;

/**
 * A row of one of the profile's rate tables, on a card. row_key is the SHA-256 of the key columns'
 * values as a JSON array, kept in key_values; it is set when the row is first saved and never
 * recomputed. The store checks the values; this model checks only the card's company.
 *
 * @property string                          $rate_card_uuid
 * @property string                          $table_key
 * @property string                          $row_key
 * @property list<mixed>|null                $key_values
 * @property array<mixed>|null               $values
 * @property int|null                        $checked_version
 * @property list<array<string, mixed>>|null $problems
 * @property array<mixed>|null               $meta
 */
final class RateCardRow extends PricingModel
{
    protected const PARENTS = ['rate_card_uuid' => RateCard::class];

    protected $table = 'enhanced_pricing_rate_card_rows';

    protected $fillable = [
        'company_uuid',
        'rate_card_uuid',
        'table_key',
        'row_key',
        'key_values',
        'values',
        'checked_version',
        'problems',
        'meta',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'key_values'      => 'array',
        'values'          => 'array',
        'checked_version' => 'integer',
        'problems'        => 'array',
        'meta'            => 'array',
    ];
}
