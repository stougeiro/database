<?php declare(strict_types=1);

    use STDW\Database\Driver\Sql\Sqlite\SqliteConfig;
    use STDW\Database\Driver\Sql\Sqlite\SqliteConnection;
    use STDW\Database\Driver\Sql\Sqlite\SqliteDriver;
    use STDW\Database\Exception\QueryException;
    use STDW\Database\Param\Param;
    use STDW\Database\Resource\Resource;
    use STDW\Database\Resource\ResourceMetadata;
    use Tests\Stubs\Schema;


    beforeEach(function () {
        $this->connection = new SqliteConnection(new SqliteConfig());
        $this->driver = new SqliteDriver($this->connection);
        $this->resource = new Resource('users', new ResourceMetadata(primaryKey: ['name']));

        Schema::createUsers($this->connection->pdo());
    });


    test('inserts when the conflict row is absent', function () {
        $result = $this->driver->upsert(
            $this->resource,
            ['name' => Param::str('Ada'), 'age' => Param::int(36)],
            ['name' => Param::str('Ada')],
        );

        expect($result->count())->toBe(1)
            ->and($this->driver->total($this->resource))->toBe(1)
            ->and($this->driver->read($this->resource, ['name' => Param::str('Ada')])->data()['age'])->toBe(36);
    });

    test('updates when the conflict row is present', function () {
        Schema::seedUsers($this->connection->pdo(), [['name' => 'Ada', 'age' => 36]]);

        $result = $this->driver->upsert(
            $this->resource,
            ['name' => Param::str('Ada'), 'age' => Param::int(37)],
            ['name' => Param::str('Ada')],
        );

        expect($result->count())->toBe(1)
            ->and($this->driver->total($this->resource))->toBe(1)
            ->and($this->driver->read($this->resource, ['name' => Param::str('Ada')])->data()['age'])->toBe(37);
    });

    test('falls back to the metadata primary key when the filter has no EQ', function () {
        $result = $this->driver->upsert(
            $this->resource,
            ['name' => Param::str('Ada'), 'age' => Param::int(36)],
            [],
        );

        expect($result->count())->toBe(1)
            ->and($this->driver->total($this->resource))->toBe(1);
    });

    test('rejects upsert without any conflict columns', function () {
        $bare = new Resource('users');

        $this->driver->upsert($bare, ['name' => Param::str('Ada')], []);
    })->throws(QueryException::class);

    test('rejects upsert without data', function () {
        $this->driver->upsert($this->resource, [], ['name' => Param::str('Ada')]);
    })->throws(QueryException::class);
