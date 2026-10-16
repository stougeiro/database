<?php declare(strict_types=1);

    use STDW\Database\Driver\Sql\Sqlite\SqliteConnection;
    use STDW\Database\Driver\Sql\Sqlite\SqliteDriver;
    use STDW\Database\Exception\ManagerException;
    use STDW\Database\Manager\ConnectionFactory;
    use STDW\Database\Manager\ConnectionRegistry;
    use STDW\Database\Manager\DatabaseManager;
    use STDW\Database\Manager\DriverFactory;
    use STDW\Database\Param\Param;
    use STDW\Database\Resource\Resource;
    use Tests\Stubs\Schema;


    $makeManager = fn (): DatabaseManager => new DatabaseManager(
        connections: [
            'default' => ['driver' => 'pdo-sqlite', 'database' => ':memory:'],
            'analytics' => ['driver' => 'pdo-sqlite', 'database' => ':memory:'],
        ],
        connectionFactory: new ConnectionFactory(),
        driverFactory: new DriverFactory(),
        registry: new ConnectionRegistry(),
    );


    test('resolves the default connection lazily and caches it', function () use ($makeManager) {
        $manager = $makeManager();

        $first = $manager->connection();
        $second = $manager->connection();

        expect($first)->toBeInstanceOf(SqliteConnection::class)
            ->and($second)->toBe($first)
            ->and($first->isConnected())->toBeTrue();
    });

    test('resolves named connections independently', function () use ($makeManager) {
        $manager = $makeManager();

        expect($manager->connection('default'))->not->toBe($manager->connection('analytics'));
    });

    test('builds and caches a driver per connection', function () use ($makeManager) {
        $manager = $makeManager();

        $driver = $manager->driver();

        expect($driver)->toBeInstanceOf(SqliteDriver::class)
            ->and($manager->driver())->toBe($driver)
            ->and($driver->connection())->toBe($manager->connection());
    });

    test('an unknown connection name throws', function () use ($makeManager) {
        $makeManager()->connection('missing');
    })->throws(ManagerException::class);

    test('a config without a driver alias throws on driver()', function () {
        $manager = new DatabaseManager(
            connections: ['default' => ['database' => ':memory:']],
            connectionFactory: new ConnectionFactory(),
            driverFactory: new DriverFactory(),
            registry: new ConnectionRegistry(),
        );

        $manager->driver();
    })->throws(ManagerException::class);

    test('disconnect() closes one connection and clears its driver', function () use ($makeManager) {
        $manager = $makeManager();
        $connection = $manager->connection();

        $manager->disconnect();

        expect($connection->isConnected())->toBeFalse()
            ->and($manager->connection())->not->toBe($connection);
    });

    test('purge() closes every connection', function () use ($makeManager) {
        $manager = $makeManager();
        $default = $manager->connection();
        $analytics = $manager->connection('analytics');

        $manager->purge();

        expect($default->isConnected())->toBeFalse()
            ->and($analytics->isConnected())->toBeFalse();
    });

    test('a driver obtained through the manager performs CRUD end to end', function () use ($makeManager) {
        $manager = $makeManager();
        $driver = $manager->driver();
        $resource = new Resource('users');

        Schema::createUsers($driver->connection()->pdo());

        $driver->create($resource, ['name' => Param::str('Ada'), 'age' => Param::int(36)]);

        expect($driver->total($resource))->toBe(1)
            ->and($driver->read($resource, ['name' => Param::str('Ada')])->data()['age'])->toBe(36);
    });
