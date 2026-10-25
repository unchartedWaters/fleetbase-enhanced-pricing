<?php

namespace Fleetbase\EnhancedPricing\Profile;

use Fleetbase\EnhancedPricing\Profile\Schema as S;

/**
 * Checks dated changes to a profile's global values. Each change names a value by its override
 * path; only money, percents and numbers may change by date, and the profile with the change
 * applied must still pass every check.
 */
final class ChangeValidator
{
    public const MAX_CHANGES = 1000;

    /**
     * Every check, for when changes are saved: each change on its own, then the profile with each
     * change applied.
     *
     * @param array<string, mixed> $profile the profile as saved
     * @param array<mixed>         $changes list of { path, value, effective_from }
     *
     * @return list<Problem>
     */
    public function check(array $profile, array $changes): array
    {
        return $this->run($profile, $changes, true);
    }

    /**
     * Each change on its own: a known path that can change by date, a value of the right type, and
     * one change per path and date. Pricing uses this, since it checks the profile with the
     * changes in effect on its date anyway.
     *
     * @param array<string, mixed> $profile the profile as saved
     * @param array<mixed>         $changes list of { path, value, effective_from }
     *
     * @return list<Problem>
     */
    public function checkEach(array $profile, array $changes): array
    {
        return $this->run($profile, $changes, false);
    }

    /**
     * @param array<string, mixed> $profile
     * @param array<mixed>         $changes
     *
     * @return list<Problem>
     */
    private function run(array $profile, array $changes, bool $applied): array
    {
        try {
            $normalized = (new ProfileValidator())->normalize($profile);
        } catch (InvalidProfile $invalid) {
            return $invalid->problems;
        }
        if (count($changes) > self::MAX_CHANGES) {
            return [Problem::atPath('', 'too-many', ['max' => self::MAX_CHANGES])];
        }

        $schema  = new S();
        $seen    = [];
        $results = [];
        foreach (array_values($changes) as $index => $raw) {
            $change = $this->change($schema, $normalized, $raw, $index, $seen);
            if ($change === null || $applied === false) {
                continue;
            }
            // The same value at the same place gives the same problems, so it is checked once.
            $memo = json_encode([$change['pointer'], $change['value']]);
            if ($memo === false) {
                $memo = "change $index";
            }
            $results[$memo] ??= (new ProfileValidator())->recheck(
                OverridePaths::apply($normalized, $change['pointer'], $change['value']),
            );
            foreach ($results[$memo] as $problem) {
                $schema->add(new Problem("$index.value", $problem->key, $problem->params));
            }
        }

        return $schema->problems();
    }

    /**
     * Checks one change on its own, returning where it applies and its checked value.
     *
     * @param array<string, mixed> $normalized
     * @param array<string, true>  $seen       path and date of the changes before it
     *
     * @return array{pointer: list<string|int>, value: mixed}|null
     */
    private function change(S $schema, array $normalized, mixed $raw, int $index, array &$seen): ?array
    {
        $change = $schema->fields($raw, [
            'path'           => S::type('text'),
            'value'          => S::nullable(S::type('raw')),
            'effective_from' => S::type('date'),
        ], (string) $index);
        if ($change === null || is_string($change['path']) === false) {
            return null;
        }
        $found = OverridePaths::find($normalized, $change['path']);
        if ($found === null) {
            $schema->add(Problem::atPath("$index.path", 'unknown-override', ['path' => $change['path']]));

            return null;
        }
        if ($found['dated'] === false || $found['pointer'] === null) {
            $schema->add(Problem::atPath("$index.path", 'not-dated', ['path' => $change['path']]));

            return null;
        }
        $key = $change['path'] . '@' . ProfileReferences::text($change['effective_from']);
        if (isset($seen[$key])) {
            $schema->add(Problem::atPath(
                "$index.effective_from",
                'duplicate',
                ['value' => ProfileReferences::text($change['effective_from'])],
            ));

            return null;
        }
        $seen[$key] = true;
        $before     = count($schema->problems());
        $value      = ($found['check'])($schema, $change['value'], "$index.value");

        return count($schema->problems()) === $before ? ['pointer' => $found['pointer'], 'value' => $value] : null;
    }
}
