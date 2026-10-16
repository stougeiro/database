<?php declare(strict_types=1);

    namespace STDW\Database\Driver\Sql\Sqlite;

    use STDW\Database\Driver\Pdo\PdoConnection;


    /**
     * SQLite connection: lifecycle only, PDO handle built lazily from the
     * SqliteConfig DSN ("sqlite:<path|:memory:>").
     */
    final class SqliteConnection extends PdoConnection
    {
        /** @param SqliteConfig $config
         */
        public function __construct(
            private readonly SqliteConfig $config,
        ) { }


        /** @return SqliteConfig
         */
        public function config(): SqliteConfig
        {
            return $this->config;
        }


        /** @return string
         */
        protected function dsn(): string
        {
            return $this->config->dsn();
        }
    }
