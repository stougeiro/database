<?php declare(strict_types=1);

    use STDW\Database\Driver\Sql\Sqlite\SqliteConnection;
    use STDW\Database\Exception\ManagerException;
    use STDW\Database\Manager\ConnectionFactory;


    test('builds a sqlite connection from a config array', function () {
        $factory = new ConnectionFactory();

        $connection = $factory->make(['driver' => 'pdo-sqlite', 'database' => ':memory:']);

        expect($connection)->toBeInstanceOf(SqliteConnection::class)
            ->and($connection->config()->database())->toBe(':memory:');
    });

    test('rejects an unknown driver alias', function () {
        (new ConnectionFactory())->make(['driver' => 'pdo-mysql']);
    })->throws(ManagerException::class);

    test('rejects a config without a driver alias', function () {
        (new ConnectionFactory())->make(['database' => ':memory:']);
    })->throws(ManagerException::class);

    test('wraps invalid config as a manager exception', function () {
        (new ConnectionFactory())->make(['driver' => 'pdo-sqlite', 'database' => '']);
    })->throws(ManagerException::class);
