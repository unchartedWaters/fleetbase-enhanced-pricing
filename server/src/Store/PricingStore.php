<?php

namespace Fleetbase\EnhancedPricing\Store;

use Fleetbase\EnhancedPricing\Models\Customer;
use Fleetbase\EnhancedPricing\Models\DatedChange;
use Fleetbase\EnhancedPricing\Models\ManualCharge;
use Fleetbase\EnhancedPricing\Models\Profile;
use Fleetbase\EnhancedPricing\Models\ProfileVersion;
use Fleetbase\EnhancedPricing\Models\RateCard;
use Fleetbase\EnhancedPricing\Models\RateCardRow;

/**
 * Every write of one company's pricing data goes through here, so every check runs whichever way
 * the data arrives: the loader, the console or the API. Each write that depends on the profile's
 * version takes the profile row's lock and reads the current version under it.
 *
 * Profiles and versions are in VersionStore, customers and rate cards in RateStore, dated changes
 * in ChangeStore and manual charges in ChargeStore. This class is the one entry point. Each method
 * throws StoreRefused, with the problems, when a check fails, and CrossCompanyReference when it is
 * handed another company's record.
 */
final class PricingStore
{
    private readonly StoreContext $context;

    /**
     * @param (callable(): \DateTimeImmutable)|null $clock
     */
    public function __construct(public readonly string $company, ?callable $clock = null)
    {
        $this->context = new StoreContext($company, $clock);
    }

    /**
     * @param array<string, mixed> $definition the first version, as saved
     */
    public function createProfile(string $key, string $name, array $definition, ?string $userUuid = null): Profile
    {
        return (new VersionStore($this->context))->create($key, $name, $definition, $userUuid);
    }

    /**
     * Saves a new version. It is refused when it was built on an older version, changes a unit that
     * stored values depend on, or leaves stored entries that no longer fit, unless $acceptStale.
     *
     * @param array<string, mixed> $definition the new version, as saved
     */
    public function addVersion(
        Profile $profile,
        array $definition,
        ?string $userUuid = null,
        ?int $builtOn = null,
        bool $acceptStale = false,
    ): ProfileVersion {
        return (new VersionStore($this->context))->add($profile, $definition, $userUuid, $builtOn, $acceptStale);
    }

    /**
     * Saves a customer. A money or percent override ten or more times away from the profile's value
     * is refused as a likely unit slip, unless $acceptLargeChange.
     *
     * @param array<string, mixed>|null $overrides null keeps the stored ones, [] clears them
     * @param array<mixed>|null         $meta      null keeps the stored meta
     */
    public function saveCustomer(
        string $customerUuid,
        Profile $profile,
        ?array $overrides = null,
        ?array $meta = null,
        bool $acceptLargeChange = false,
    ): Customer {
        return (new RateStore($this->context))->saveCustomer(
            $customerUuid,
            $profile,
            $overrides,
            $meta,
            $acceptLargeChange,
        );
    }

    /**
     * The profile's customers with their own override at a path. A dated change of that path
     * doesn't reach them.
     *
     * @return list<Customer>
     */
    public function customersWithOverride(Profile $profile, string $path): array
    {
        return (new RateStore($this->context))->withOverride($profile, $path);
    }

    /**
     * @param array<mixed>|null $meta
     */
    public function saveCard(Customer $customer, string $label, ?array $meta = null): RateCard
    {
        return (new RateStore($this->context))->saveCard($customer, $label, $meta);
    }

    /**
     * @param list<array{values: array<mixed>, meta?: array<mixed>|null}> $rows
     *
     * @return list<RateCardRow>
     */
    public function saveRows(RateCard $card, string $tableKey, array $rows): array
    {
        return (new RateStore($this->context))->saveRows($card, $tableKey, $rows);
    }

    /**
     * Adds a dated change. A money or percent value ten or more times away from the profile's value
     * is refused as a likely unit slip, unless $acceptLargeChange.
     */
    public function addDatedChange(
        Profile $profile,
        string $path,
        mixed $value,
        string $effectiveFrom,
        ?string $userUuid = null,
        bool $acceptLargeChange = false,
    ): DatedChange {
        return (new ChangeStore($this->context))->add(
            $profile,
            $path,
            $value,
            $effectiveFrom,
            $userUuid,
            $acceptLargeChange,
        );
    }

    public function cancelDatedChange(DatedChange $change, ?string $userUuid = null): DatedChange
    {
        return (new ChangeStore($this->context))->cancel($change, $userUuid);
    }

    /**
     * @param array<string, mixed> $fields charge_key, product, quantity, amount, line_name, note
     */
    public function addManualCharge(
        string $orderUuid,
        ProfileVersion $version,
        array $fields,
        ?string $userUuid = null,
    ): ManualCharge {
        return (new ChargeStore($this->context))->add($orderUuid, $version, $fields, $userUuid);
    }

    public function removeManualCharge(ManualCharge $charge, ?string $userUuid = null): ManualCharge
    {
        return (new ChargeStore($this->context))->remove($charge, $userUuid);
    }
}
