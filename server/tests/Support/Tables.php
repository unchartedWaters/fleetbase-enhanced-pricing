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
        $mysql  = new MySqlConnection(static fn () => null, 'fleetbase', '', $config);
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
