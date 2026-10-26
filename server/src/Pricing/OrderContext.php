<?php

namespace Fleetbase\EnhancedPricing\Pricing;

use Brick\Math\BigRational;
use Fleetbase\EnhancedPricing\Profile\ProfileReferences as R;
use Fleetbase\EnhancedPricing\Support\Exact;

/**
 * One order being priced: the resolved profile, the pickup's local time, the checked entities
 * and attributes, the chosen row, the lines made so far and any notes.
 */
final class OrderContext
{
    public const MAX_ENTITIES = 1000;

    public const MAX_MEASURE = '1000000';

    public readonly LineBook $book;

    /** @var list<PricedEntity> */
    public array $entities = [];

    /** @var array<string, mixed> */
    public array $attributes = [];

    /** @var list<array{key: string, params: array<string, scalar|null>}> */
    public array $notes = [];

    /** @var array<string, array<string, mixed>> */
    private array $attributeDefinitions = [];

    /**
     * @param array<string, mixed>      $profile     the resolved profile
     * @param array<string, mixed>      $serviceRate
     * @param array<string, string>     $labels
     * @param array<string, mixed>|null $row
     */
    public function __construct(
        public readonly array $profile,
        public readonly array $serviceRate,
        public readonly \DateTimeImmutable $localPickup,
        public readonly array $labels,
        public readonly ?array $row,
    ) {
        $this->book = new LineBook();
        foreach (R::items($profile['attributes']) as $attribute) {
            $this->attributeDefinitions[R::text($attribute['key'])] = $attribute;
        }
    }

    public function date(): string
    {
        return $this->localPickup->format('Y-m-d');
    }

    /**
     * @param array<string, mixed> $rule
     */
    public function inEffect(array $rule): bool
    {
        $from  = $rule['effective_from'] ?? null;
        $until = $rule['effective_until'] ?? null;

        return (is_string($from) === false || $from <= $this->date())
            && (is_string($until) === false || $until >= $this->date());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function attribute(string $key): ?array
    {
        return $this->attributeDefinitions[$key] ?? null;
    }

    /**
     * Stops pricing, naming the customer, card and row along with the details given.
     *
     * @param array<string, scalar|null> $params
     */
    public function fail(string $name, array $params = []): PricingFailed
    {
        return PricingFailed::because($name, $params + [
            'customer' => $this->labels['customer'] ?? '',
            'card'     => $this->labels['card'] ?? '',
            'row'      => $this->labels['row'] ?? '',
        ]);
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public function note(string $name, array $params): void
    {
        $this->notes[] = ['key' => 'enhanced-pricing.pricing.' . $name, 'params' => $params];
    }

    /**
     * Checks the order's attributes and entities against the profile.
     *
     * @param array<string, mixed> $attributes
     * @param list<EntityInput>    $entities
     */
    public function readInputs(array $attributes, array $entities): void
    {
        if (count($entities) > self::MAX_ENTITIES) {
            throw $this->fail('too-many-entities', ['max' => self::MAX_ENTITIES]);
        }
        $this->attributes = $this->readAttributes($attributes, 'order', 0);
        $types            = [];
        foreach (R::items($this->profile['entity_types']) as $type) {
            $types[R::text($type['key'])] = $type;
        }
        foreach ($entities as $index => $input) {
            $number = $index + 1;
            $key    = $input->type ?? R::text($this->profile['default_entity_type']);
            if (isset($types[$key]) === false) {
                throw $this->fail('unknown-entity-type', ['entity' => $number, 'type' => $key]);
            }
            $this->entities[] = new PricedEntity(
                $number,
                $types[$key],
                $this->kilograms($input, $number),
                $this->volume($input, $number),
                $this->readAttributes($input->attributes, 'entity', $number),
            );
        }
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function readAttributes(array $values, string $scope, int $entity): array
    {
        $read = [];
        foreach ($values as $key => $value) {
            $definition = $this->attribute((string) $key);
            if ($definition === null || $definition['scope'] !== $scope) {
                throw $this->fail('attribute-invalid', ['attribute' => (string) $key, 'entity' => $entity]);
            }
            if ($value === null) {
                continue;
            }
            $read[(string) $key] = $this->attributeValue($definition, $value, $entity);
        }
        foreach ($this->attributeDefinitions as $key => $definition) {
            if ($definition['scope'] === $scope && $this->isRequired($definition) && isset($read[$key]) === false) {
                throw $this->fail('attribute-required', ['attribute' => R::text($definition['name']), 'entity' => $entity]);
            }
        }

        return $read;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function attributeValue(array $definition, mixed $value, int $entity): bool|string|BigRational
    {
        $invalid = $this->fail('attribute-invalid', ['attribute' => R::text($definition['name']), 'entity' => $entity]);
        switch ($definition['type']) {
            case 'boolean':
                return is_bool($value) ? $value : throw $invalid;
            case 'choice':
                $choices = array_map(
                    static fn (array $choice): mixed => $choice['key'],
                    R::items($definition['choices']),
                );

                return is_string($value) && in_array($value, $choices, true) ? $value : throw $invalid;
            default:
                $number = Exact::canonical($value);
                if (
                    $number === null
                    || str_starts_with($number, '-')
                    || Exact::rational($number)->isGreaterThan(R::text($definition['maximum']))
                ) {
                    throw $invalid;
                }

                return Exact::rational($number);
        }
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function isRequired(array $definition): bool
    {
        return $definition['required'] === true
            || in_array($this->serviceRate['key'], R::strings($definition['required_for']), true);
    }

    private function kilograms(EntityInput $input, int $number): ?BigRational
    {
        if ($input->weight === null) {
            return null;
        }
        $weight = $this->measure($input->weight, $number);
        if ($input->weightUnit === null || isset(Units::WEIGHT[$input->weightUnit]) === false) {
            throw $this->fail('entity-invalid', ['entity' => $number]);
        }

        return Units::toKilograms($weight, $input->weightUnit);
    }

    private function volume(EntityInput $input, int $number): ?BigRational
    {
        $sides = [$input->length, $input->width, $input->height];
        if (count(array_filter($sides, static fn (mixed $side): bool => $side !== null)) === 0) {
            return null;
        }
        if (
            in_array(null, $sides, true)
            || $input->dimensionsUnit === null
            || isset(Units::LENGTH[$input->dimensionsUnit]) === false
        ) {
            throw $this->fail('entity-invalid', ['entity' => $number]);
        }
        $volume = BigRational::one();
        foreach ($sides as $side) {
            $volume = $volume->multipliedBy(
                Units::toCentimetres($this->measure($side, $number), $input->dimensionsUnit),
            );
        }

        return $volume;
    }

    private function measure(int|float|string|null $value, int $number): BigRational
    {
        $canonical = Exact::canonical($value);
        if (
            $canonical === null
            || str_starts_with($canonical, '-')
            || Exact::rational($canonical)->isGreaterThan(self::MAX_MEASURE)
        ) {
            throw $this->fail('entity-invalid', ['entity' => $number]);
        }

        return Exact::rational($canonical);
    }
}
