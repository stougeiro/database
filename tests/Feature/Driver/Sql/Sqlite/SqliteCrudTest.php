<?php declare(strict_types=1);

    use STDW\Contract\Database\QueryTree\Types\OrderDirection;
    use STDW\Database\Driver\Sql\Sqlite\SqliteConfig;
    use STDW\Database\Driver\Sql\Sqlite\SqliteConnection;
    use STDW\Database\Driver\Sql\Sqlite\SqliteDriver;
    use STDW\Database\Exception\QueryException;
    use STDW\Database\Param\Param;
    use STDW\Database\Resource\Resource;
    use Tests\Stubs\Schema;


    beforeEach(function () {
        $this->connection = new SqliteConnection(new SqliteConfig());
        $this->driver = new SqliteDriver($this->connection);
        $this->resource = new Resource('users');

        Schema::createUsers($this->connection->pdo());
    });


    test('announces every capability', function () {
        $capabilities = $this->driver->capabilities();

        expect($capabilities->supportsAggregation())->toBeTrue()
            ->and($capabilities->supportsBatch())->toBeTrue()
            ->and($capabilities->supportsSearch())->toBeTrue()
            ->and($capabilities->supportsStreaming())->toBeTrue()
            ->and($capabilities->supportsTransactions())->toBeTrue()
            ->and($capabilities->supportsUpsert())->toBeTrue();
    });

    test('create() inserts a record and returns it with the generated id', function () {
        $result = $this->driver->create($this->resource, [
            'name' => Param::str('Ada'),
            'age' => Param::int(36),
        ]);

        expect($result->isValid())->toBeTrue()
            ->and($result->count())->toBe(1)
            ->and($result->data()['id'])->toBe(1)
            ->and($result->data()['name'])->toBe('Ada');
    });

    test('read() returns a single record with early stop', function () {
        Schema::seedUsers($this->connection->pdo(), [
            ['name' => 'Ada', 'age' => 36],
            ['name' => 'Grace', 'age' => 45],
        ]);

        $result = $this->driver->read($this->resource, ['name' => Param::str('Grace')]);

        expect($result->count())->toBe(1)
            ->and($result->data()['age'])->toBe(45);

        $missing = $this->driver->read($this->resource, ['name' => Param::str('Nobody')]);

        expect($missing->count())->toBe(0)
            ->and($missing->data())->toBeNull();
    });

    test('list() filters, orders and paginates', function () {
        Schema::seedUsers($this->connection->pdo(), [
            ['name' => 'Ada', 'age' => 36],
            ['name' => 'Grace', 'age' => 45],
            ['name' => 'Edsger', 'age' => 72],
        ]);

        $result = $this->driver->list(
            $this->resource,
            filter: ['age GTE' => Param::int(40)],
            order: ['age' => OrderDirection::Desc],
            pagination: ['limit' => 1],
        );

        expect($result->count())->toBe(1)
            ->and($result->data()[0]['name'])->toBe('Edsger');
    });

    test('list() resolves page and perPage', function () {
        Schema::seedUsers($this->connection->pdo(), [
            ['name' => 'A', 'age' => 1],
            ['name' => 'B', 'age' => 2],
            ['name' => 'C', 'age' => 3],
        ]);

        $result = $this->driver->list($this->resource, pagination: ['page' => 2, 'perPage' => 2]);

        expect($result->count())->toBe(1)
            ->and($result->data()[0]['name'])->toBe('C');
    });

    test('list() supports rowid cursor pagination', function () {
        Schema::seedUsers($this->connection->pdo(), [
            ['name' => 'A', 'age' => 1],
            ['name' => 'B', 'age' => 2],
            ['name' => 'C', 'age' => 3],
        ]);

        $first = $this->driver->list($this->resource, pagination: ['limit' => 2]);

        expect($first->count())->toBe(2);

        $lastId = $first->data()[1]['id'];
        $next = $this->driver->list($this->resource, pagination: ['cursor' => $lastId]);

        expect($next->count())->toBe(1)
            ->and($next->data()[0]['name'])->toBe('C');
    });

    test('update() returns the affected count', function () {
        Schema::seedUsers($this->connection->pdo(), [
            ['name' => 'Ada', 'age' => 36],
            ['name' => 'Grace', 'age' => 45],
        ]);

        $result = $this->driver->update(
            $this->resource,
            ['age' => Param::int(37)],
            ['name' => Param::str('Ada')],
        );

        expect($result->count())->toBe(1)
            ->and($this->driver->total($this->resource, ['age' => Param::int(37)]))->toBe(1);
    });

    test('delete() returns the affected count', function () {
        Schema::seedUsers($this->connection->pdo(), [
            ['name' => 'Ada', 'age' => 36],
            ['name' => 'Grace', 'age' => 45],
        ]);

        $result = $this->driver->delete($this->resource, ['age GTE' => Param::int(40)]);

        expect($result->count())->toBe(1)
            ->and($this->driver->total($this->resource))->toBe(1);
    });

    test('total() counts matching records', function () {
        Schema::seedUsers($this->connection->pdo(), [
            ['name' => 'Ada', 'age' => 36],
            ['name' => 'Grace', 'age' => 45],
        ]);

        expect($this->driver->total($this->resource))->toBe(2)
            ->and($this->driver->total($this->resource, ['age GTE' => Param::int(40)]))->toBe(1);
    });

    test('binds typed params without injection', function () {
        $this->driver->create($this->resource, [
            'name' => Param::str("Robert'); DROP TABLE users;--"),
        ]);

        $found = $this->driver->read($this->resource, [
            'name' => Param::str("Robert'); DROP TABLE users;--"),
        ]);

        expect($found->count())->toBe(1)
            ->and($this->driver->total($this->resource))->toBe(1);
    });

    test('rejects invalid SQL through QueryException', function () {
        $this->driver->list(new Resource('missing_table'));
    })->throws(QueryException::class);
