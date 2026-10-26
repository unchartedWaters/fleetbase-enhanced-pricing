<?php

namespace Fleetbase\EnhancedPricing\Tests\Support;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;

/**
 * A fresh in-memory SQLite database for each test, with the few Fleetbase and Fleet-Ops tables
 * the extension reads, then the extension's own migrations. No Laravel application is booted:
 * a bare container holds the database, the schema builder and the config the models read.
 */
final class Database
{
    public const MIGRATIONS = __DIR__ . '/../../migrations';

    public static function fresh(): Capsule
    {
        $container = new Container();
        Container::setInstance($container);
        $container->instance('config', new Repository(['fleetbase' => ['connection' => ['db' => 'sqlite']]]));

        $capsule = new Capsule($container);
        $capsule->addConnection(
            ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
            'sqlite',
        );
        $capsule->getDatabaseManager()->setDefaultConnection('sqlite');
        $capsule->setEventDispatcher(new Dispatcher($container));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        Model::clearBootedModels();

        $container->instance('db', $capsule->getDatabaseManager());
        $container->bind('db.schema', static fn () => $capsule->getConnection()->getSchemaBuilder());
        Facade::setFacadeApplication($container);
        Facade::clearResolvedInstances();

        self::hostTables();
        foreach (self::migrations() as $migration) {
            $migration->up();
        }

        return $capsule;
    }

    /**
     * The extension's migrations, in file name order.
     *
     * @return list<Migration>
     */
    public static function migrations(): array
    {
        $files = glob(self::MIGRATIONS . '/*.php');
        $files = $files === false ? [] : $files;
        sort($files);

        $migrations = [];
        foreach ($files as $file) {
            $migration = require $file;
            if ($migration instanceof Migration) {
                $migrations[] = $migration;
            }
        }

        return $migrations;
    }

    /**
     * Two companies, each with two customer contacts, a contact that isn't a customer, and an order.
     */
    public static function seed(): void
    {
        foreach (['a', 'b'] as $company) {
            $uuid = self::company($company);
            Capsule::table('companies')->insert(['uuid' => $uuid, 'name' => "Company $company"]);
            Capsule::table('contacts')->insert([
                ['uuid' => self::customer($company), 'company_uuid' => $uuid, 'type' => 'customer'],
                ['uuid' => self::customer($company, 2), 'company_uuid' => $uuid, 'type' => 'customer'],
                ['uuid' => self::contact($company), 'company_uuid' => $uuid, 'type' => 'contact'],
            ]);
            Capsule::table('orders')->insert(['uuid' => self::order($company), 'company_uuid' => $uuid]);
        }
    }

    public static function company(string $name): string
    {
        return self::uuid($name, '1');
    }

    public static function customer(string $company, int $number = 1): string
    {
        return self::uuid($company, '2', $number);
    }

    public static function contact(string $company): string
    {
        return self::uuid($company, '3');
    }

    public static function order(string $company): string
    {
        return self::uuid($company, '4');
    }

    private static function uuid(string $company, string $kind, int $number = 1): string
    {
        return sprintf('%08x-0000-4000-8000-%s%011d', crc32($company), $kind, $number);
    }

    /**
     * The columns of core's companies, and of Fleet-Ops' contacts and orders, that the extension
     * reads. companies.uuid is a string(191), as in core.
     */
    private static function hostTables(): void
    {
        Schema::create('companies', static function (Blueprint $table): void {
            $table->string('uuid', 191)->unique();
            $table->string('name');
        });
        Schema::create('contacts', static function (Blueprint $table): void {
            $table->string('uuid', 191)->unique();
            $table->string('company_uuid', 191)->nullable();
            $table->string('type')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('orders', static function (Blueprint $table): void {
            $table->string('uuid', 191)->unique();
            $table->string('company_uuid', 191)->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }
}
