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


    test('insertMany() inserts every row and counts them', function () {
        $result = $this->driver->insertMany($this->resource, [
            ['name' => Param::str('Ada'), 'age' => Param::int(36)],
            ['name' => Param::str('Grace'), 'age' => Param::int(45)],
            ['name' => Param::str('Edsger'), 'age' => Param::int(72)],
        ]);

        expect($result->count())->toBe(3)
            ->and($this->driver->total($this->resource))->toBe(3);
    });

    test('insertMany() rolls the whole batch back on failure', function () {
        $this->driver->insertMany($this->resource, [
            ['name' => Param::str('Ada')],
            [],
        ]);
    })->throws(\STDW\Database\Exception\QueryException::class);

    test('insertMany() leaves no partial rows after a failure', function () {
        try {
            $this->driver->insertMany($this->resource, [
                ['name' => Param::str('Ada')],
                [],
            ]);
        } catch (\STDW\Database\Exception\QueryException) {
        }

        expect($this->driver->total($this->resource))->toBe(0);
    });

    test('updateMany() applies each entry and sums the affected rows', function () {
        Schema::seedUsers($this->connection->pdo(), [
            ['name' => 'Ada', 'age' => 36],
            ['name' => 'Grace', 'age' => 45],
            ['name' => 'Edsger', 'age' => 72],
        ]);

        $result = $this->driver->updateMany($this->resource, [
            ['data' => ['age' => Param::int(37)], 'where' => ['name' => Param::str('Ada')]],
            ['data' => ['age' => Param::int(46)], 'where' => ['name' => Param::str('Grace')]],
        ]);

        expect($result->count())->toBe(2)
            ->and($this->driver->total($this->resource, ['age' => Param::int(37)]))->toBe(1)
            ->and($this->driver->total($this->resource, ['age' => Param::int(46)]))->toBe(1);
    });

    test('insertMany() with no rows is a no-op', function () {
        $result = $this->driver->insertMany($this->resource, []);

        expect($result->count())->toBe(0);
    });
