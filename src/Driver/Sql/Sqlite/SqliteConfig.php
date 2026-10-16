<?php declare(strict_types=1);

    namespace STDW\Database\Driver\Sql\Sqlite;

    use STDW\Database\Exception\InvalidConfigException;


    /**
     * Configuration for the SQLite family.
     *
     * Keys:
     *   - database (optional, default ':memory:'): a file path or ':memory:'.
     */
    final readonly class SqliteConfig
    {
        private string $database;

        /**
         * @param string $database
         * @throws InvalidConfigException
         */
        public function __construct(string $database = ':memory:')
        {
            if (trim($database) === '') {
                throw InvalidConfigException::invalid('database');
            }

            $this->database = $database;
        }

        /**
         * @param array<array-key, mixed> $config
         * @return self
         * @throws InvalidConfigException
         */
        public static function from(array $config): self
        {
            foreach (array_keys($config) as $key) {
                if ( ! is_string($key)) {
                    throw InvalidConfigException::invalidKeys();
                }
            }

            /** @var array<string, mixed> $config */
            return new self(self::string($config, 'database'));
        }


        /**
         * @param array<string, mixed> $config
         * @param string $key
         * @return string
         * @throws InvalidConfigException
         */
        private static function string(array $config, string $key): string
        {
            $value = $config[$key] ?? ':memory:';

            if ( ! is_string($value)) {
                throw InvalidConfigException::invalid($key);
            }

            return $value;
        }


        /** @return string
         */
        public function database(): string
        {
            return $this->database;
        }

        /** @return string
         */
        public function dsn(): string
        {
            return 'sqlite:' . $this->database;
        }

        /** @return array<string, mixed>
         */
        public function all(): array
        {
            return ['database' => $this->database];
        }
    }
