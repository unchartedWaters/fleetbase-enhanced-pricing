<?php

namespace Fleetbase\EnhancedPricing\Admin;

use Fleetbase\EnhancedPricing\Models\Customer;
use Fleetbase\EnhancedPricing\Models\Profile;
use Fleetbase\EnhancedPricing\Models\ProfileVersion;
use Fleetbase\EnhancedPricing\Store\PricingStore;
use Fleetbase\EnhancedPricing\Store\StoreRefused;

/**
 * The admin page's profiles: the list, any version, and the editor's create and save. Every write
 * goes through PricingStore, so the editor runs the same checks as a load.
 */
final class Profiles
{
    private readonly PricingStore $store;

    private readonly Names $names;

    /**
     * @param (callable(): \DateTimeImmutable)|null $clock
     */
    public function __construct(private readonly string $company, ?callable $clock = null)
    {
        $this->store = new PricingStore($company, $clock);
        $this->names = new Names($company);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        $query     = Profile::ofCompany($this->company);
        $query->getQuery()->orderBy('name')->orderBy('key');
        $profiles  = Profile::listOf($query);
        $versions  = [];
        foreach ($profiles as $profile) {
            $version = $profile->currentVersion();
            if ($version !== null) {
                $versions[$profile->uuid] = $version;
            }
        }
        $customers = Counts::by(Customer::ofCompany($this->company), 'profile_uuid');
        $names     = $this->names->users(array_values(array_map(
            static fn (ProfileVersion $version): ?string => $version->created_by_uuid,
            $versions,
        )));

        return array_map(fn (Profile $profile): array => $this->summary($profile) + [
            'saved_at'  => self::time($versions[$profile->uuid] ?? null),
            'saved_by'  => Names::person(($versions[$profile->uuid] ?? null)?->created_by_uuid, $names),
            'customers' => $customers[$profile->uuid] ?? 0,
        ], $profiles);
    }

    /**
     * One version of a profile, the current one by default, with every version's number, saver
     * and time.
     *
     * @return array<string, mixed>
     */
    public function show(string $key, ?int $number = null): array
    {
        $profile  = $this->profile($key);
        $query    = ProfileVersion::ofCompany($this->company)->where('profile_uuid', $profile->uuid);
        $query->getQuery()->orderByDesc('version');
        $versions = ProfileVersion::listOf($query);
        $number ??= $profile->current_version;
        $shown    = null;
        foreach ($versions as $version) {
            if ($version->version === $number) {
                $shown = $version;
            }
        }
        if ($shown === null) {
            throw AdminRefused::notFound();
        }
        $names = $this->names->users(
            array_map(static fn (ProfileVersion $version) => $version->created_by_uuid, $versions),
        );

        return $this->summary($profile) + [
            'version'  => self::version($shown, $names) + ['definition' => $shown->definition],
            'versions' => array_map(
                static fn (ProfileVersion $version): array => self::version($version, $names),
                $versions,
            ),
        ];
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return array<string, mixed>
     */
    public function create(string $key, string $name, array $definition, ?string $userUuid): array
    {
        try {
            $this->store->createProfile($key, $name, $definition, $userUuid);
        } catch (StoreRefused $refused) {
            throw AdminRefused::fromStore($refused);
        }

        return $this->show($key);
    }

    /**
     * Saves a new version. $builtOn is the version the editor opened; null replaces whatever is
     * newest. $acceptStale lists the stale entries the person was shown and accepted.
     *
     * @param array<string, mixed> $definition
     * @param list<string>         $acceptStale
     *
     * @return array<string, mixed>
     */
    public function save(string $key, array $definition, ?int $builtOn, array $acceptStale, ?string $userUuid): array
    {
        $profile = $this->profile($key);
        try {
            $this->store->addVersion($profile, $definition, $userUuid, $builtOn, $acceptStale);
        } catch (StoreRefused $refused) {
            throw $this->refusal($profile, $refused);
        }

        return $this->show($key);
    }

    private function profile(string $key): Profile
    {
        return Profile::firstOf(Profile::ofCompany($this->company)->where('key', $key))
            ?? throw AdminRefused::notFound();
    }

    private function refusal(Profile $profile, StoreRefused $refused): AdminRefused
    {
        if ($refused->stale !== []) {
            return AdminRefused::problems($refused->problems, 'stale', 409)
                ->with(['stale' => $this->stale($refused->stale)]);
        }
        $admin = AdminRefused::fromStore($refused);
        if ($admin->body['refused'] === 'outdated') {
            $newer = $profile->refresh()->currentVersion();
            $names = $this->names->users([$newer?->created_by_uuid]);

            return $admin->with(['newer' => $newer === null ? null : self::version($newer, $names)]);
        }

        return $admin;
    }

    /**
     * The stale entries, each named so a person can tell which it is: a customer by name, a dated
     * change by its path and date, a row by its customer, card and key.
     *
     * @param list<array{kind: string, uuid: string, problems: list<\Fleetbase\EnhancedPricing\Profile\Problem>}> $stale
     *
     * @return list<array<string, mixed>>
     */
    private function stale(array $stale): array
    {
        $labels = (new StaleLabels($this->company, $this->names))->for($stale);

        return array_map(static fn (array $entry): array => [
            'kind'     => $entry['kind'],
            'uuid'     => $entry['uuid'],
            'label'    => $labels[$entry['uuid']] ?? '',
            'problems' => array_map(static fn ($problem): array => $problem->toArray(), $entry['problems']),
        ], $stale);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Profile $profile): array
    {
        return [
            'key'             => $profile->key,
            'name'            => $profile->name,
            'currency'        => $profile->currency,
            'distance_unit'   => $profile->distance_unit,
            'current_version' => $profile->current_version,
        ];
    }

    /**
     * @param array<string, string> $names
     *
     * @return array{number: int, saved_at: string|null, saved_by: array{uuid: string, name: string}|null}
     */
    private static function version(ProfileVersion $version, array $names): array
    {
        return [
            'number'   => $version->version,
            'saved_at' => self::time($version),
            'saved_by' => Names::person($version->created_by_uuid, $names),
        ];
    }

    private static function time(?ProfileVersion $version): ?string
    {
        $time = $version?->getAttribute('created_at');
        if ($time instanceof \DateTimeInterface) {
            return $time->format(\DateTimeInterface::ATOM);
        }

        return is_string($time) ? $time : null;
    }
}
