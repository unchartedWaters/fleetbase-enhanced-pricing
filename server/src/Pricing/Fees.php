<?php

namespace Fleetbase\EnhancedPricing\Pricing;

use Brick\Math\BigRational;
use Fleetbase\EnhancedPricing\Profile\ProfileReferences as R;
use Fleetbase\EnhancedPricing\Support\Exact;

/**
 * Peak hours, parcel and additional fees, in the profile's order, after the service fee and
 * manual charges. Each sees only the lines made before it.
 */
final class Fees
{
    private RuleSupport $support;

    public function __construct(private readonly OrderContext $order)
    {
        $this->support = new RuleSupport($order);
    }

    public function price(): void
    {
        foreach (R::items($this->order->profile['fees']) as $fee) {
            if ($fee['enabled'] !== true || $this->order->inEffect($fee) === false) {
                continue;
            }
            $drafts = match ($fee['kind']) {
                'peak_hours' => $this->peakHours($fee),
                'parcel'     => $this->parcel($fee),
                default      => $this->additional($fee),
            };
            $this->order->book->add($this->support->limits($fee, $drafts));
        }
    }

    /**
     * @param array<string, mixed> $fee
     *
     * @return list<array{product: string, name: string, quantity: int, unit: int, source: string}>
     */
    private function peakHours(array $fee): array
    {
        if ($this->inPeakHours($fee) === false) {
            return [];
        }

        return $this->charged($fee, $fee['charge'] === 'per_entity' ? count($this->support->counted($fee)) : null);
    }

    /**
     * @param array<string, mixed> $fee
     */
    private function inPeakHours(array $fee): bool
    {
        $time   = $this->order->localPickup->format('H:i');
        $window = $fee['window'];
        if (is_array($window)) {
            $from = R::text($window['from']);
            $to   = R::text($window['to']);
            $in   = $from < $to ? ($time >= $from && $time < $to) : ($time >= $from || $time < $to);
            if ($in === false) {
                return false;
            }
        }
        $weekdays = $fee['weekdays'];
        $holidays = $fee['holidays'];
        if ($weekdays === null && $holidays === null) {
            return true;
        }
        $weekday = (int) $this->order->localPickup->format('N');
        $dates   = is_string($holidays) ? R::strings(R::map($this->order->profile['holiday_lists'])[$holidays] ?? []) : [];

        return (is_array($weekdays) && in_array($weekday, $weekdays, true)) || in_array($this->order->date(), $dates, true);
    }

    /**
     * @param array<string, mixed> $fee
     *
     * @return list<array{product: string, name: string, quantity: int, unit: int, source: string}>
     */
    private function parcel(array $fee): array
    {
        $thresholds = [];
        foreach (R::items($fee['thresholds']) as $threshold) {
            $thresholds[] = ['over' => Exact::of(R::text($threshold['over'])), 'amount' => is_int($threshold['amount']) ? $threshold['amount'] : 0];
        }
        $measured = [];
        foreach ($this->support->counted($fee) as $entity) {
            $measure = $this->measure($fee, $entity);
            if ($measure === null) {
                $this->order->note('entity-not-measured', ['entity' => $entity->number, 'rule' => R::text($fee['key'])]);
                continue;
            }
            $measured[] = $measure;
        }
        if ($measured === []) {
            return [];
        }
        $unit = R::text($fee['unit']);
        if ($fee['scope'] === 'order') {
            $total    = array_reduce($measured, static fn (BigRational $sum, BigRational $measure): BigRational => $sum->plus($measure), BigRational::zero());
            $measured = [$total];
            if ($fee['charge'] === 'per_order') {
                $amount = self::highest($thresholds, $total);

                return $amount === null ? [] : [$this->support->draft($fee, 1, $amount)];
            }
        }
        if ($fee['charge'] === 'per_entity') {
            $groups = [];
            foreach ($measured as $measure) {
                $amount = self::highest($thresholds, $measure);
                if ($amount !== null) {
                    $groups[$amount] = ($groups[$amount] ?? 0) + 1;
                }
            }

            return array_map(fn (int $amount, int $count): array => $this->support->draft($fee, $count, $amount), array_keys($groups), array_values($groups));
        }
        $drafts = [];
        foreach ($measured as $measure) {
            if ($measure->isGreaterThan($thresholds[0]['over'])) {
                $excess   = $measure->minus($thresholds[0]['over']);
                $drafts[] = $this->support->draft($fee, 1, Exact::round(self::bands($thresholds, $measure)), sprintf('%s (%s %s)', R::text($fee['name']), Exact::display($excess), $unit));
            }
        }

        return $drafts;
    }

    /**
     * The entity's measurement in the fee's unit, or null when it lacks what the fee needs.
     *
     * @param array<string, mixed> $fee
     */
    private function measure(array $fee, PricedEntity $entity): ?BigRational
    {
        $unit = R::text($fee['unit']);
        if ($fee['basis'] === 'volume') {
            return $entity->cubicCentimetres === null ? null : Units::fromCubicCentimetres($entity->cubicCentimetres, $unit);
        }
        $actual      = $entity->kilograms;
        $dimensional = $entity->cubicCentimetres === null || $fee['divisor'] === null ? null : $entity->cubicCentimetres->dividedBy(R::text($fee['divisor']));
        $kilograms   = match ($fee['measure']) {
            'actual'      => $actual,
            'dimensional' => $dimensional,
            default       => $actual === null ? $dimensional : ($dimensional === null || $actual->isGreaterThanOrEqualTo($dimensional) ? $actual : $dimensional),
        };

        return $kilograms === null ? null : Units::fromKilograms($kilograms, $unit);
    }

    /**
     * The amount of the highest threshold the measurement is strictly over.
     *
     * @param list<array{over: BigRational, amount: int}> $thresholds
     */
    private static function highest(array $thresholds, BigRational $measure): ?int
    {
        $amount = null;
        foreach ($thresholds as $threshold) {
            if ($measure->isGreaterThan($threshold['over'])) {
                $amount = $threshold['amount'];
            }
        }

        return $amount;
    }

    /**
     * Units over the first threshold, each band at its own threshold's amount, like tiers.
     *
     * @param list<array{over: BigRational, amount: int}> $thresholds
     */
    private static function bands(array $thresholds, BigRational $measure): BigRational
    {
        $total = BigRational::zero();
        foreach ($thresholds as $index => $threshold) {
            if ($measure->isLessThanOrEqualTo($threshold['over'])) {
                break;
            }
            $top   = isset($thresholds[$index + 1]) && $measure->isGreaterThan($thresholds[$index + 1]['over']) ? $thresholds[$index + 1]['over'] : $measure;
            $total = $total->plus($top->minus($threshold['over'])->multipliedBy($threshold['amount']));
        }

        return $total;
    }

    /**
     * @param array<string, mixed> $fee
     *
     * @return list<array{product: string, name: string, quantity: int, unit: int, source: string}>
     */
    private function additional(array $fee): array
    {
        $attribute = $this->order->attribute(R::text($fee['attribute'])) ?? [];
        $key       = R::text($fee['attribute']);
        $charge    = $fee['charge'];
        if ($attribute['scope'] === 'order') {
            $value = $this->order->attributes[$key] ?? null;
            if (self::matches($attribute, $fee, $value) === false) {
                return [];
            }
            if ($charge === 'per_unit' && $value instanceof BigRational) {
                return $this->perUnit($fee, $attribute, [$value]);
            }

            return $this->charged($fee, $charge === 'per_entity' ? count($this->support->counted($fee)) : null);
        }

        $matching = array_values(array_filter(
            $this->support->counted($fee),
            static fn (PricedEntity $entity): bool => self::matches($attribute, $fee, $entity->attributes[$key] ?? null),
        ));
        if ($matching === []) {
            return [];
        }
        if ($charge === 'per_unit') {
            $values = array_map(static fn (PricedEntity $entity): mixed => $entity->attributes[$key], $matching);

            return $this->perUnit($fee, $attribute, array_values(array_filter($values, static fn (mixed $value): bool => $value instanceof BigRational)));
        }

        return $this->charged($fee, $charge === 'per_entity' ? count($matching) : 1, true);
    }

    /**
     * @param array<string, mixed> $attribute
     * @param array<string, mixed> $fee
     */
    private static function matches(array $attribute, array $fee, mixed $value): bool
    {
        return match ($attribute['type']) {
            'boolean' => $value === true,
            'choice'  => $value !== null && $value === $fee['equals'],
            default   => $value instanceof BigRational && $value->isPositive(),
        };
    }

    /**
     * A line per value: the amount for each unit of it, named with the value.
     *
     * @param array<string, mixed> $fee
     * @param array<string, mixed> $attribute
     * @param list<BigRational>    $values
     *
     * @return list<array{product: string, name: string, quantity: int, unit: int, source: string}>
     */
    private function perUnit(array $fee, array $attribute, array $values): array
    {
        if ($attribute['scope'] === 'order' && $this->order->book->hasAny(R::strings($fee['applies_to'])) === false) {
            return [];
        }
        $amount = $this->support->amount($fee);
        if ($amount === null) {
            return [];
        }
        $unit   = is_string($attribute['unit'] ?? null) ? ' ' . $attribute['unit'] : '';
        $drafts = [];
        foreach ($values as $value) {
            $drafts[] = $this->support->draft($fee, 1, Exact::round($value->multipliedBy($amount)), sprintf('%s (%s%s)', R::text($fee['name']), Exact::display($value), $unit));
        }

        return $drafts;
    }

    /**
     * A per-order charge (count null: once, when the order has a line for the fee's products) or
     * a charge for a number of entities.
     *
     * @param array<string, mixed> $fee
     *
     * @return list<array{product: string, name: string, quantity: int, unit: int, source: string}>
     */
    private function charged(array $fee, ?int $count, bool $alreadyCounted = false): array
    {
        if ($count === null && $alreadyCounted === false && $this->order->book->hasAny(R::strings($fee['applies_to'])) === false) {
            return [];
        }
        $count ??= 1;
        if ($count <= 0) {
            return [];
        }
        $amount = $this->support->amount($fee);

        return $amount === null ? [] : [$this->support->draft($fee, $count, $amount)];
    }
}
