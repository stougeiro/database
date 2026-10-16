<?php declare(strict_types=1);

    namespace STDW\Database\Exception;

    use STDW\Contract\Database\Exception\ManagerExceptionInterface;


    /**
     * Orchestration failures: unknown connections, unknown driver aliases,
     * factory build failures.
     */
    final class ManagerException extends DatabaseException implements ManagerExceptionInterface
    {
        /**
         * @param string $name
         * @return self
         */
        public static function unknownConnection(string $name): self
        {
            return new self(sprintf('Connection "%s" is not defined in the manager configuration', $name));
        }

        /**
         * @param string $alias
         * @return self
         */
        public static function unknownDriver(string $alias): self
        {
            return new self(sprintf('Driver alias "%s" is not registered in the driver factory', $alias));
        }

        /**
         * @param string $name
         * @return self
         */
        public static function missingDriver(string $name): self
        {
            return new self(sprintf('Connection "%s" does not declare a "driver" alias in its configuration', $name));
        }

        /**
         * @param string $driver
         * @return self
         */
        public static function invalidConnectionClass(string $driver): self
        {
            return new self(sprintf('The connection built for driver "%s" is incompatible with the requested family', $driver));
        }
    }
