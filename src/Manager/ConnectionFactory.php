<?php declare(strict_types=1);

    namespace STDW\Database\Manager;

    use STDW\Contract\Database\Connection\DatabaseConnectionInterface;
    use STDW\Contract\Database\Manager\ConnectionFactoryInterface;
    use STDW\Database\Driver\Sql\Sqlite\SqliteConfig;
    use STDW\Database\Driver\Sql\Sqlite\SqliteConnection;
    use STDW\Database\Exception\InvalidConfigException;
    use STDW\Database\Exception\ManagerException;


    /**
     * Creates connections from a config array. The "driver" key selects the
     * family (e.g. "pdo-sqlite" => SQLite over PDO); the remaining keys belong
     * to that family's config. The seam for DI: an implementation may
     * delegate to any container.
     */
    final class ConnectionFactory implements ConnectionFactoryInterface
    {
        /** @var string
         */
        public const SQLITE = 'pdo-sqlite';


        /**
         * @param array<string, mixed> $config
         * @return DatabaseConnectionInterface
         * @throws ManagerException
         */
        public function make(array $config): DatabaseConnectionInterface
        {
            $driver = $config['driver'] ?? null;

            if ( ! is_string($driver)) {
                throw ManagerException::missingDriver('given');
            }

            try {
                return match ($driver) {
                    self::SQLITE => new SqliteConnection(SqliteConfig::from($config)),
                    default => throw ManagerException::unknownDriver($driver),
                };
            } catch (InvalidConfigException $exception) {
                throw new ManagerException($exception->getMessage(), (int) $exception->getCode(), $exception);
            }
        }
    }
