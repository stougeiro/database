<?php declare(strict_types=1);

    use STDW\Database\Param\Param;
    use STDW\Database\Resource\Resource;
    use STDW\Database\Resource\ResourceMetadata;
    use STDW\Database\Result\Result;
    use Tests\Stubs\Pdo\PdoSqliteConnectionStub;
    use Tests\Stubs\Pdo\SqlDriverStub;
    use Tests\Stubs\Schema;


    beforeEach(function () {
        $this->connection = new PdoSqliteConnectionStub();
        $this->driver = new SqlDriverStub($this->connection);
        $this->resource = new Resource('users', new ResourceMetadata(primaryKey: ['id']));

        Schema::createUsers($this->connection->pdo());
    });


    test('create() inserts a record and returns it with the generated id', function () {
        $result = $this->driver->create($this->resource, [
            'name' => Param::str('Ada'),
            'age' => Param::int(36),
        ]);

        expect($result)->toBeInstanceOf(Result::class)
            ->and($result->isValid())->toBeTrue()
            ->and($result->count())->toBe(1)
            ->and($result->data()['name'])->toBe('Ada')
            ->and($result->data()['age'])->toBe(36)
            ->and($result->data()['id'])->toBe(1);
    });

    test('read() returns a single matching record or an invalid empty result', function () {
        Schema::seedUsers($this->connection->pdo(), [
            ['name' => 'Ada', 'age' => 36],
            ['name' => 'Grace', 'age' => 45],
        ]);

        $found = $this->driver->read($this->resource, ['name' => Param::str('Ada')]);

        expect($found->isValid())->toBeTrue()
            ->and($found->count())->toBe(1)
            ->and($found->data()['age'])->toBe(36);

        $missing = $this->driver->read($this->resource, ['name' => Param::str('Nobody')]);

        expect($missing->isValid())->toBeTrue()
            ->and($missing->count())->toBe(0)
            ->and($missing->data())->toBeNull();
    });

    test('list() returns all matching records with order and pagination', function () {
        Schema::seedUsers($this->connection->pdo(), [
            ['name' => 'Ada', 'age' => 36],
            ['name' => 'Grace', 'age' => 45],
            ['name' => 'Edsger', 'age' => 72],
        ]);

        $all = $this->driver->list($this->resource);

        expect($all->count())->toBe(3);

        $ordered = $this->driver->list(
            $this->resource,
            filter: ['age GTE' => Param::int(40)],
            order: ['age' => \STDW\Contract\Database\QueryTree\Types\OrderDirection::Desc],
            pagination: ['limit' => 1, 'offset' => 0],
        );

        expect($ordered->count())->toBe(1)
            ->and($ordered->data()[0]['name'])->toBe('Edsger');
    });

    test('list() resolves page and perPage', function () {
        Schema::seedUsers($this->connection->pdo(), [
            ['name' => 'A', 'age' => 1],
            ['name' => 'B', 'age' => 2],
            ['name' => 'C', 'age' => 3],
        ]);

        $page = $this->driver->list($this->resource, pagination: ['page' => 2, 'perPage' => 2]);

        expect($page->count())->toBe(1)
            ->and($page->data()[0]['name'])->toBe('C');
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
            ['name' => 'Edsger', 'age' => 72],
        ]);

        expect($this->driver->total($this->resource))->toBe(3)
            ->and($this->driver->total($this->resource, ['age GTE' => Param::int(40)]))->toBe(2)
            ->and($this->driver->total($this->resource, ['name' => Param::str('Nobody')]))->toBe(0);
    });

    test('rejects create() without data', function () {
        $this->driver->create($this->resource, []);
    })->throws(\STDW\Database\Exception\QueryException::class);

    test('rejects create() with non-param values', function () {
        /** @var mixed $bypass */
        $bypass = ['name' => 'raw'];
        $this->driver->create($this->resource, $bypass);
    })->throws(\STDW\Database\Exception\QueryException::class);

    test('exposes its connection and capabilities', function () {
        expect($this->driver->connection())->toBe($this->connection)
            ->and($this->driver->capabilities()->supportsTransactions())->toBeTrue()
            ->and($this->driver->capabilities()->supportsSearch())->toBeFalse();
    });
