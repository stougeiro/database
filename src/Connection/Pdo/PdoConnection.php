<?php declare(strict_types=1);

    namespace STDW\Database\Connection\Pdo;

    use PDO;
    use PDOException;
    use STDW\Database\Connection\Pdo\Spec\PdoConnectionInterface;
    use STDW\Database\Exception\ConnectionException;


    /**
     * PDO-backed connection: lifecycle only.
     *
     * The PDO handle is created lazily from the subclass-provided DSN, so the
     * driver can call pdo() at any time without a prior connect() call.
     * connect() is idempotent; disconnect() closes the handle (a later pdo()
     * call reconnects). Subclasses may override attributes() to tweak the
     * PDO behaviour per dialect.
     */
    abstract class PdoConnection implements PdoConnectionInterface
    {
        private ?PDO $pdo = null;


        /** The PDO data-source name, e.g. "sqlite::memory:".
         *
         * @return string
         */
        abstract protected function dsn(): string;

        /** PDO attributes applied on every fresh handle.
         *
         * @return array<int, mixed>
         */
        protected function attributes(): array
        {
            return [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];
        }


        /** Opens the physical connection. Idempotent.
         *
         * @return void
         * @throws ConnectionException
         */
        public function connect(): void
        {
            $this->pdo();
        }

        /** @return bool
         */
        public function isConnected(): bool
        {
            return $this->pdo instanceof PDO;
        }

        /** @return void
         */
        public function disconnect(): void
        {
            $this->pdo = null;
        }

        /** @return PDO
         */
        public function pdo(): PDO
        {
            if ($this->pdo instanceof PDO) {
                return $this->pdo;
            }

            try {
                $pdo = new PDO($this->dsn());
            } catch (PDOException $exception) {
                throw ConnectionException::failed($this->dsn(), $exception);
            }

            foreach ($this->attributes() as $attribute => $value) {
                $pdo->setAttribute($attribute, $value);
            }

            return $this->pdo = $pdo;
        }
    }
