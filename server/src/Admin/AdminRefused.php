<?php

namespace Fleetbase\EnhancedPricing\Admin;

use Fleetbase\EnhancedPricing\Profile\Problem;
use Fleetbase\EnhancedPricing\Store\StoreRefused;

/**
 * A console request refused, with the HTTP status and the body the console reads. fetch() gives
 * the console the body but not the status, so the body names the refusal itself:
 *
 * - not-found (404): no such record for this company; another company's is "not found" too, so
 *   the console can't learn what exists;
 * - invalid (422): problems, each { path, key, params };
 * - outdated (409): the profile has a newer version than the one edited, under "newer";
 * - stale (409): the version leaves stored entries that no longer fit, listed under "stale";
 * - large-change (422): a value about ten times the one it replaces, to confirm.
 */
final class AdminRefused extends \RuntimeException
{
    /**
     * @param array<string, mixed> $body
     */
    public function __construct(public readonly int $status, public readonly array $body)
    {
        parent::__construct(is_string($body['refused'] ?? null) ? $body['refused'] : 'refused');
    }

    public static function notFound(): self
    {
        return new self(404, ['refused' => 'not-found']);
    }

    /**
     * A problem found by the console endpoints themselves, with English text under
     * enhanced-pricing.admin.
     *
     * @param array<string, scalar|null> $params
     */
    public static function invalid(string $name, array $params = [], string $path = ''): self
    {
        return self::problems([new Problem($path, 'enhanced-pricing.admin.' . $name, $params)]);
    }

    /**
     * @param list<Problem> $problems
     */
    public static function problems(array $problems, string $refused = 'invalid', int $status = 422): self
    {
        return new self($status, [
            'refused'  => $refused,
            'problems' => array_map(static fn (Problem $problem): array => $problem->toArray(), $problems),
        ]);
    }

    public static function fromStore(StoreRefused $refused): self
    {
        $keys = array_map(static fn (Problem $problem): string => $problem->key, $refused->problems);
        if (in_array('enhanced-pricing.store.version-outdated', $keys, true)) {
            return self::problems($refused->problems, 'outdated', 409);
        }
        if (in_array('enhanced-pricing.store.large-change', $keys, true)) {
            return self::problems($refused->problems, 'large-change');
        }

        return self::problems($refused->problems);
    }

    /**
     * This refusal with more in its body.
     *
     * @param array<string, mixed> $more
     */
    public function with(array $more): self
    {
        return new self($this->status, $this->body + $more);
    }
}
