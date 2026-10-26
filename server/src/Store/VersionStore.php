<?php

namespace Fleetbase\EnhancedPricing\Store;

use Fleetbase\EnhancedPricing\Models\Customer;
use Fleetbase\EnhancedPricing\Models\DatedChange;
use Fleetbase\EnhancedPricing\Models\Profile;
use Fleetbase\EnhancedPricing\Models\ProfileVersion;
use Fleetbase\EnhancedPricing\Profile\ProfileReferences as R;

/**
 * Profiles and their versions, for PricingStore. A new version is checked against every stored
 * entry that depends on the profile, and each entry records the result.
 *
 * @phpstan-import-type Entry from StaleCheck
 */
final class VersionStore
{
    public function __construct(private readonly StoreContext $context)
    {
    }

    /**
     * @param array<string, mixed> $definition the first version, as saved
     *
     * @throws StoreRefused
     */
    public function create(string $key, string $name, array $definition, ?string $userUuid = null): Profile
    {
        $normalized = $this->context->normalize($definition);
        if (Profile::ofCompany($this->context->company)->where('key', $key)->exists()) {
            throw StoreRefused::refuse('profile-exists', ['key' => $key], 'key');
        }

        return $this->context->transaction(function () use ($key, $name, $definition, $normalized, $userUuid): Profile {
            $profile = Profile::createWith([
                'company_uuid'  => $this->context->company,
                'key'           => $key,
                'name'          => $name,
                'currency'      => R::text($normalized['currency']),
                'distance_unit' => R::text($normalized['distance_unit']),
            ]);
            $this->insertVersion($profile, 1, $definition, $userUuid);

            return $profile;
        });
    }

    /**
     * Saves a new version. It is refused when it was built on an older version, changes a unit that
     * stored values depend on, or leaves stored entries that no longer fit, unless $acceptStale:
     * true accepts any, and a list of uuids accepts only stale entries that are all in it, so a
     * person accepts only the entries they were shown.
     * Every entry checked records the version and its problems.
     *
     * @param array<string, mixed> $definition  the new version, as saved
     * @param bool|list<string>    $acceptStale
     *
     * @throws StoreRefused
     */
    public function add(
        Profile $profile,
        array $definition,
        ?string $userUuid = null,
        ?int $builtOn = null,
        bool|array $acceptStale = false,
    ): ProfileVersion {
        $this->context->own($profile);
        $new     = $this->context->normalize($definition);
        $current = $this->context->current($this->context->fresh($profile));
        if ($builtOn !== null && $builtOn !== $current['version']) {
            throw StoreRefused::refuse('version-outdated', ['current' => $current['version']]);
        }

        $check = new StaleCheck($this->context->company, $definition, $new);
        $first = $check->run($profile);

        $save = function () use ($profile, $definition, $userUuid, $acceptStale, $current, $new, $check, $first) {
            $locked = $this->context->lock($profile);
            if ($locked->current_version !== $current['version']) {
                throw StoreRefused::refuse('version-outdated', ['current' => $locked->current_version]);
            }
            $this->checkUnits($locked, $current['normalized'], $new);
            $entries = $check->run($locked, $first);
            $stale   = array_values(array_filter($entries, static fn (array $entry) => $entry['problems'] !== []));
            if ($stale !== [] && self::accepts($acceptStale, $stale) === false) {
                $list = array_map(static fn (array $entry) => [
                    'kind'     => $entry['kind'],
                    'uuid'     => $entry['uuid'],
                    'problems' => $entry['problems'],
                ], $stale);

                throw new StoreRefused([StoreRefused::problem('stale-entries', ['count' => count($stale)])], $list);
            }
            $version = $this->insertVersion($locked, $current['version'] + 1, $definition, $userUuid);
            $this->record($entries, $version->version);
            $profile->setRawAttributes($locked->getAttributes(), true);

            return $version;
        };

        return $this->context->transaction($save);
    }

    /**
     * @param bool|list<string>              $accepted
     * @param list<array{uuid: string, ...}> $stale
     */
    private static function accepts(bool|array $accepted, array $stale): bool
    {
        if (is_bool($accepted)) {
            return $accepted;
        }

        return array_diff(array_column($stale, 'uuid'), $accepted) === [];
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     *
     * @throws StoreRefused
     */
    private function checkUnits(Profile $profile, array $old, array $new): void
    {
        $changes = DatedChange::listOf(
            DatedChange::ofCompany($this->context->company)->where('profile_uuid', $profile->uuid)->where('live', 1),
        );
        $paths     = array_map(static fn (DatedChange $change) => $change->path, $changes);
        $customers = Customer::ofCompany($this->context->company)->where('profile_uuid', $profile->uuid);
        foreach (Customer::listOf($customers) as $customer) {
            $paths = [...$paths, ...array_map('strval', array_keys($customer->overrides ?? []))];
        }
        $problems = [
            ...UnitCheck::fixed($profile, $new),
            ...UnitCheck::named($old, $new, $paths),
            ...UnitCheck::inEffect($old, $new, $changes, $this->context->today($new)),
        ];
        if ($problems !== []) {
            throw new StoreRefused($problems);
        }
    }

    /**
     * Records each entry's check: the version and its problems, NULL when it fits. Entries that fit
     * are updated together, in chunks.
     *
     * @param array<string, Entry> $entries
     */
    private function record(array $entries, int $version): void
    {
        $fitting = [];
        foreach ($entries as $entry) {
            if ($entry['problems'] === []) {
                $fitting[$entry['class']][] = $entry['uuid'];
                continue;
            }
            $entry['class']::query()->whereKey($entry['uuid'])->update([
                'checked_version' => $version,
                'problems'        => json_encode(StoreRefused::stored($entry['problems'])),
            ]);
        }
        foreach ($fitting as $class => $uuids) {
            foreach (array_chunk($uuids, 500) as $chunk) {
                $class::query()->whereKey($chunk)->update(['checked_version' => $version, 'problems' => null]);
            }
        }
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function insertVersion(Profile $profile, int $number, array $definition, ?string $userUuid): ProfileVersion
    {
        $version = ProfileVersion::createWith([
            'company_uuid'    => $this->context->company,
            'profile_uuid'    => $profile->uuid,
            'version'         => $number,
            'definition'      => $definition,
            'created_by_uuid' => $userUuid,
        ]);
        $profile->current_version = $number;
        $profile->save();

        return $version;
    }
}
