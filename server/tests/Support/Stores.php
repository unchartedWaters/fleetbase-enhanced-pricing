<?php

namespace Fleetbase\EnhancedPricing\Tests\Support;

use Fleetbase\EnhancedPricing\Models\Customer;
use Fleetbase\EnhancedPricing\Models\Profile;
use Fleetbase\EnhancedPricing\Models\RateCard;
use Fleetbase\EnhancedPricing\Store\PricingStore;
use Fleetbase\EnhancedPricing\Store\StoreRefused;

/**
 * Stores for the two seeded companies, on a fixed clock, and the records most tests start from.
 * The example profile's scheduling timezone is UTC, so "today" is the clock's UTC date.
 */
final class Stores
{
    public const NOW = '2026-11-02T12:00:00Z';

    public static function for(string $company = 'a', string $now = self::NOW): PricingStore
    {
        return new PricingStore(
            Database::company($company),
            static fn (): \DateTimeImmutable => new \DateTimeImmutable($now),
        );
    }

    /**
     * @param array<string, mixed>|null $definition
     */
    public static function profile(string $company = 'a', ?array $definition = null, string $key = 'main'): Profile
    {
        return self::for($company)->createProfile($key, 'Main', $definition ?? Profiles::example());
    }

    /**
     * @param array<string, mixed>|null $overrides
     */
    public static function customer(string $company = 'a', ?Profile $profile = null, ?array $overrides = null): Customer
    {
        return self::for($company)->saveCustomer(
            Database::customer($company),
            $profile ?? self::profile($company),
            $overrides,
        );
    }

    public static function card(string $company = 'a', ?Customer $customer = null, string $label = 'Main branch'): RateCard
    {
        return self::for($company)->saveCard($customer ?? self::customer($company), $label);
    }

    /**
     * A row of the example profile's "rows" table.
     *
     * @return array<string, mixed>
     */
    public static function row(string $code = 'R1', array $changes = []): array
    {
        return array_merge(['code' => $code, 'first' => 1000, 'second' => 900, 'third' => 800], $changes);
    }

    /**
     * The problem keys of a refusal, without the common prefix.
     *
     * @return list<string>
     */
    public static function refusal(\Closure $call): array
    {
        try {
            $call();
        } catch (StoreRefused $refused) {
            return array_map(
                static fn ($problem): string => (string) preg_replace('/^enhanced-pricing\.[a-z]+\./', '', $problem->key),
                $refused->problems,
            );
        }

        return [];
    }
}
