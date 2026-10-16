<?php declare(strict_types=1);

    namespace STDW\Database\Manager;

    use STDW\Contract\Database\Connection\DatabaseConnectionInterface;
    use STDW\Contract\Database\Driver\DatabaseDriverInterface;
    use STDW\Contract\Database\Manager\DriverFactoryInterface;
    use STDW\Database\Connection\Sqlite\SqliteConnection;
    use STDW\Database\Driver\Sqlite\SqliteDriver;
    use STDW\Database\Exception\ManagerException;


    /**
     * Creates drivers from a connection already built by the
     * ConnectionFactory. The "driver" alias maps to an implementation:
     * the built-in "pdo-sqlite" alias produces a SqliteDriver; extra aliases
     * can be registered (callable receiveing the connection and the config).
     * The seam for DI: an implementation may delegate to any container.
     */
    final class DriverFactory implements DriverFactoryInterface
    {
        /** @var array<string, callable(DatabaseConnectionInterface, array<string, mixed>): DatabaseDriverInterface>
         */
        private array $resolvers = [];


        /**
         * @param array<array-key, mixed> $resolvers Alias => callable(connection, config).
         * @throws ManagerException
         */
        public function __construct(array $resolvers = [])
        {
            foreach ($resolvers as $alias => $resolver) {
                if ( ! is_string($alias) || ! is_callable($resolver)) {
                    throw ManagerException::unknownDriver((string) $alias);
                }

                $this->resolvers[$alias] = $resolver;
            }
        }


        /**
         * @param string $driver Driver alias (e.g. "pdo-sqlite")
         * @param DatabaseConnectionInterface $connection
         * @param array<string, mixed> $config Driver-level config
         * @return DatabaseDriverInterface
         * @throws ManagerException
         */
        public function make(string $driver, DatabaseConnectionInterface $connection, array $config = []): DatabaseDriverInterface
        {
            if (isset($this->resolvers[$driver])) {
                return ($this->resolvers[$driver])($connection, $config);
            }

            return match ($driver) {
                ConnectionFactory::SQLITE => $this->sqlite($connection),
                default => throw ManagerException::unknownDriver($driver),
            };
        }


        /**
         * @param DatabaseConnectionInterface $connection
         * @return SqliteDriver
         * @throws ManagerException
         */
        private function sqlite(DatabaseConnectionInterface $connection): SqliteDriver
        {
            if ( ! $connection instanceof SqliteConnection) {
                throw ManagerException::invalidConnectionClass(ConnectionFactory::SQLITE);
            }

            return new SqliteDriver($connection);
        }
    }
