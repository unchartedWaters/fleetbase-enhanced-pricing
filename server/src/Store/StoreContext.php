<?php

namespace Fleetbase\EnhancedPricing\Store;

use Fleetbase\EnhancedPricing\Models\CrossCompanyReference;
use Fleetbase\EnhancedPricing\Models\PricingModel;
use Fleetbase\EnhancedPricing\Models\Profile;
use Fleetbase\EnhancedPricing\Models\ProfileVersion;
use Fleetbase\EnhancedPricing\Profile\InvalidProfile;
use Fleetbase\EnhancedPricing\Profile\ProfileReferences as R;
use Fleetbase\EnhancedPricing\Profile\ProfileValidator;
use Illuminate\Database\ConnectionInterface;

/**
 * What every part of the store shares: the company, the clock, the profile lock and the checks
 * that apply to every write.
 */
final class StoreContext
{
    public const MAX_META_BYTES = 4096;

    /** The profile field that gives the timezone in which "today" is judged for dated changes. */
    public const TIMEZONE_FIELD = 'timezone';

    /** @var \Closure(): \DateTimeImmutable */
    private readonly \Closure $clock;

    /**
     * @param (\Closure(): \DateTimeImmutable)|null $clock now, for tests; the system clock otherwise
     */
    public function __construct(public readonly string $company, ?\Closure $clock = null)
    {
        $this->clock = $clock ?? static fn (): \DateTimeImmutable => new \DateTimeImmutable('now');
    }

    /**
     * @throws CrossCompanyReference
     */
    public function own(PricingModel $model): void
    {
        if ($model->company_uuid !== $this->company) {
            throw new CrossCompanyReference($model::class, 'company_uuid');
        }
    }

    /**
     * The profile row, locked until the transaction ends.
     */
    public function lock(Profile $profile): Profile
    {
        return $this->fresh($profile, true);
    }

    /**
     * A fresh copy of the profile row, so a caller's older copy never decides the current version.
     */
    public function fresh(Profile $profile, bool $lock = false): Profile
    {
        $query = Profile::ofCompany($this->company)->whereKey($profile->uuid);
        if ($lock) {
            $query->getQuery()->lockForUpdate();
        }
        $copy = Profile::firstOf($query);
        if ($copy === null) {
            throw new CrossCompanyReference(Profile::class, 'uuid');
        }

        return $copy;
    }

    /**
     * The profile's current version, as saved and normalized.
     *
     * @return array{version: int, definition: array<string, mixed>, normalized: array<string, mixed>}
     */
    public function current(Profile $profile): array
    {
        $version = $profile->currentVersion();
        if ($version === null) {
            throw new CrossCompanyReference(ProfileVersion::class, 'version');
        }

        return [
            'version'    => $version->version,
            'definition' => $version->definition,
            'normalized' => $this->normalize($version->definition),
        ];
    }

    /**
     * Today in the profile's scheduling timezone, as Y-m-d.
     *
     * @param array<string, mixed> $normalized
     */
    public function today(array $normalized): string
    {
        $zone = new \DateTimeZone(R::text($normalized[self::TIMEZONE_FIELD] ?? null));

        return $this->now()->setTimezone($zone)->format('Y-m-d');
    }

    public function now(): \DateTimeImmutable
    {
        return ($this->clock)()->setTimezone(new \DateTimeZone('UTC'));
    }

    public function timestamp(): string
    {
        return $this->now()->format('Y-m-d H:i:s');
    }

    /**
     * @param array<mixed>|null $meta
     *
     * @throws StoreRefused
     */
    public function checkMeta(?array $meta, string $path = 'meta'): void
    {
        if ($meta !== null && strlen((string) json_encode($meta)) > self::MAX_META_BYTES) {
            throw StoreRefused::refuse('meta-too-large', ['max' => self::MAX_META_BYTES], $path);
        }
    }

    /**
     * @template T
     *
     * @param \Closure(): T $work
     *
     * @return T
     */
    public function transaction(\Closure $work): mixed
    {
        /** @var T $result */
        $result = $this->connection()->transaction($work);

        return $result;
    }

    public function connection(): ConnectionInterface
    {
        return (new Profile())->getConnection();
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return array<string, mixed>
     *
     * @throws StoreRefused
     */
    public function normalize(array $definition): array
    {
        try {
            return (new ProfileValidator())->normalize($definition);
        } catch (InvalidProfile $invalid) {
            throw new StoreRefused($invalid->problems);
        }
    }
}
