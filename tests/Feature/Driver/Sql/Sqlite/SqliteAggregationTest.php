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
        $this->resource = new Resource('posts');

        Schema::createPosts($this->connection->pdo());

        Schema::seedPosts($this->connection->pdo(), [
            ['user_id' => 1, 'title' => 'Intro to SQL', 'views' => 10],
            ['user_id' => 1, 'title' => 'Advanced SQL', 'views' => 30],
            ['user_id' => 2, 'title' => 'PHP Tips', 'views' => 50],
            ['user_id' => 2, 'title' => 'PHP Internals', 'views' => 70],
        ]);
    });


    test('aggregates without grouping', function () {
        $result = $this->driver->aggregate($this->resource, [
            'aggregate' => [
                'total_views' => ['sum', 'views'],
                'posts' => ['count', '*'],
            ],
        ]);

        expect($result->count())->toBe(1)
            ->and($result->data()[0]['total_views'])->toBe(160)
            ->and((int) $result->data()[0]['posts'])->toBe(4);
    });

    test('aggregates with groupBy', function () {
        $result = $this->driver->aggregate($this->resource, [
            'groupBy' => ['user_id'],
            'aggregate' => [
                'user_id' => ['max', 'user_id'],
                'total' => ['sum', 'views'],
            ],
            'order' => ['user_id' => \STDW\Contract\Database\QueryTree\Types\OrderDirection::Asc],
        ]);

        expect($result->count())->toBe(2)
            ->and($result->data()[0]['total'])->toBe(40)
            ->and($result->data()[1]['total'])->toBe(120);
    });

    test('applies where and having', function () {
        $result = $this->driver->aggregate($this->resource, [
            'where' => ['views GTE' => Param::int(30)],
            'groupBy' => ['user_id'],
            'aggregate' => [
                'user_id' => ['max', 'user_id'],
                'total' => ['sum', 'views'],
            ],
            'having' => ['total GT' => Param::int(50)],
        ]);

        expect($result->count())->toBe(1)
            ->and($result->data()[0]['user_id'])->toBe(2)
            ->and($result->data()[0]['total'])->toBe(120);
    });

    test('honors the limit stage', function () {
        $result = $this->driver->aggregate($this->resource, [
            'groupBy' => ['user_id'],
            'aggregate' => [
                'user_id' => ['max', 'user_id'],
                'total' => ['sum', 'views'],
            ],
            'order' => ['total' => \STDW\Contract\Database\QueryTree\Types\OrderDirection::Desc],
            'limit' => 1,
        ]);

        expect($result->count())->toBe(1)
            ->and($result->data()[0]['total'])->toBe(120);
    });

    test('supports min, avg and max', function () {
        $result = $this->driver->aggregate($this->resource, [
            'aggregate' => [
                'lowest' => ['min', 'views'],
                'highest' => ['max', 'views'],
                'average' => ['avg', 'views'],
            ],
        ]);

        expect($result->data()[0]['lowest'])->toBe(10)
            ->and($result->data()[0]['highest'])->toBe(70)
            ->and((float) $result->data()[0]['average'])->toBe(40.0);
    });

    test('returns an empty result for a native stage', function () {
        $result = $this->driver->aggregate($this->resource, [
            'native' => ['raw'],
            'aggregate' => ['x' => ['count', '*']],
        ]);

        expect($result->count())->toBe(0)
            ->and($result->data())->toBe([]);
    });

    test('returns an empty result for unknown functions', function () {
        $result = $this->driver->aggregate($this->resource, [
            'aggregate' => ['x' => ['median', 'views']],
        ]);

        expect($result->count())->toBe(0);
    });
