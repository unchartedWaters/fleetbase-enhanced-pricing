<?php

namespace Fleetbase\EnhancedPricing\Admin;

use Fleetbase\EnhancedPricing\Models\Customer;
use Fleetbase\EnhancedPricing\Models\DatedChange;
use Fleetbase\EnhancedPricing\Models\Profile;
use Fleetbase\EnhancedPricing\Profile\ProfileReferences as R;
use Fleetbase\EnhancedPricing\Store\PricingStore;
use Fleetbase\EnhancedPricing\Store\StoreContext;
use Fleetbase\EnhancedPricing\Store\StoreRefused;

/**
 * The admin page's dated changes. The page schedules and cancels changes to a subtotal fee's
 * global percentage or amount only (plan, Endpoints); a loader changes any other value. Every
 * stored change of the profile is listed, read-only, with its state.
 */
final class Changes
{
    private const PATH = '/^subtotal_fees\.([a-z0-9][a-z0-9_-]{0,63})\.(percentage|amount)$/';

    private readonly StoreContext $context;

    private readonly PricingStore $store;

    private readonly Names $names;

    /**
     * @param (callable(): \DateTimeImmutable)|null $clock
     */
    public function __construct(private readonly string $company, ?callable $clock = null)
    {
        $this->context = new StoreContext($company, $clock);
        $this->store   = new PricingStore($company, $clock);
        $this->names   = new Names($company);
    }

    /**
     * @return array{today: string, subtotal_fees: list<array<string, mixed>>, changes: list<array<string, mixed>>}
     */
    public function schedule(string $profileKey): array
    {
        $state     = ProfileState::of($this->company, $profileKey, $this->context);
        $query     = DatedChange::ofCompany($this->company)->where('profile_uuid', $state->profile->uuid);
        $query->getQuery()->orderBy('path')->orderBy('effective_from')->orderBy('added_at');
        $all       = DatedChange::listOf($query);
        $customers = Customer::listOf(
            Customer::ofCompany($this->company)->where('profile_uuid', $state->profile->uuid),
        );
        $names     = $this->names->customers(
            array_map(static fn (Customer $customer) => $customer->customer_uuid, $customers),
        );
        $users     = $this->names->users(array_merge(
            array_map(static fn (DatedChange $change) => $change->added_by_uuid, $all),
            array_map(static fn (DatedChange $change) => $change->cancelled_by_uuid, $all),
        ));
        $changes   = array_map(fn (DatedChange $change): array => $this->change($change, $state->today, $users), $all);

        $fees = [];
        foreach (R::items($state->normalized['subtotal_fees'] ?? []) as $fee) {
            $field  = $fee['mode'] === 'percentage' ? 'percentage' : 'amount';
            $path   = 'subtotal_fees.' . R::text($fee['key']) . '.' . $field;
            $fees[] = [
                'key'           => $fee['key'],
                'name'          => $fee['name'],
                'mode'          => $fee['mode'],
                'path'          => $path,
                'profile_value' => $fee[$field],
                'current'       => $state->changeInEffect($path) ?? ['value' => $fee[$field], 'effective_from' => null],
                'upcoming'      => array_values(array_filter(
                    $changes,
                    static fn (array $change): bool => $change['path'] === $path && $change['state'] === 'upcoming',
                )),
                'overridden_by' => $this->overriddenBy($customers, $names, $path),
            ];
        }

        return ['today' => $state->today, 'subtotal_fees' => $fees, 'changes' => $changes];
    }

    /**
     * @return array<string, mixed>
     */
    public function add(string $profileKey, string $path, mixed $value, string $effectiveFrom, ?string $userUuid): array
    {
        $state = ProfileState::of($this->company, $profileKey, $this->context);
        if ($this->allowed($state->normalized, $path) === false) {
            throw AdminRefused::invalid('change-not-allowed', ['path' => $path], 'path');
        }
        try {
            $change = $this->store->addDatedChange($state->profile, $path, $value, $effectiveFrom, $userUuid);
        } catch (StoreRefused $refused) {
            throw AdminRefused::fromStore($refused);
        }

        return $this->change($change, $state->today, $this->names->users([$userUuid]));
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(string $uuid, ?string $userUuid): array
    {
        $change = DatedChange::firstOf(DatedChange::ofCompany($this->company)->whereKey($uuid))
            ?? throw AdminRefused::notFound();
        if (preg_match(self::PATH, $change->path) !== 1) {
            throw AdminRefused::invalid('change-not-allowed', ['path' => $change->path], 'path');
        }
        try {
            $cancelled = $this->store->cancelDatedChange($change, $userUuid);
        } catch (StoreRefused $refused) {
            throw AdminRefused::fromStore($refused);
        }
        $profile = Profile::firstOf(Profile::ofCompany($this->company)->whereKey($cancelled->profile_uuid))
            ?? throw AdminRefused::notFound();
        $today   = (new ProfileState($this->context, $profile))->today;

        return $this->change($cancelled, $today, $this->names->users([$cancelled->added_by_uuid, $userUuid]));
    }

    /**
     * Only a subtotal fee's value for its mode: its percentage, or its flat amount.
     *
     * @param array<string, mixed> $normalized
     */
    private function allowed(array $normalized, string $path): bool
    {
        if (preg_match(self::PATH, $path, $match) !== 1) {
            return false;
        }
        foreach (R::items($normalized['subtotal_fees'] ?? []) as $fee) {
            if ($fee['key'] === $match[1]) {
                return ($fee['mode'] === 'percentage') === ($match[2] === 'percentage');
            }
        }

        return false;
    }

    /**
     * @param list<Customer>        $customers
     * @param array<string, string> $names
     *
     * @return list<array{uuid: string, name: string, value: mixed}>
     */
    private function overriddenBy(array $customers, array $names, string $path): array
    {
        $found = [];
        foreach ($customers as $customer) {
            if (array_key_exists($path, $customer->overrides ?? [])) {
                $found[] = [
                    'uuid'  => $customer->uuid,
                    'name'  => $names[$customer->customer_uuid] ?? '',
                    'value' => ($customer->overrides ?? [])[$path],
                ];
            }
        }
        usort($found, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $found;
    }

    /**
     * @param array<string, string> $users
     *
     * @return array<string, mixed>
     */
    private function change(DatedChange $change, string $today, array $users): array
    {
        $state = match (true) {
            $change->live === null            => 'cancelled',
            $change->problems !== null        => 'stale',
            $change->effective_from <= $today => 'in-effect',
            default                           => 'upcoming',
        };

        return [
            'uuid'           => $change->uuid,
            'path'           => $change->path,
            'value'          => $change->value,
            'effective_from' => $change->effective_from,
            'state'          => $state,
            'added_at'       => self::time($change->added_at),
            'added_by'       => Names::person($change->added_by_uuid, $users),
            'cancelled_at'   => self::time($change->cancelled_at),
            'cancelled_by'   => Names::person($change->cancelled_by_uuid, $users),
            'problems'       => $change->problems,
        ];
    }

    /**
     * The store writes times in UTC without a zone (StoreContext::timestamp); the console reads
     * ISO times, as Profiles sends them.
     */
    private static function time(?string $stored): ?string
    {
        if ($stored === null) {
            return null;
        }
        $time = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $stored, new \DateTimeZone('UTC'));

        return $time === false ? $stored : $time->format(\DateTimeInterface::ATOM);
    }
}
