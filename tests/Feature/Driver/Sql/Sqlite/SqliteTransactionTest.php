<?php declare(strict_types=1);

    use STDW\Database\Driver\Sql\Sqlite\SqliteConfig;
    use STDW\Database\Driver\Sql\Sqlite\SqliteConnection;
    use STDW\Database\Driver\Sql\Sqlite\SqliteDriver;
    use STDW\Database\Param\Param;
    use STDW\Database\Resource\Resource;
    use Tests\Stubs\Schema;


    beforeEach(function () {
        $this->connection = new SqliteConnection(new SqliteConfig());
        $this->driver = new SqliteDriver($this->connection);
        $this->resource = new Resource('users');

        Schema::createUsers($this->connection->pdo());
    });


    test('begin, commit and rollback control the transaction state', function () {
        expect($this->driver->inTransaction())->toBeFalse();

        $this->driver->begin();

        expect($this->driver->inTransaction())->toBeTrue();

        $this->driver->commit();

        expect($this->driver->inTransaction())->toBeFalse();

        $this->driver->begin();
        $this->driver->rollback();

        expect($this->driver->inTransaction())->toBeFalse();
    });

    test('committed writes persist', function () {
        $this->driver->begin();
        $this->driver->create($this->resource, ['name' => Param::str('Ada')]);
        $this->driver->commit();

        expect($this->driver->total($this->resource))->toBe(1);
    });

    test('rolled back writes are discarded', function () {
        $this->driver->begin();
        $this->driver->create($this->resource, ['name' => Param::str('Ada')]);
        $this->driver->rollback();

        expect($this->driver->total($this->resource))->toBe(0);
    });

    test('begin inside an open transaction fails', function () {
        $this->driver->begin();
        $this->driver->begin();
    })->throws(\STDW\Database\Exception\DriverException::class);
