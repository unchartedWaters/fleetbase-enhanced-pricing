<?php

namespace Fleetbase\EnhancedPricing\Profile;

use Fleetbase\EnhancedPricing\Profile\ProfileReferences as R;
use Fleetbase\EnhancedPricing\Profile\Schema as S;
use Fleetbase\EnhancedPricing\Support\Exact;

/**
 * The checks that depend on a fee's or subtotal fee's kind: how it may charge, and the fields
 * each kind needs or refuses. ProfileReferences runs them after the checks every rule shares.
 */
final class RuleKindReferences
{
    /**
     * @param array<string, mixed> $profile a profile whose shape has no problems
     */
    public function __construct(private readonly S $schema, private readonly array $profile)
    {
    }

    /**
     * @param array<string, mixed> $fee
     */
    public function fee(array $fee, string $path): void
    {
        match ($fee['kind']) {
            'peak_hours' => $this->peakHours($fee, $path),
            'parcel'     => $this->parcel($fee, $path),
            default      => $this->additional($fee, $path),
        };
    }

    /**
     * @param array<string, mixed> $fee
     */
    private function peakHours(array $fee, string $path): void
    {
        $this->charge($fee, ['per_entity', 'per_order'], $path);
        if ($fee['window'] === null && $fee['weekdays'] === null && $fee['holidays'] === null) {
            $this->schema->add(Problem::atPath($path, 'needs-one-of', ['fields' => 'window, weekdays, holidays']));
        }
        $window = $fee['window'];
        if (is_array($window) && $window['from'] === $window['to']) {
            $this->schema->add(Problem::atPath("$path.window.to", 'window-empty'));
        }
        $holidays = $fee['holidays'];
        if (is_string($holidays) && array_key_exists($holidays, R::map($this->profile['holiday_lists'])) === false) {
            $this->schema->add(Problem::atPath(
                "$path.holidays",
                'unknown-reference',
                ['kind' => 'holiday_list', 'key' => $holidays],
            ));
        }
    }

    /**
     * @param array<string, mixed> $fee
     */
    private function parcel(array $fee, string $path): void
    {
        $this->charge($fee, $fee['scope'] === 'entity' ? ['per_entity', 'per_unit'] : ['per_order', 'per_unit'], $path);
        $weight = $fee['basis'] === 'weight';
        $units  = $weight ? ProfileShapes::WEIGHT_UNITS : ProfileShapes::VOLUME_UNITS;
        if (in_array($fee['unit'], $units, true) === false) {
            $this->schema->add(Problem::atPath("$path.unit", 'not-one-of', ['choices' => implode(', ', $units)]));
        }
        $this->measure($fee, $weight, $path);
        $previous = null;
        foreach (R::items($fee['thresholds']) as $index => $threshold) {
            $over = Exact::rational(R::text($threshold['over']));
            if ($previous !== null && $over->isLessThanOrEqualTo($previous)) {
                $this->schema->add(Problem::atPath("$path.thresholds.$index.over", 'not-increasing'));
            }
            $previous = $over;
        }
    }

    /**
     * A weight fee needs a measure, and a divisor for dimensional weight; a volume fee has neither.
     *
     * @param array<string, mixed> $fee
     */
    private function measure(array $fee, bool $weight, string $path): void
    {
        $measure = $fee['measure'];
        if ($weight && $measure === null) {
            $this->schema->add(Problem::atPath("$path.measure", 'missing-field', ['field' => 'measure']));
        }
        if ($weight === false && $measure !== null) {
            $this->schema->add(Problem::atPath("$path.measure", 'measure-not-allowed'));
        }
        $needsDivisor = in_array($measure, ['dimensional', 'greater'], true);
        if ($needsDivisor && $fee['divisor'] === null) {
            $this->schema->add(Problem::atPath("$path.divisor", 'missing-field', ['field' => 'divisor']));
        }
        if ($needsDivisor === false && $fee['divisor'] !== null) {
            $this->schema->add(Problem::atPath("$path.divisor", 'divisor-not-allowed'));
        }
    }

    /**
     * @param array<string, mixed> $fee
     */
    private function additional(array $fee, string $path): void
    {
        $this->charge($fee, ['per_entity', 'per_order', 'per_unit'], $path);
        $attribute = null;
        foreach (R::items($this->profile['attributes']) as $candidate) {
            if ($candidate['key'] === $fee['attribute']) {
                $attribute = $candidate;
            }
        }
        if ($attribute === null) {
            $this->schema->add(Problem::atPath(
                "$path.attribute",
                'unknown-reference',
                ['kind' => 'attribute', 'key' => R::text($fee['attribute'])],
            ));

            return;
        }
        if ($attribute['type'] === 'choice') {
            $choices = array_map(
                static fn (array $choice): string => R::text($choice['key']),
                R::items($attribute['choices']),
            );
            if ($fee['equals'] === null) {
                $this->schema->add(Problem::atPath("$path.equals", 'missing-field', ['field' => 'equals']));
            } elseif (in_array($fee['equals'], $choices, true) === false) {
                $this->schema->add(Problem::atPath(
                    "$path.equals",
                    'unknown-reference',
                    ['kind' => 'choice', 'key' => R::text($fee['equals'])],
                ));
            }
        } elseif ($fee['equals'] !== null) {
            $this->schema->add(Problem::atPath("$path.equals", 'equals-not-allowed'));
        }
        if ($fee['charge'] === 'per_unit' && $attribute['type'] !== 'number') {
            $this->schema->add(Problem::atPath("$path.charge", 'per-unit-needs-number'));
        }
    }

    /**
     * @param array<string, mixed> $subtotal
     */
    public function subtotal(array $subtotal, string $path): void
    {
        $percentage = $subtotal['mode'] === 'percentage';
        if ($percentage && $subtotal['amount'] !== null) {
            $this->schema->add(Problem::atPath("$path.amount", 'not-allowed-for-mode', ['mode' => 'percentage']));
        }
        if ($percentage === false && $subtotal['percentage'] !== null) {
            $this->schema->add(Problem::atPath("$path.percentage", 'not-allowed-for-mode', ['mode' => 'flat']));
        }
        if ($percentage && $subtotal['charge'] !== 'per_order') {
            $this->schema->add(Problem::atPath("$path.charge", 'not-one-of', ['choices' => 'per_order']));
        }
    }

    /**
     * @param array<string, mixed> $rule
     * @param list<string>         $allowed
     */
    private function charge(array $rule, array $allowed, string $path): void
    {
        if (in_array($rule['charge'], $allowed, true) === false) {
            $this->schema->add(Problem::atPath("$path.charge", 'not-one-of', ['choices' => implode(', ', $allowed)]));
        }
    }

    /**
     * @param array<string, mixed> $charge
     */
    public function manualCharge(array $charge, string $path): void
    {
        if (($charge['amount'] === null) === ($charge['entered'] === null)) {
            $this->schema->add(Problem::atPath($path, 'needs-exactly-one', ['fields' => 'amount, entered']));
        }
        if ($charge['entered'] !== null) {
            if ($charge['quantity'] !== null) {
                $this->schema->add(Problem::atPath("$path.quantity", 'entered-takes-no-quantity'));
            }
            $this->limits(R::map($charge['entered']), 'minimum', 'maximum', "$path.entered");
        }
        $quantity = $charge['quantity'];
        if (is_array($quantity)) {
            $steps = Exact::rational(R::text($quantity['maximum']))->dividedBy(R::text($quantity['step']));
            if (Exact::isWhole($steps) === false) {
                $this->schema->add(Problem::atPath("$path.quantity.maximum", 'not-a-step'));
            }
        }
        $from  = $charge['effective_from'];
        $until = $charge['effective_until'];
        if (is_string($from) && is_string($until) && $until < $from) {
            $this->schema->add(Problem::atPath("$path.effective_until", 'until-before-from'));
        }
    }

    /**
     * Reports a minimum above its maximum, for money or numbers.
     *
     * @param array<string, mixed> $values
     */
    public function limits(array $values, string $minimum, string $maximum, string $path): void
    {
        $low  = $values[$minimum] ?? null;
        $high = $values[$maximum] ?? null;
        if (
            (is_int($low) || is_string($low))
            && (is_int($high) || is_string($high))
            && Exact::rational($low)->isGreaterThan(Exact::rational($high))
        ) {
            $this->schema->add(Problem::atPath(S::join($path, $minimum), 'minimum-above-maximum'));
        }
    }
}
