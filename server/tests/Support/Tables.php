<?php

namespace Fleetbase\EnhancedPricing\Tests\Support;

use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\Facade;

/**
 * The extension's tables, and the MySQL statements its migrations make.
 */
final class Tables
{
    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return [
            'enhanced_pricing_profiles',
            'enhanced_pricing_profile_versions',
            'enhanced_pricing_customers',
            'enhanced_pricing_rate_cards',
            'enhanced_pricing_rate_card_rows',
            'enhanced_pricing_manual_charges',
            'enhanced_pricing_dated_changes',
        ];
    }

    /**
     * The MySQL statements the migrations make, without a MySQL server: the connection only
     * records them.
     *
     * @return list<string>
     */
    public static function statements(string $direction = 'up'): array
    {
        Database::fresh();
        $config = ['driver' => 'mysql', 'charset' => 'utf8mb4'];
        // Laravel 12's MySQL grammar asks the connection for the server version, so name one.
        $mysql = new class(static fn () => null, 'fleetbase', '', $config) extends MySqlConnection {
            public function isMaria(): bool
            {
                return false;
            }

            public function getServerVersion(): string
            {
                return '8.0.36';
            }
        };
        $app    = Facade::getFacadeApplication();
        $app->bind('db.schema', static fn () => $mysql->getSchemaBuilder());
        Facade::clearResolvedInstances();

        $queries = $mysql->pretend(static function () use ($direction): void {
            foreach (Database::migrations() as $migration) {
                $migration->{$direction}();
            }
        });

        return array_map(static fn (array $query): string => $query['query'], $queries);
    }
}
