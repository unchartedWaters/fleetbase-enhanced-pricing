<?php

namespace Fleetbase\EnhancedPricing\Admin;

use Fleetbase\EnhancedPricing\Pricing\EntityInput;
use Fleetbase\EnhancedPricing\Pricing\ManualChargeInput;

/**
 * Reads the preview form's order: service rate, pickup time and timezone, distance, attributes,
 * entities and manual charges. Anything malformed is refused before pricing, with its field.
 */
final class PreviewInput
{
    public const MAX_ENTITIES = 1000;

    public const MAX_CHARGES = 20;

    /**
     * @param array<string, mixed> $input
     */
    public function __construct(private readonly array $input)
    {
    }

    public function serviceRate(): string
    {
        $rate = $this->input['service_rate'] ?? null;

        return is_string($rate) && $rate !== ''
            ? $rate
            : throw AdminRefused::invalid('field-required', [], 'service_rate');
    }

    public function pickupAt(): \DateTimeImmutable
    {
        $text   = $this->input['pickup_at'] ?? null;
        $format = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?(\.\d+)?(Z|[+-]\d{2}:\d{2})$/';
        if (is_string($text) && preg_match($format, $text) === 1) {
            try {
                return new \DateTimeImmutable($text);
            } catch (\Exception) {
                // refused below
            }
        }

        throw AdminRefused::invalid('field-invalid', [], 'pickup_at');
    }

    public function timezone(string $default): string
    {
        $zone = $this->input['timezone'] ?? null;
        if ($zone === null || $zone === '') {
            return $default;
        }
        if (is_string($zone) && in_array($zone, \DateTimeZone::listIdentifiers(), true)) {
            return $zone;
        }

        throw AdminRefused::invalid('field-invalid', [], 'timezone');
    }

    public function distanceMeters(): ?int
    {
        $distance = $this->input['distance_meters'] ?? null;
        if ($distance === null || (is_int($distance) && $distance >= 0)) {
            return $distance;
        }

        throw AdminRefused::invalid('field-invalid', [], 'distance_meters');
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->object($this->input['attributes'] ?? [], 'attributes');
    }

    public function row(): ?string
    {
        $row = $this->input['row'] ?? null;

        return $row === null || is_string($row) ? $row : throw AdminRefused::invalid('field-invalid', [], 'row');
    }

    /**
     * @return list<EntityInput>
     */
    public function entities(): array
    {
        $entities = [];
        foreach ($this->list('entities', self::MAX_ENTITIES) as $index => $raw) {
            $entity     = $this->object($raw, "entities.$index");
            $path       = "entities.$index";
            $entities[] = new EntityInput(
                type: self::text($entity, 'type', $path),
                weight: self::number($entity, 'weight', $path),
                weightUnit: self::text($entity, 'weight_unit', $path),
                length: self::number($entity, 'length', $path),
                width: self::number($entity, 'width', $path),
                height: self::number($entity, 'height', $path),
                dimensionsUnit: self::text($entity, 'dimensions_unit', $path),
                attributes: $this->object($entity['attributes'] ?? [], "$path.attributes"),
            );
        }

        return $entities;
    }

    /**
     * @return list<ManualChargeInput>
     */
    public function manualCharges(): array
    {
        $charges = [];
        foreach ($this->list('manual_charges', self::MAX_CHARGES) as $index => $raw) {
            $charge   = $this->object($raw, "manual_charges.$index");
            $key      = $charge['key'] ?? null;
            $quantity = $charge['quantity'] ?? null;
            $amount   = $charge['amount'] ?? null;
            $valid    = is_string($key)
                && ($quantity === null || is_int($quantity) || is_float($quantity) || is_string($quantity))
                && ($amount === null || is_int($amount));
            if ($valid === false) {
                throw AdminRefused::invalid('field-invalid', [], "manual_charges.$index");
            }
            /* @var string $key */
            /* @var int|float|string|null $quantity */
            /* @var int|null $amount */
            $charges[] = ManualChargeInput::new($key, $quantity, $amount);
        }

        return $charges;
    }

    /**
     * @return list<mixed>
     */
    private function list(string $field, int $max): array
    {
        $value = $this->input[$field] ?? [];
        if (is_array($value) === false || array_is_list($value) === false) {
            throw AdminRefused::invalid('field-invalid', [], $field);
        }
        if (count($value) > $max) {
            throw AdminRefused::invalid('too-many', ['max' => $max], $field);
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function object(mixed $value, string $path): array
    {
        if (is_array($value) === false || ($value !== [] && array_is_list($value))) {
            throw AdminRefused::invalid('field-invalid', [], $path);
        }
        $object = [];
        foreach ($value as $key => $item) {
            $object[(string) $key] = $item;
        }

        return $object;
    }

    /**
     * @param array<string, mixed> $object
     */
    private static function text(array $object, string $field, string $path): ?string
    {
        $value = $object[$field] ?? null;

        return $value === null || is_string($value)
            ? $value
            : throw AdminRefused::invalid('field-invalid', [], "$path.$field");
    }

    /**
     * @param array<string, mixed> $object
     */
    private static function number(array $object, string $field, string $path): int|float|string|null
    {
        $value = $object[$field] ?? null;
        if ($value === null || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        throw AdminRefused::invalid('field-invalid', [], "$path.$field");
    }
}
