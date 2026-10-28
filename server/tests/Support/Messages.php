<?php

namespace Fleetbase\EnhancedPricing\Tests\Support;

use Fleetbase\EnhancedPricing\Pricing\PricingFailed;
use Fleetbase\EnhancedPricing\Profile\Problem;
use Symfony\Component\Yaml\Yaml;

/**
 * The English text people see for a message key, and the keys the engine can raise.
 */
final class Messages
{
    /**
     * @return array<string, mixed>
     */
    public static function translations(): array
    {
        $parsed = Yaml::parseFile(__DIR__ . '/../../../translations/en-us.yaml');

        return is_array($parsed['enhanced-pricing'] ?? null) ? $parsed['enhanced-pricing'] : [];
    }

    /**
     * The English text of a key with its parameters filled in. Parameters that are type or kind
     * codes are shown with their own English text, as the console does.
     *
     * @param array<string, scalar|null> $params
     */
    public static function english(string $key, array $params): string
    {
        $all     = self::translations();
        $section = str_starts_with($key, 'enhanced-pricing.profile.') ? 'profile' : 'pricing';
        $name    = substr($key, strlen("enhanced-pricing.$section."));
        $text    = $all[$section][$name] ?? null;
        expect($text)->toBeString("$key has no English text");
        foreach ($params as $param => $value) {
            $shown = (string) $value;
            if ($param === 'expected') {
                $shown = $all['types'][$shown] ?? "<no text for type $shown>";
            }
            if ($param === 'kind') {
                $shown = $all['kinds'][$shown] ?? "<no text for kind $shown>";
            }
            $text = str_replace('{' . $param . '}', $shown, (string) $text);
        }

        return (string) $text;
    }

    public static function failure(callable $price): PricingFailed
    {
        try {
            $price();
        } catch (PricingFailed $failed) {
            return $failed;
        }
        throw new \RuntimeException('Pricing did not stop.');
    }

    /**
     * The English text of each problem.
     *
     * @param list<Problem> $problems
     *
     * @return list<string>
     */
    public static function problems(array $problems): array
    {
        return array_map(static fn (Problem $problem) => self::english($problem->key, $problem->params), $problems);
    }

    /**
     * The literal keys passed to a call of one of these functions, however deeply its arguments
     * nest.
     *
     * @param list<string> $functions
     *
     * @return list<string>
     */
    public static function keysPassedTo(array $functions): array
    {
        $keys = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../../src')) as $file) {
            if ($file->isFile() === false) {
                continue;
            }
            foreach (self::callsTo(self::tokens($file->getPathname()), $functions) as $part) {
                if (is_array($part) && $part[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $value = trim($part[1], "'\"");
                    if (preg_match('/^[a-z]+(-[a-z]+)+$/', $value) === 1) {
                        $keys[] = $value;
                    }
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * A file's tokens without whitespace and comments.
     *
     * @return list<array{int, string, int}|string>
     */
    private static function tokens(string $path): array
    {
        $skipped = [T_WHITESPACE, T_COMMENT];

        return array_values(array_filter(
            token_get_all((string) file_get_contents($path)),
            static fn ($token) => is_array($token) === false || in_array($token[0], $skipped, true) === false,
        ));
    }

    /**
     * The tokens inside the brackets of every call to one of these functions.
     *
     * @param list<array{int, string, int}|string> $tokens
     * @param list<string>                         $functions
     *
     * @return list<array{int, string, int}|string>
     */
    private static function callsTo(array $tokens, array $functions): array
    {
        $inside = [];
        foreach ($tokens as $at => $token) {
            $named = is_array($token) && in_array($token[1], $functions, true);
            if ($named === false || ($tokens[$at + 1] ?? null) !== '(') {
                continue;
            }
            $depth = 0;
            for ($next = $at + 1; $next < count($tokens); $next++) {
                $part  = $tokens[$next];
                $depth += $part === '(' ? 1 : ($part === ')' ? -1 : 0);
                if ($depth === 0) {
                    break;
                }
                $inside[] = $part;
            }
        }

        return $inside;
    }
}
