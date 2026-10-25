<?php

namespace Fleetbase\EnhancedPricing\Tests\Support;

/**
 * Reads the Markdown documentation, so tests can check it against the code.
 */
final class Docs
{
    public const FILES = [
        'README.md',
        'CONTRIBUTING.md',
        'docs/SETUP.md',
        'docs/profile-format.md',
        'docs/pricing-engine.md',
    ];

    public static function path(string $relative): string
    {
        return dirname(__DIR__, 3) . '/' . $relative;
    }

    public static function text(string $relative): string
    {
        return (string) file_get_contents(self::path($relative));
    }

    /**
     * @return list<string>
     */
    public static function jsonBlocks(string $relative): array
    {
        preg_match_all('/```json\n(.*?)```/s', self::text($relative), $matches);

        return $matches[1];
    }

    public static function parses(string $json): bool
    {
        try {
            json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        return true;
    }

    /**
     * The JSON block that follows a marker comment, decoded.
     */
    public static function jsonAfter(string $relative, string $marker): mixed
    {
        $text = self::text($relative);
        $at   = strpos($text, "<!-- $marker -->");
        if ($at === false || preg_match('/```json\n(.*?)```/s', $text, $match, 0, $at) !== 1) {
            return null;
        }

        return json_decode($match[1], true);
    }

    /**
     * Links to files in this repository, relative to the document.
     *
     * @return list<string>
     */
    public static function relativeLinks(string $relative): array
    {
        preg_match_all('/\]\(([^)#\s]+)(?:#[^)]*)?\)/', self::text($relative), $matches);

        return array_values(array_filter(
            $matches[1],
            static fn (string $link): bool => preg_match('/^[a-z]+:/', $link) !== 1,
        ));
    }
}
