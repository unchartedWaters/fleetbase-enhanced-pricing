<?php

namespace Fleetbase\EnhancedPricing\Store;

use Fleetbase\EnhancedPricing\Profile\Problem;

/**
 * A write the store refused, with every problem found. A version refused because it leaves stored
 * entries stale also lists them: each entry's kind (customer, dated_change or row), uuid and
 * problems.
 */
final class StoreRefused extends \DomainException
{
    /**
     * @param list<Problem>                                                    $problems
     * @param list<array{kind: string, uuid: string, problems: list<Problem>}> $stale
     */
    public function __construct(public readonly array $problems, public readonly array $stale = [])
    {
        parent::__construct('enhanced-pricing.store.refused');
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public static function refuse(string $name, array $params = [], string $path = ''): self
    {
        return new self([self::problem($name, $params, $path)]);
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public static function problem(string $name, array $params = [], string $path = ''): Problem
    {
        return new Problem($path, 'enhanced-pricing.store.' . $name, $params);
    }

    /**
     * Problems as stored in a problems column.
     *
     * @param list<Problem> $problems
     *
     * @return list<array{path: string, key: string, params: array<string, scalar|null>}>|null
     */
    public static function stored(array $problems): ?array
    {
        return $problems === [] ? null : array_map(static fn (Problem $problem) => $problem->toArray(), $problems);
    }
}
