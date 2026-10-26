<?php

namespace Fleetbase\EnhancedPricing\Admin;

use Fleetbase\EnhancedPricing\Models\DatedChange;
use Fleetbase\EnhancedPricing\Models\Profile;
use Fleetbase\EnhancedPricing\Profile\OverridePaths;
use Fleetbase\EnhancedPricing\Store\StoreContext;
use Fleetbase\EnhancedPricing\Store\StoreRefused;

/**
 * A profile as the admin page reads it today: its current version normalized, today's date in its
 * scheduling timezone, its live dated changes, and the global value of any override path.
 */
final class ProfileState
{
    /** @var array<string, mixed> */
    public readonly array $normalized;

    /** @var array<string, mixed> */
    public readonly array $definition;

    public readonly string $today;

    /** @var list<DatedChange> live changes, oldest date first */
    public readonly array $live;

    public function __construct(StoreContext $context, public readonly Profile $profile)
    {
        try {
            $current = $context->current($profile);
        } catch (StoreRefused) {
            throw AdminRefused::invalid('profile-unreadable');
        }
        $this->definition = $current['definition'];
        $this->normalized = $current['normalized'];
        $this->today      = $context->today($this->normalized);
        $live             = DatedChange::ofCompany($profile->company_uuid)
            ->where('profile_uuid', $profile->uuid)->where('live', 1);
        $live->getQuery()->orderBy('effective_from');
        $this->live       = DatedChange::listOf($live);
    }

    public static function load(string $company, string $key, StoreContext $context): self
    {
        $profile = Profile::firstOf(Profile::ofCompany($company)->where('key', $key)) ?? throw AdminRefused::notFound();

        return new self($context, $profile);
    }

    /**
     * The profile's own value at an override path; null when the path names nothing, and for
     * allowed_service_rates, whose global value is "every service rate".
     */
    public function globalValue(string $path): mixed
    {
        $found = OverridePaths::find($this->normalized, $path);
        if ($found === null || $found['pointer'] === null) {
            return null;
        }
        $value = $this->normalized;
        foreach ($found['pointer'] as $step) {
            if (is_array($value) === false || array_key_exists($step, $value) === false) {
                return null;
            }
            $value = $value[$step];
        }

        return $value;
    }

    /**
     * The dated change in effect today at a path: the latest live one on or before today that
     * still fits the profile. Stale changes are ignored, as pricing ignores them (decision 10.5).
     *
     * @return array{value: mixed, effective_from: string}|null
     */
    public function changeInEffect(string $path): ?array
    {
        $found = null;
        foreach ($this->live as $change) {
            if ($change->path === $path && $change->problems === null && $change->effective_from <= $this->today) {
                $found = ['value' => $change->value, 'effective_from' => $change->effective_from];
            }
        }

        return $found;
    }
}
