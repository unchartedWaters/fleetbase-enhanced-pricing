<?php

namespace Fleetbase\EnhancedPricing\Profile;

/**
 * One thing wrong with a profile, an override or a dated change: where it is (a dot path such as
 * "fees.2.thresholds.0.over", "" for the whole), a translation key and the key's parameters.
 */
final class Problem
{
    /**
     * @param array<string, scalar|null> $params
     */
    public function __construct(
        public readonly string $path,
        public readonly string $key,
        public readonly array $params = [],
    ) {
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public static function atPath(string $path, string $name, array $params = []): self
    {
        return new self($path, 'enhanced-pricing.profile.' . $name, $params);
    }

    /**
     * @return array{path: string, key: string, params: array<string, scalar|null>}
     */
    public function toArray(): array
    {
        return ['path' => $this->path, 'key' => $this->key, 'params' => $this->params];
    }
}
