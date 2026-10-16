<?php declare(strict_types=1);

    use STDW\Database\Driver\Sql\Sqlite\SqliteConfig;
    use STDW\Database\Exception\InvalidConfigException;


    test('defaults to an in-memory database', function () {
        $config = new SqliteConfig();

        expect($config->database())->toBe(':memory:')
            ->and($config->dsn())->toBe('sqlite::memory:')
            ->and($config->all())->toBe(['database' => ':memory:']);
    });

    test('builds a file dsn', function () {
        $config = new SqliteConfig('/tmp/app.sqlite');

        expect($config->dsn())->toBe('sqlite:/tmp/app.sqlite');
    });

    test('builds from a config array', function () {
        $config = SqliteConfig::from(['database' => '/tmp/app.sqlite', 'extra' => true]);

        expect($config->database())->toBe('/tmp/app.sqlite');
    });

    test('rejects an empty database key', function () {
        SqliteConfig::from(['database' => '']);
    })->throws(InvalidConfigException::class);

    test('rejects a non-string database key', function () {
        SqliteConfig::from(['database' => 42]);
    })->throws(InvalidConfigException::class);

    test('rejects non-string keys', function () {
        SqliteConfig::from([0 => 'x']);
    })->throws(InvalidConfigException::class);
