<?php

use Fleetbase\EnhancedPricing\Providers\EnhancedPricingServiceProvider;
use Fleetbase\EnhancedPricing\Tests\Support\Database;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;

// The seven tables: their names, keys and the MySQL statements they make.

/**
 * @return list<string>
 */
function pricingTables(): array
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
function mysqlStatements(string $direction = 'up'): array
{
    Database::fresh();
    $mysql = new MySqlConnection(static fn () => null, 'fleetbase', '', ['driver' => 'mysql', 'charset' => 'utf8mb4']);
    $app   = Facade::getFacadeApplication();
    $app->bind('db.schema', static fn () => $mysql->getSchemaBuilder());
    Facade::clearResolvedInstances();

    $queries = $mysql->pretend(static function () use ($direction): void {
        foreach (Database::migrations() as $migration) {
            $migration->{$direction}();
        }
    });

    return array_map(static fn (array $query): string => $query['query'], $queries);
}

beforeEach(function () {
    Database::fresh();
});

test('the migrations make the seven tables', function () {
    foreach (pricingTables() as $table) {
        expect(Schema::hasTable($table))->toBeTrue("$table is missing");
    }
});

test('every index and foreign key has an explicit name of 64 characters or fewer', function () {
    $sql = implode("\n", mysqlStatements());
    preg_match_all('/(?:constraint|index|unique|key) `([^`]+)`/i', $sql, $names);

    expect(count($names[1]))->toBeGreaterThan(20);
    foreach ($names[1] as $name) {
        expect(strlen($name))->toBeLessThanOrEqual(64, "$name is too long")
            ->and($name)->toStartWith('ep_');
    }
    foreach (pricingTables() as $table) {
        expect(strlen($table))->toBeLessThanOrEqual(64);
    }
});

test('the down migrations drop every table', function () {
    $sql = implode("\n", mysqlStatements('down'));

    foreach (pricingTables() as $table) {
        expect($sql)->toContain("drop table if exists `$table`");
    }
});

test('no table has soft deletes', function () {
    foreach (pricingTables() as $table) {
        expect(Schema::hasColumn($table, 'deleted_at'))->toBeFalse("$table has deleted_at");
    }
});

test('the database runs with foreign keys on', function () {
    expect(Capsule::select('PRAGMA foreign_keys')[0]->foreign_keys)->toBe(1);
});

test('the provider loads the extension migrations', function () {
    $source = (string) file_get_contents((new ReflectionClass(EnhancedPricingServiceProvider::class))->getFileName());

    expect($source)->toContain('loadMigrationsFrom')
        ->and(is_dir(Database::MIGRATIONS))->toBeTrue();
});
