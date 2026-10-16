<?php declare(strict_types=1);

    use STDW\Database\Driver\Sql\Sqlite\SqliteConnection;
    use STDW\Database\Driver\Sql\Sqlite\SqliteDriver;
    use STDW\Database\Driver\Sql\Sqlite\SqliteConfig;
    use STDW\Database\Exception\ManagerException;
    use STDW\Database\Manager\ConnectionFactory;
    use STDW\Database\Manager\DriverFactory;
    use Tests\Stubs\Pdo\PdoSqliteConnectionStub;


    test('builds a sqlite driver from a sqlite connection', function () {
        $factory = new DriverFactory();
        $connection = new SqliteConnection(new SqliteConfig());

        $driver = $factory->make('pdo-sqlite', $connection);

        expect($driver)->toBeInstanceOf(SqliteDriver::class)
            ->and($driver->connection())->toBe($connection);
    });

    test('rejects a connection of the wrong family', function () {
        (new DriverFactory())->make('pdo-sqlite', new PdoSqliteConnectionStub());
    })->throws(ManagerException::class);

    test('rejects an unknown alias', function () {
        (new DriverFactory())->make('mongo', new SqliteConnection(new SqliteConfig()));
    })->throws(ManagerException::class);

    test('supports custom resolvers', function () {
        $factory = new DriverFactory([
            'custom' => fn ($connection, $config) => new SqliteDriver($connection),
        ]);

        $driver = $factory->make('custom', new SqliteConnection(new SqliteConfig()));

        expect($driver)->toBeInstanceOf(SqliteDriver::class);
    });
