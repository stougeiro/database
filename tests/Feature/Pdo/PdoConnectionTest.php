<?php declare(strict_types=1);

    use STDW\Database\Exception\ConnectionException;
    use Tests\Stubs\Pdo\InvalidDsnConnection;
    use Tests\Stubs\Pdo\PdoSqliteConnectionStub;


    test('connects lazily through pdo()', function () {
        $connection = new PdoSqliteConnectionStub();

        expect($connection->isConnected())->toBeFalse();

        $pdo = $connection->pdo();

        expect($pdo)->toBeInstanceOf(PDO::class)
            ->and($connection->isConnected())->toBeTrue();
    });

    test('connect() is idempotent and returns the same handle', function () {
        $connection = new PdoSqliteConnectionStub();

        $connection->connect();
        $first = $connection->pdo();
        $connection->connect();

        expect($connection->pdo())->toBe($first)
            ->and($connection->isConnected())->toBeTrue();
    });

    test('disconnect() closes the handle and pdo() reconnects', function () {
        $connection = new PdoSqliteConnectionStub();
        $connection->pdo()->exec('CREATE TABLE t (id INTEGER)');

        $connection->disconnect();

        expect($connection->isConnected())->toBeFalse();

        $pdo = $connection->pdo();

        expect($connection->isConnected())->toBeTrue()
            ->and($pdo->query('SELECT COUNT(*) FROM sqlite_master')->fetchColumn())->toBe(0);
    });

    test('fails with a connection exception on an invalid dsn', function () {
        $connection = new InvalidDsnConnection();

        $connection->connect();
    })->throws(ConnectionException::class);
