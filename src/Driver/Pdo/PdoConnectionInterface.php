<?php declare(strict_types=1);

    namespace STDW\Database\Driver\Pdo;

    use PDO;
    use STDW\Contract\Database\Connection\DatabaseConnectionInterface;


    /**
     * PDO family sub-interface: the typed native handle. The generic
     * DatabaseConnectionInterface never exposes an untyped handle — a driver
     * family narrows the access through its own sub-interface, e.g. this one
     * exposing the underlying PDO instance.
     */
    interface PdoConnectionInterface extends DatabaseConnectionInterface
    {
        /** The live PDO handle. Connects lazily when needed.
         *
         * @return PDO
         */
        public function pdo(): PDO;
    }
