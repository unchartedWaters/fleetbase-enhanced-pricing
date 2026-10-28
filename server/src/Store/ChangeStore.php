<?php

namespace Fleetbase\EnhancedPricing\Store;

use Fleetbase\EnhancedPricing\Models\CrossCompanyReference;
use Fleetbase\EnhancedPricing\Models\DatedChange;
use Fleetbase\EnhancedPricing\Models\Profile;
use Fleetbase\EnhancedPricing\Profile\ChangeValidator;

/**
 * Dated changes, for PricingStore. A change sent again is matched against the stored ones first,
 * so a loader rerun does nothing, and a change cancelled on the admin page is never re-added.
 */
final class ChangeStore
{
    public const MAX_YEARS_AHEAD = 2;

    public function __construct(private readonly StoreContext $context)
    {
    }

    /**
     * @throws StoreRefused
     */
    public function add(
        Profile $profile,
        string $path,
        mixed $value,
        string $effectiveFrom,
        ?string $userUuid,
        bool $acceptLargeChange = false,
    ): DatedChange {
        $this->context->own($profile);
        $add = function () use ($profile, $path, $value, $effectiveFrom, $userUuid, $acceptLargeChange): DatedChange {
            $current = $this->context->current($this->context->lock($profile));
            $today   = $this->context->today($current['normalized']);
            $live    = $this->stored($profile, $path, $effectiveFrom);
            if ($live !== null && Canonical::json($live->value) === Canonical::json($value)) {
                return $live;
            }
            if ($live !== null && $live->effective_from <= $today) {
                throw StoreRefused::refuse('change-in-effect', ['date' => $effectiveFrom], 'effective_from');
            }

            $this->check($profile, $current['definition'], $live, $path, $value, $effectiveFrom);
            $this->checkDate($effectiveFrom, $today);
            if ($acceptLargeChange === false) {
                $before = $this->before($profile, $path, $effectiveFrom);
                SlipCheck::run($current['normalized'], [$path => $value], $before);
            }
            if ($live !== null) {
                $this->markCancelled($live, $userUuid);
            }

            return DatedChange::createWith([
                'company_uuid'    => $this->context->company,
                'profile_uuid'    => $profile->uuid,
                'path'            => $path,
                'value'           => $value,
                'effective_from'  => $effectiveFrom,
                'added_by_uuid'   => $userUuid,
                'added_at'        => $this->context->timestamp(),
                'live'            => 1,
                'checked_version' => $current['version'],
            ]);
        };

        return $this->context->transaction($add);
    }

    /**
     * The value of the live change in effect before a date, if any, as path => value.
     *
     * @return array<string, mixed>
     */
    private function before(Profile $profile, string $path, string $effectiveFrom): array
    {
        $query = DatedChange::ofCompany($this->context->company)
            ->where('profile_uuid', $profile->uuid)
            ->where('path', $path)
            ->where('live', 1)
            ->where('effective_from', '<', $effectiveFrom);
        $query->getQuery()->orderBy('effective_from', 'desc');
        $change = DatedChange::firstOf($query);

        return $change === null ? [] : [$path => $change->value];
    }

    /**
     * The live change stored for the same path and date, if any. When the only ones stored were
     * cancelled, the change is refused: a cancelled change is never added again.
     *
     * @throws StoreRefused
     */
    private function stored(Profile $profile, string $path, string $effectiveFrom): ?DatedChange
    {
        $query = DatedChange::ofCompany($this->context->company)
            ->where('profile_uuid', $profile->uuid)
            ->where('path', $path)
            ->where('effective_from', $effectiveFrom);
        $stored = DatedChange::listOf($query);
        foreach ($stored as $change) {
            if ($change->live === 1) {
                return $change;
            }
        }
        if ($stored !== []) {
            throw StoreRefused::refuse('change-was-cancelled', ['date' => $effectiveFrom], 'effective_from');
        }

        return null;
    }

    /**
     * A change can be cancelled until the day before its date. Cancelling one already cancelled
     * does nothing.
     *
     * @throws StoreRefused
     */
    public function cancel(DatedChange $change, ?string $userUuid): DatedChange
    {
        $this->context->own($change);
        $profile = Profile::firstOf(Profile::ofCompany($this->context->company)->whereKey($change->profile_uuid));
        if ($profile === null) {
            throw new CrossCompanyReference(DatedChange::class, 'profile_uuid');
        }

        return $this->context->transaction(function () use ($change, $profile, $userUuid): DatedChange {
            $current = $this->context->current($this->context->lock($profile));
            $change->refresh();
            if ($change->live === null) {
                return $change;
            }
            if ($change->effective_from <= $this->context->today($current['normalized'])) {
                throw StoreRefused::refuse('change-in-effect', ['date' => $change->effective_from], 'effective_from');
            }

            return $this->markCancelled($change, $userUuid);
        });
    }

    /**
     * The new change with every live one but the change it replaces. Only the new change's own
     * problems, or one about all of them (too many), refuse it, so a stale change already stored
     * never blocks new ones.
     *
     * @param array<string, mixed> $definition the current version, as saved
     *
     * @throws StoreRefused
     */
    private function check(
        Profile $profile,
        array $definition,
        ?DatedChange $replaced,
        string $path,
        mixed $value,
        string $effectiveFrom,
    ): void {
        $live = DatedChange::ofCompany($this->context->company)
            ->where('profile_uuid', $profile->uuid)
            ->where('live', 1);
        $live->getQuery()->orderBy('effective_from');
        $list = [];
        foreach (DatedChange::listOf($live) as $change) {
            if ($change->uuid !== $replaced?->uuid) {
                $list[] = [
                    'path'           => $change->path,
                    'value'          => $change->value,
                    'effective_from' => $change->effective_from,
                ];
            }
        }
        $index  = count($list);
        $list[] = ['path' => $path, 'value' => $value, 'effective_from' => $effectiveFrom];

        $own = array_values(array_filter(
            (new ChangeValidator())->check($definition, $list),
            static fn ($problem) => $problem->path === '' || str_starts_with($problem->path . '.', "$index."),
        ));
        if ($own !== []) {
            throw new StoreRefused($own);
        }
    }

    /**
     * A new change takes effect from tomorrow at the earliest, and at most two years ahead.
     *
     * @throws StoreRefused
     */
    private function checkDate(string $effectiveFrom, string $today): void
    {
        $latest = (new \DateTimeImmutable($today))->modify('+' . self::MAX_YEARS_AHEAD . ' years')->format('Y-m-d');
        if ($effectiveFrom <= $today) {
            throw StoreRefused::refuse('change-too-soon', ['today' => $today], 'effective_from');
        }
        if ($effectiveFrom > $latest) {
            throw StoreRefused::refuse('change-too-far', ['latest' => $latest], 'effective_from');
        }
    }

    private function markCancelled(DatedChange $change, ?string $userUuid): DatedChange
    {
        $change->live              = null;
        $change->cancelled_by_uuid = $userUuid;
        $change->cancelled_at      = $this->context->timestamp();
        $change->save();

        return $change;
    }
}
