<?php declare(strict_types=1);

    use STDW\Database\Driver\Sql\Sqlite\SqliteConfig;
    use STDW\Database\Driver\Sql\Sqlite\SqliteConnection;
    use STDW\Database\Driver\Sql\Sqlite\SqliteDriver;
    use STDW\Database\Param\Param;
    use STDW\Database\Resource\Resource;
    use STDW\Database\Resource\ResourceMetadata;
    use Tests\Stubs\Schema;


    beforeEach(function () {
        $this->connection = new SqliteConnection(new SqliteConfig());
        $this->driver = new SqliteDriver($this->connection);

        Schema::createUsers($this->connection->pdo());
        Schema::createDocsFts($this->connection->pdo());

        Schema::seedUsers($this->connection->pdo(), [
            ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'],
            ['name' => 'Grace Hopper', 'email' => 'grace@example.com'],
            ['name' => 'Edsger Dijkstra', 'email' => 'edsger@example.com'],
        ]);

        Schema::seedDocsFts($this->connection->pdo(), [
            ['title' => 'Design Patterns', 'body' => 'A catalog of reusable object-oriented solutions'],
            ['title' => 'Database Internals', 'body' => 'How storage engines really work'],
            ['title' => 'Clean Code', 'body' => 'A handbook of agile software craftsmanship'],
        ]);
    });


    test('searches an FTS5 table with MATCH', function () {
        $docs = new Resource('docs');

        $result = $this->driver->search($docs, ['title LIKE' => Param::str('%patterns%')]);

        expect($result->count())->toBe(1)
            ->and($result->data()[0]['title'])->toBe('Design Patterns');
    });

    test('searches FTS5 across all indexed columns via an EQ term', function () {
        $docs = new Resource('docs');

        $result = $this->driver->search($docs, ['body' => Param::str('storage engines')]);

        expect($result->count())->toBe(1)
            ->and($result->data()[0]['title'])->toBe('Database Internals');
    });

    test('combines multiple FTS terms with AND', function () {
        $docs = new Resource('docs');

        $result = $this->driver->search($docs, [
            'title LIKE' => Param::str('%code%'),
            'body LIKE' => Param::str('%agile%'),
        ]);

        expect($result->count())->toBe(1)
            ->and($result->data()[0]['title'])->toBe('Clean Code');
    });

    test('falls back to LIKE across columns on regular tables', function () {
        $users = new Resource('users', new ResourceMetadata(fields: ['name' => 'string', 'email' => 'string']));

        $result = $this->driver->search($users, ['name LIKE' => Param::str('%hop%')]);

        expect($result->count())->toBe(1)
            ->and($result->data()[0]['name'])->toBe('Grace Hopper');
    });

    test('the LIKE fallback matches any column', function () {
        $users = new Resource('users', new ResourceMetadata(fields: ['name' => 'string', 'email' => 'string']));

        $result = $this->driver->search($users, ['name LIKE' => Param::str('%edsger@%')]);

        expect($result->count())->toBe(1)
            ->and($result->data()[0]['name'])->toBe('Edsger Dijkstra');
    });

    test('the LIKE fallback detects columns through PRAGMA without metadata', function () {
        $users = new Resource('users');

        $result = $this->driver->search($users, ['name LIKE' => Param::str('%ada%')]);

        expect($result->count())->toBe(1)
            ->and($result->data()[0]['name'])->toBe('Ada Lovelace');
    });

    test('returns an empty result when nothing matches', function () {
        $users = new Resource('users');

        $result = $this->driver->search($users, ['name LIKE' => Param::str('%nobody%')]);

        expect($result->count())->toBe(0)
            ->and($result->data())->toBe([]);
    });
