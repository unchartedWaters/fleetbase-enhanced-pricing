<?php

namespace Fleetbase\EnhancedPricing\Tests\Support;

use Fleetbase\EnhancedPricing\Models\Customer;
use Fleetbase\EnhancedPricing\Models\DatedChange;
use Fleetbase\EnhancedPricing\Models\ManualCharge;
use Fleetbase\EnhancedPricing\Models\Profile;
use Fleetbase\EnhancedPricing\Models\ProfileVersion;
use Fleetbase\EnhancedPricing\Models\RateCard;
use Fleetbase\EnhancedPricing\Models\RateCardRow;
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

    public static function profileOf(string $company, string $key = 'main'): Profile
    {
        return Profile::firstOf(Profile::ofCompany(Database::company($company))->where('key', $key))
            ?? throw new \LogicException("no profile $key");
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

    public static function card(string $company = 'a', ?Customer $customer = null, string $label = 'Main'): RateCard
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
                static fn ($problem): string => (string) preg_replace('/^enhanced-pricing\.\w+\./', '', $problem->key),
                $refused->problems,
            );
        }

        return [];
    }

    /**
     * One of everything for a company, through the store.
     *
     * @return array{
     *     profile: Profile,
     *     version: ProfileVersion,
     *     customer: Customer,
     *     card: RateCard,
     *     row: RateCardRow,
     *     change: DatedChange,
     *     charge: ManualCharge,
     * }
     */
    public static function everything(string $company): array
    {
        $store    = self::for($company);
        $profile  = self::profile($company);
        $customer = self::customer($company, $profile);
        $card     = $store->saveCard($customer, 'Main branch');
        $rows     = $store->saveRows($card, 'rows', [['values' => self::row()]]);
        $change   = $store->addDatedChange($profile, 'subtotal_fees.percent.percentage', 1300, '2026-12-01');
        $version  = $profile->currentVersion() ?? throw new \LogicException('no version');
        $charge   = $store->addManualCharge(Database::order($company), $version, self::hourly());

        return [
            'profile'  => $profile,
            'version'  => $version,
            'customer' => $customer,
            'card'     => $card,
            'row'      => $rows[0],
            'change'   => $change,
            'charge'   => $charge,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function hourly(string $quantity = '1.5'): array
    {
        return [
            'charge_key' => 'hourly',
            'product'    => 'HOURLY',
            'quantity'   => $quantity,
            'amount'     => 6300,
            'line_name'  => "Hourly charge ($quantity h)",
            'note'       => 'Waited at the dock',
        ];
    }

    /**
     * The example profile without the fee keyed $key.
     *
     * @return array<string, mixed>
     */
    public static function withoutFee(string $key): array
    {
        $profile         = Profiles::example();
        $profile['fees'] = array_values(
            array_filter($profile['fees'], static fn (array $fee) => $fee['key'] !== $key),
        );

        return $profile;
    }
}
