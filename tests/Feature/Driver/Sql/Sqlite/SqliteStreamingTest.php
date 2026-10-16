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
        Schema::seedUsers($this->connection->pdo(), [
            ['name' => 'Ada', 'age' => 36],
            ['name' => 'Grace', 'age' => 45],
            ['name' => 'Edsger', 'age' => 72],
        ]);
    });


    test('streams rows lazily', function () {
        $names = [];

        foreach ($this->driver->stream($this->resource) as $row) {
            $names[] = $row['name'];
        }

        expect($names)->toBe(['Ada', 'Grace', 'Edsger']);
    });

    test('streams a filtered and ordered subset', function () {
        $names = [];

        foreach ($this->driver->stream(
            $this->resource,
            filter: ['age GTE' => Param::int(40)],
            order: ['age' => \STDW\Contract\Database\QueryTree\Types\OrderDirection::Desc],
        ) as $row) {
            $names[] = $row['name'];
        }

        expect($names)->toBe(['Edsger', 'Grace']);
    });

    test('bounds the stream through pagination', function () {
        $count = 0;

        foreach ($this->driver->stream($this->resource, pagination: ['limit' => 2]) as $row) {
            $count++;
        }

        expect($count)->toBe(2);
    });

    test('streams through the rowid cursor', function () {
        $names = [];

        foreach ($this->driver->stream($this->resource, pagination: ['cursor' => 1]) as $row) {
            $names[] = $row['name'];
        }

        expect($names)->toBe(['Grace', 'Edsger']);
    });

    test('yields an empty iterable when nothing matches', function () {
        $rows = iterator_to_array($this->driver->stream(
            $this->resource,
            filter: ['name' => Param::str('Nobody')],
        ));

        expect($rows)->toBe([]);
    });
