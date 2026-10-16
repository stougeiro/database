<?php declare(strict_types=1);

    namespace STDW\Database\Manager;

    use STDW\Contract\Database\Connection\DatabaseConnectionInterface;
    use STDW\Contract\Database\Manager\ConnectionRegistryInterface;
    use STDW\Database\Exception\ManagerException;


    /**
     * Stores and retrieves open connections by name (the live pool).
     * Construction is the factory's job; this registry only caches.
     */
    final class ConnectionRegistry implements ConnectionRegistryInterface
    {
        /** @var array<string, DatabaseConnectionInterface>
         */
        private array $connections = [];


        /**
         * @param string $name
         * @param DatabaseConnectionInterface $connection
         * @return void
         */
        public function add(string $name, DatabaseConnectionInterface $connection): void
        {
            $this->connections[$name] = $connection;
        }

        /**
         * @param string $name
         * @return DatabaseConnectionInterface
         * @throws ManagerException
         */
        public function get(string $name): DatabaseConnectionInterface
        {
            if ( ! isset($this->connections[$name])) {
                throw ManagerException::unknownConnection($name);
            }

            return $this->connections[$name];
        }

        /**
         * @param string $name
         * @return bool
         */
        public function has(string $name): bool
        {
            return isset($this->connections[$name]);
        }

        /** @return array<string, DatabaseConnectionInterface>
         */
        public function all(): array
        {
            return $this->connections;
        }

        /**
         * @param string $name
         * @return void
         */
        public function remove(string $name): void
        {
            unset($this->connections[$name]);
        }
    }
