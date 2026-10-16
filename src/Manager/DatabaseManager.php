<?php declare(strict_types=1);

    namespace STDW\Database\Manager;

    use STDW\Contract\Database\Connection\DatabaseConnectionInterface;
    use STDW\Contract\Database\Driver\DatabaseDriverInterface;
    use STDW\Contract\Database\Manager\ConnectionFactoryInterface;
    use STDW\Contract\Database\Manager\ConnectionRegistryInterface;
    use STDW\Contract\Database\Manager\DatabaseManagerInterface;
    use STDW\Contract\Database\Manager\DriverFactoryInterface;
    use STDW\Database\Exception\ManagerException;


    /**
     * Orchestrates drivers and connections.
     *
     * The named-connection configuration is provided at construction time
     * (e.g. from config/database.php); it is never registered at runtime.
     * connection() builds via the ConnectionFactory and caches in the
     * ConnectionRegistry; driver() hands that connection to the
     * DriverFactory and caches the driver per connection name. Both accept
     * null for the "default" connection. The manager never knows about the
     * query tree and never orchestrates transactions (that is a driver
     * capability).
     */
    final class DatabaseManager implements DatabaseManagerInterface
    {
        /** @var string
         */
        public const DEFAULT = 'default';

        /** @var array<string, DatabaseDriverInterface>
         */
        private array $drivers = [];

        /**
         * @param array<string, array<string, mixed>> $connections Named configs; each declares a "driver" alias.
         * @param ConnectionFactoryInterface $connectionFactory
         * @param DriverFactoryInterface $driverFactory
         * @param ConnectionRegistryInterface $registry
         */
        public function __construct(
            private readonly array $connections,
            private readonly ConnectionFactoryInterface $connectionFactory,
            private readonly DriverFactoryInterface $driverFactory,
            private readonly ConnectionRegistryInterface $registry,
        ) { }


        /**
         * @param string|null $name Connection name; null for default.
         * @return DatabaseConnectionInterface
         * @throws ManagerException
         */
        public function connection(?string $name = null): DatabaseConnectionInterface
        {
            $name = $this->name($name);

            if ($this->registry->has($name)) {
                return $this->registry->get($name);
            }

            $connection = $this->connectionFactory->make($this->config($name));
            $connection->connect();
            $this->registry->add($name, $connection);

            return $connection;
        }

        /**
         * @param string|null $name Connection name whose driver to build; null for default.
         * @return DatabaseDriverInterface
         * @throws ManagerException
         */
        public function driver(?string $name = null): DatabaseDriverInterface
        {
            $name = $this->name($name);

            if (isset($this->drivers[$name])) {
                return $this->drivers[$name];
            }

            $config = $this->config($name);
            $alias = $config['driver'] ?? null;

            if ( ! is_string($alias)) {
                throw ManagerException::missingDriver($name);
            }

            return $this->drivers[$name] = $this->driverFactory->make($alias, $this->connection($name), $config);
        }

        /**
         * @param string|null $name Connection name; null for default.
         * @return void
         * @throws ManagerException
         */
        public function disconnect(?string $name = null): void
        {
            $name = $this->name($name);

            if ($this->registry->has($name)) {
                $this->registry->get($name)->disconnect();
                $this->registry->remove($name);
            }

            unset($this->drivers[$name]);
        }

        /**
         * @return void
         * @throws ManagerException
         */
        public function purge(): void
        {
            foreach ($this->registry->all() as $name => $connection) {
                $connection->disconnect();
                $this->registry->remove($name);
            }

            $this->drivers = [];
        }


        /**
         * @param string $name
         * @return array<string, mixed>
         * @throws ManagerException
         */
        private function config(string $name): array
        {
            if ( ! isset($this->connections[$name])) {
                throw ManagerException::unknownConnection($name);
            }

            return $this->connections[$name];
        }

        /**
         * @param null|string $name
         * @return string
         */
        private function name(?string $name): string
        {
            return $name ?? self::DEFAULT;
        }
    }
