<?php declare(strict_types=1);

    use STDW\Database\Exception\InvalidConfigException;
    use STDW\Database\Exception\QueryTreeException;
    use STDW\Database\Exception\UnsupportedCapabilityException;
    use STDW\Database\Manager\ConnectionFactory;
    use STDW\Database\Manager\ConnectionRegistry;
    use STDW\Database\Manager\DatabaseManager;
    use STDW\Database\Manager\DriverFactory;
    use STDW\Database\Model\ModelAbstracted;
    use STDW\Database\Resource\ResourceMetadata;
    use Tests\Stubs\Models\User;
    use Tests\Stubs\Schema;


    $makeManager = fn (): DatabaseManager => new DatabaseManager(
        connections: ['default' => ['driver' => 'pdo-sqlite', 'database' => ':memory:']],
        connectionFactory: new ConnectionFactory(),
        driverFactory: new DriverFactory(),
        registry: new ConnectionRegistry(),
    );

    $seed = function (DatabaseManager $manager): void {
        Schema::createUsers($manager->connection()->pdo());

        Schema::seedUsers($manager->connection()->pdo(), [
            ['name' => 'Ada', 'email' => 'ada@example.com', 'age' => 36],
            ['name' => 'Grace', 'email' => 'grace@example.com', 'age' => 45],
            ['name' => 'Edsger', 'email' => 'edsger@example.com', 'age' => 72],
        ]);
    };


    test('a model without a declared resource fails at construction', function () use ($makeManager) {
        new class($makeManager()) extends ModelAbstracted { };
    })->throws(InvalidConfigException::class);

    test('create() inserts a record and returns it with the generated id', function () use ($makeManager) {
        $manager = $makeManager();

        Schema::createUsers($manager->connection()->pdo());

        $model = new User($manager);

        $record = $model->create(['name' => 'Ada', 'age' => 36]);

        expect($record['id'])->toBe(1)
            ->and($record['name'])->toBe('Ada')
            ->and($record['age'])->toBe(36);
    });

    test('read() returns a single record or null', function () use ($makeManager, $seed) {
        $manager = $makeManager();
        $seed($manager);
        $model = new User($manager);

        expect($model->read(['name' => 'Grace'])['age'])->toBe(45)
            ->and($model->read(['name' => 'Nobody']))->toBeNull();
    });

    test('list() filters, orders and paginates with raw values', function () use ($makeManager, $seed) {
        $manager = $makeManager();
        $seed($manager);
        $model = new User($manager);

        $rows = $model->list(['age GTE' => 40], ['age' => 'desc'], ['limit' => 1]);

        expect($rows)->toHaveCount(1)
            ->and($rows[0]['name'])->toBe('Edsger');
    });

    test('list() resolves page and perPage', function () use ($makeManager, $seed) {
        $manager = $makeManager();
        $seed($manager);
        $model = new User($manager);

        $rows = $model->list(pagination: ['page' => 2, 'perPage' => 2]);

        expect($rows)->toHaveCount(1)
            ->and($rows[0]['name'])->toBe('Edsger');
    });

    test('list() filters with a clause list and nested groups', function () use ($makeManager, $seed) {
        $manager = $makeManager();
        $seed($manager);
        $model = new User($manager);

        $rows = $model->list([
            ['name' => 'Ada'],
            'OR',
            ['name' => 'Grace'],
        ]);

        expect($rows)->toHaveCount(2);
    });

    test('update() returns the affected count', function () use ($makeManager, $seed) {
        $manager = $makeManager();
        $seed($manager);
        $model = new User($manager);

        $affected = $model->update(['age' => 37], ['name' => 'Ada']);

        expect($affected)->toBe(1)
            ->and($model->read(['name' => 'Ada'])['age'])->toBe(37);
    });

    test('delete() returns the affected count', function () use ($makeManager, $seed) {
        $manager = $makeManager();
        $seed($manager);
        $model = new User($manager);

        $affected = $model->delete(['name' => 'Ada']);

        expect($affected)->toBe(1)
            ->and($model->total())->toBe(2);
    });

    test('total() counts matching records', function () use ($makeManager, $seed) {
        $manager = $makeManager();
        $seed($manager);
        $model = new User($manager);

        expect($model->total())->toBe(3)
            ->and($model->total(['age GTE' => 45]))->toBe(2);
    });

    test('an invalid filter fails fast before the driver', function () use ($makeManager, $seed) {
        $manager = $makeManager();
        $seed($manager);
        $model = new User($manager);

        $model->list(['age WEIRD' => 40]);
    })->throws(QueryTreeException::class);

    test('search() falls back to LIKE across columns', function () use ($makeManager, $seed) {
        $manager = $makeManager();
        $seed($manager);
        $model = new User($manager);

        $rows = $model->search(['name LIKE' => '%ra%']);

        expect($rows)->toHaveCount(1)
            ->and($rows[0]['name'])->toBe('Grace');
    });

    test('stream() yields rows lazily', function () use ($makeManager, $seed) {
        $manager = $makeManager();
        $seed($manager);
        $model = new User($manager);

        $names = [];

        foreach ($model->stream(['age GTE' => 36], ['age' => 'asc']) as $row) {
            $names[] = $row['name'];
        }

        expect($names)->toBe(['Ada', 'Grace', 'Edsger']);
    });

    test('upsert() inserts when absent and updates when present', function () use ($makeManager, $seed) {
        $manager = $makeManager();
        $seed($manager);
        $model = new User($manager);

        $model->upsert(['name' => 'Ada', 'age' => 99], ['name' => 'Ada']);
        $model->upsert(['name' => 'Linus', 'age' => 54], ['name' => 'Linus']);

        expect($model->read(['name' => 'Ada'])['age'])->toBe(99)
            ->and($model->read(['name' => 'Linus']))->not->toBeNull();
    });

    test('insertMany() inserts every row and counts them', function () use ($makeManager) {
        $manager = $makeManager();

        Schema::createUsers($manager->connection()->pdo());

        $model = new User($manager);

        $inserted = $model->insertMany([
            ['name' => 'Ada', 'age' => 36],
            ['name' => 'Grace', 'age' => 45],
        ]);

        expect($inserted)->toBe(2)
            ->and($model->total())->toBe(2);
    });

    test('updateMany() applies each entry and sums the affected rows', function () use ($makeManager, $seed) {
        $manager = $makeManager();
        $seed($manager);
        $model = new User($manager);

        $affected = $model->updateMany([
            ['data' => ['active' => 0], 'where' => ['name' => 'Ada']],
            ['data' => ['active' => 0], 'where' => ['name' => 'Grace']],
        ]);

        expect($affected)->toBe(2);
    });

    test('aggregate() groups and counts', function () use ($makeManager) {
        $manager = $makeManager();
        Schema::createPosts($manager->connection()->pdo());

        Schema::seedPosts($manager->connection()->pdo(), [
            ['user_id' => 1, 'title' => 'A', 'views' => 10],
            ['user_id' => 1, 'title' => 'B', 'views' => 20],
            ['user_id' => 2, 'title' => 'C', 'views' => 5],
        ]);

        $model = new class($manager) extends ModelAbstracted {
            protected string $resource = 'posts';
        };

        $rows = $model->aggregate([
            'aggregate' => ['total_views' => ['sum', 'views'], 'posts' => ['count', '*']],
            'groupBy' => ['user_id'],
            'order' => ['user_id' => 'asc'],
        ]);

        expect($rows)->toHaveCount(2)
            ->and($rows[0]['total_views'])->toBe(30)
            ->and($rows[1]['total_views'])->toBe(5);
    });

    test('transactions commit and rollback through the model', function () use ($makeManager, $seed) {
        $manager = $makeManager();
        $seed($manager);
        $model = new User($manager);

        $model->begin();
        $model->update(['age' => 1], ['name' => 'Ada']);
        $model->rollback();

        expect($model->read(['name' => 'Ada'])['age'])->toBe(36)
            ->and($model->inTransaction())->toBeFalse();

        $model->begin();
        $model->update(['age' => 2], ['name' => 'Ada']);
        $model->commit();

        expect($model->read(['name' => 'Ada'])['age'])->toBe(2);
    });

    test('a driver that lacks a capability rejects it at the model edge', function () use ($makeManager, $seed) {
        $manager = new DatabaseManager(
            connections: ['default' => ['driver' => 'pdo-sqlite', 'database' => ':memory:']],
            connectionFactory: new ConnectionFactory(),
            driverFactory: new DriverFactory([
                'pdo-sqlite' => fn ($connection) => new \STDW\Database\Driver\Sql\Sqlite\SqliteDriver(
                    $connection,
                    new \STDW\Database\Driver\Capabilities(search: false, streaming: false),
                ),
            ]),
            registry: new ConnectionRegistry(),
        );

        $seed($manager);
        $model = new User($manager);

        $model->search(['name LIKE' => '%Ada%']);
    })->throws(UnsupportedCapabilityException::class);

    test('the model metadata drives the upsert conflict columns', function () use ($makeManager) {
        $manager = $makeManager();
        Schema::createUsers($manager->connection()->pdo());
        $model = new User($manager);

        $model->upsert(['name' => 'Ada', 'age' => 36], []);
        $model->upsert(['name' => 'Ada', 'age' => 99], []);

        expect($model->total())->toBe(1)
            ->and($model->read(['name' => 'Ada'])['age'])->toBe(99);
    });
