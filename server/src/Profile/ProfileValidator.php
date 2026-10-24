<?php

namespace Fleetbase\EnhancedPricing\Profile;

/**
 * Checks a pricing profile against docs/profile-format.md and returns it normalized, with every
 * optional field filled in. The shape is checked first; references between parts are checked
 * once the shape is valid, so their messages never point at a value that is itself malformed.
 */
final class ProfileValidator
{
    public const MAX_BYTES = 65536;

    /**
     * @param array<string, mixed> $definition
     *
     * @return list<Problem>
     */
    public function check(array $definition): array
    {
        return $this->run($definition, true)[1];
    }

    /**
     * Checks a profile made from a checked one: normalized, or with overrides or dated changes
     * applied. The size limit is for the profile as saved, so it is not checked again; defaults
     * filled in make a normalized profile larger.
     *
     * @param array<string, mixed> $profile
     *
     * @return list<Problem>
     */
    public function recheck(array $profile): array
    {
        return $this->run($profile, false)[1];
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return array<string, mixed>
     *
     * @throws InvalidProfile
     */
    public function normalize(array $definition): array
    {
        [$profile, $problems] = $this->run($definition, true);
        if ($problems !== []) {
            throw new InvalidProfile($problems);
        }

        return $profile;
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return array{0: array<string, mixed>, 1: list<Problem>}
     */
    private function run(array $definition, bool $checkSize): array
    {
        if ($checkSize && self::tooLarge($definition)) {
            return [[], [Problem::at('', 'too-large', ['max' => self::MAX_BYTES])]];
        }

        $schema  = new Schema();
        $shapes  = new ProfileShapes($schema);
        $profile = $schema->fields($definition, ProfileShapes::topLevel(), '') ?? [];
        $parts   = [
            'attributes'    => $shapes->attribute(...),
            'entity_types'  => $shapes->entityType(...),
            'service_rates' => $shapes->serviceRate(...),
            'fees'          => $shapes->fee(...),
            'subtotal_fees' => $shapes->subtotalFee(...),
        ];
        foreach ($parts as $section => $check) {
            if (is_array($profile[$section] ?? null) === false) {
                continue;
            }
            $items = [];
            foreach ($profile[$section] as $index => $raw) {
                $items[] = $check($raw, "$section.$index");
            }
            $profile[$section] = $items;
        }

        if ($schema->problems() === []) {
            (new ProfileReferences($schema, $profile))->check();
        }

        return [$profile, $schema->problems()];
    }

    /**
     * @param array<string, mixed> $definition
     */
    private static function tooLarge(array $definition): bool
    {
        $encoded = json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false || strlen($encoded) > self::MAX_BYTES;
    }
}
