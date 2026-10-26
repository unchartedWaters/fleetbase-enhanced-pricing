<?php

use Fleetbase\EnhancedPricing\Providers\EnhancedPricingServiceProvider;
use Fleetbase\EnhancedPricing\Tests\Support\Database;
use Fleetbase\EnhancedPricing\Tests\Support\Tables;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Schema;

// The seven tables: their names, keys and the MySQL statements they make.

beforeEach(function () {
    Database::fresh();
});

test('the migrations make the seven tables', function () {
    foreach (Tables::names() as $table) {
        expect(Schema::hasTable($table))->toBeTrue("$table is missing");
    }
});

test('every index and foreign key has an explicit name of 64 characters or fewer', function () {
    $sql = implode("\n", Tables::statements());
    preg_match_all('/(?:constraint|index|unique|key) `([^`]+)`/i', $sql, $names);

    expect(count($names[1]))->toBeGreaterThanOrEqual(20);
    foreach ($names[1] as $name) {
        expect(strlen($name))->toBeLessThanOrEqual(64, "$name is too long")
            ->and($name)->toStartWith('ep_');
    }
    foreach (Tables::names() as $table) {
        expect(strlen($table))->toBeLessThanOrEqual(64);
    }
});

test('the down migrations drop every table', function () {
    $sql = implode("\n", Tables::statements('down'));

    foreach (Tables::names() as $table) {
        expect($sql)->toContain("drop table if exists `$table`");
    }
});

test('no table has soft deletes', function () {
    foreach (Tables::names() as $table) {
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
