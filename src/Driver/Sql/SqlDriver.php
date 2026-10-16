<?php declare(strict_types=1);

    namespace STDW\Database\Driver\Sql;

    use PDO;
    use PDOException;
    use PDOStatement;
    use STDW\Contract\Database\Connection\DatabaseConnectionInterface;
    use STDW\Contract\Database\Driver\DatabaseDriverInterface;
    use STDW\Contract\Database\Driver\DriverCapabilitiesInterface;
    use STDW\Contract\Database\Param\ParamInterface;
    use STDW\Contract\Database\Param\Types\ParamType;
    use STDW\Contract\Database\QueryTree\Types\OrderDirection;
    use STDW\Contract\Database\Resource\DatabaseResourceInterface;
    use STDW\Contract\Database\Result\ResultInterface;
    use STDW\Database\Driver\Pdo\PdoConnectionInterface;
    use STDW\Database\Exception\DriverException;
    use STDW\Database\Exception\QueryException;
    use STDW\Database\Result\Result;


    /**
     * PDO-backed base for the SQL dialects.
     *
     * Values crossing the boundary must be typed Params; the base maps their
     * ParamType onto PDO binding constants and never interpolates a value
     * into SQL. Dialects provide identifier quoting (quote()) and may hook
     * lastInsertId(), cursorClause() and orderDirection()/pagination for
     * engine specifics. Ordering and pagination (limit/offset) are baseline;
     * cursor pagination takes precedence when present and is resolved by the
     * dialect-specific cursorClause() hook (ignored when it returns null).
     */
    abstract class SqlDriver implements DatabaseDriverInterface
    {
        /**
         * @param PdoConnectionInterface $connection
         * @param DriverCapabilitiesInterface $capabilities
         */
        public function __construct(
            protected readonly PdoConnectionInterface $connection,
            protected readonly DriverCapabilitiesInterface $capabilities,
        ) { }


        /** @return PdoConnectionInterface
         */
        public function connection(): PdoConnectionInterface
        {
            return $this->connection;
        }

        /** @return DriverCapabilitiesInterface
         */
        public function capabilities(): DriverCapabilitiesInterface
        {
            return $this->capabilities;
        }


        /** Quotes an identifier (column/resource) for the dialect.
         *
         * @param string $identifier
         * @return string
         */
        abstract protected function quote(string $identifier): string;

        /** Last auto-increment id of the connection, or null when not
         * available for the dialect/engine.
         *
         * @return null|string
         */
        protected function lastInsertId(): ?string
        {
            $id = $this->pdo()->lastInsertId();

            return $id === false || $id === '0' ? null : $id;
        }

        /**
         * Compiles a keyset cursor into an extra WHERE fragment. Returns null
         * when the cursor is not understood — the cursor is then ignored.
         *
         * @param mixed $cursor
         * @param array<string, OrderDirection> $order
         * @param array<int, ParamInterface> $params
         * @return null|string
         */
        protected function cursorClause(mixed $cursor, array $order, array &$params): ?string
        {
            return null;
        }


        /**
         * @param DatabaseResourceInterface $resource
         * @param array<string, ParamInterface> $data
         * @return ResultInterface
         * @throws QueryException
         */
        public function create(DatabaseResourceInterface $resource, array $data): ResultInterface
        {
            if ($data === []) {
                throw QueryException::invalid('cannot create a record without any data');
            }

            $columns = [];
            $placeholders = [];
            $record = [];
            $params = [];

            foreach ($data as $field => $value) {
                $param = $this->param((string) $field, $value);

                $columns[] = $this->quote((string) $field);
                $placeholders[] = '?';
                $record[$field] = $param->value();
                $params[] = $param;
            }

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $this->quote($resource->name()),
                implode(', ', $columns),
                implode(', ', $placeholders),
            );

            $this->execute($sql, $params);

            $id = $this->lastInsertId();

            if ($id !== null && ! array_key_exists('id', $record)) {
                $record['id'] = is_numeric($id) ? (int) $id : $id;
            }

            return Result::make($record, 1);
        }

        /**
         * @param DatabaseResourceInterface $resource
         * @param array<array-key, mixed> $filter
         * @return ResultInterface
         * @throws QueryException
         */
        public function read(DatabaseResourceInterface $resource, array $filter = []): ResultInterface
        {
            $compiled = Conditions::compile($filter, $this->quote(...));

            $sql = 'SELECT * FROM ' . $this->quote($resource->name());

            if ($compiled['sql'] !== '') {
                $sql .= ' WHERE ' . $compiled['sql'];
            }

            $sql .= ' LIMIT 1';

            $rows = $this->fetchAll($sql, $compiled['params']);

            if ($rows === []) {
                return Result::make(null, 0);
            }

            /** @var array<int|string, mixed> $row */
            $row = $rows[0];

            return Result::make($row, 1);
        }

        /**
         * @param DatabaseResourceInterface $resource
         * @param array<array-key, mixed> $filter
         * @param array<string, OrderDirection> $order
         * @param array<string, mixed> $pagination
         * @return ResultInterface
         * @throws QueryException
         */
        public function list(DatabaseResourceInterface $resource, array $filter = [], array $order = [], array $pagination = []): ResultInterface
        {
            $select = $this->selectSql($resource, $filter, $order, $pagination);
            $rows = $this->fetchAll($select['sql'], $select['params']);

            return Result::make($rows, count($rows));
        }

        /**
         * @param DatabaseResourceInterface $resource
         * @param array<string, ParamInterface> $data
         * @param array<array-key, mixed> $filter
         * @return ResultInterface
         * @throws QueryException
         */
        public function update(DatabaseResourceInterface $resource, array $data, array $filter): ResultInterface
        {
            if ($data === []) {
                throw QueryException::invalid('cannot update a record without any data');
            }

            $sets = [];
            $params = [];

            foreach ($data as $field => $value) {
                $params[] = $this->param((string) $field, $value);
                $sets[] = $this->quote((string) $field) . ' = ?';
            }

            $compiled = Conditions::compile($filter, $this->quote(...));

            $sql = sprintf(
                'UPDATE %s SET %s',
                $this->quote($resource->name()),
                implode(', ', $sets),
            );

            if ($compiled['sql'] !== '') {
                $sql .= ' WHERE ' . $compiled['sql'];
                $params = [...$params, ...$compiled['params']];
            }

            $affected = $this->execute($sql, $params)->rowCount();

            return Result::make(null, $affected);
        }

        /**
         * @param DatabaseResourceInterface $resource
         * @param array<array-key, mixed> $filter
         * @return ResultInterface
         * @throws QueryException
         */
        public function delete(DatabaseResourceInterface $resource, array $filter): ResultInterface
        {
            $compiled = Conditions::compile($filter, $this->quote(...));

            $sql = 'DELETE FROM ' . $this->quote($resource->name());

            if ($compiled['sql'] !== '') {
                $sql .= ' WHERE ' . $compiled['sql'];
            }

            $affected = $this->execute($sql, $compiled['params'])->rowCount();

            return Result::make(null, $affected);
        }

        /**
         * @param DatabaseResourceInterface $resource
         * @param array<array-key, mixed> $filter
         * @return int
         * @throws QueryException
         */
        public function total(DatabaseResourceInterface $resource, array $filter = []): int
        {
            $compiled = Conditions::compile($filter, $this->quote(...));

            $sql = 'SELECT COUNT(*) AS aggregate FROM ' . $this->quote($resource->name());

            if ($compiled['sql'] !== '') {
                $sql .= ' WHERE ' . $compiled['sql'];
            }

            $rows = $this->fetchAll($sql, $compiled['params']);

            /** @var mixed $count */
            $count = $rows[0]['aggregate'] ?? 0;

            return is_numeric($count) ? (int) $count : 0;
        }


        /**
         * Builds the full SELECT for list(), search() and friends, resolving
         * the cursor through the dialect hook when present.
         *
         * @param DatabaseResourceInterface $resource
         * @param array<array-key, mixed> $filter
         * @param array<string, OrderDirection> $order
         * @param array<string, mixed> $pagination
         * @param string $columns
         * @return array{sql: string, params: array<int, ParamInterface>}
         * @throws QueryException
         */
        protected function selectSql(
            DatabaseResourceInterface $resource,
            array $filter,
            array $order,
            array $pagination,
            string $columns = '*',
        ): array {
            $compiled = Conditions::compile($filter, $this->quote(...));

            $cursorParams = [];
            $cursorSql = null;

            if (isset($pagination['cursor'])) {
                $cursorSql = $this->cursorClause($pagination['cursor'], $order, $cursorParams);
            }

            $sql = sprintf('SELECT %s FROM %s', $columns, $this->quote($resource->name()));

            $fragments = [];

            if ($compiled['sql'] !== '') {
                $fragments[] = '(' . $compiled['sql'] . ')';
            }

            if ($cursorSql !== null && $cursorSql !== '') {
                $fragments[] = '(' . $cursorSql . ')';
            }

            if ($fragments !== []) {
                $sql .= ' WHERE ' . implode(' AND ', $fragments);
            }

            $sql .= $this->orderSql($order, $pagination);
            $sql .= $this->paginationSql($pagination);

            return ['sql' => $sql, 'params' => [...$compiled['params'], ...$cursorParams]];
        }

        /**
         * @param array<string, OrderDirection> $order
         * @param array<string, mixed> $pagination
         * @return string
         */
        protected function orderSql(array $order, array $pagination = []): string
        {
            $clauses = [];

            foreach ($order as $column => $direction) {
                $clauses[] = $this->quote((string) $column) . ' ' . strtoupper($direction->value);
            }

            return $clauses === [] ? '' : ' ORDER BY ' . implode(', ', $clauses);
        }

        /**
         * @param array<string, mixed> $pagination
         * @return string
         */
        protected function paginationSql(array $pagination): string
        {
            if (isset($pagination['cursor'])) {
                return '';
            }

            $limit = null;
            $offset = null;

            if (isset($pagination['limit']) && is_int($pagination['limit'])) {
                $limit = $pagination['limit'];
            } elseif (isset($pagination['page'], $pagination['perPage']) && is_int($pagination['page']) && is_int($pagination['perPage'])) {
                $limit = $pagination['perPage'];
                $offset = ($pagination['page'] - 1) * $pagination['perPage'];
            }

            if ($offset === null && isset($pagination['offset']) && is_int($pagination['offset'])) {
                $offset = $pagination['offset'];
            }

            $sql = '';

            if ($limit !== null) {
                $sql .= ' LIMIT ' . $limit;
            }

            if ($offset !== null && $offset > 0) {
                $sql .= ' OFFSET ' . $offset;
            }

            return $sql;
        }

        /**
         * @param string $sql
         * @param array<array-key, mixed> $params
         * @return array<int, array<int|string, mixed>>
         * @throws QueryException
         */
        protected function fetchAll(string $sql, array $params = []): array
        {
            return $this->execute($sql, $params)->fetchAll();
        }

        /**
         * @param string $sql
         * @param array<array-key, mixed> $params
         * @return PDOStatement
         * @throws QueryException
         */
        protected function execute(string $sql, array $params = []): PDOStatement
        {
            try {
                $statement = $this->pdo()->prepare($sql);
                self::bind($statement, $params);
                $statement->execute();
            } catch (PDOException $exception) {
                throw QueryException::executionFailed($sql, $exception);
            }

            return $statement;
        }

        /**
         * @return PDO
         */
        protected function pdo(): PDO
        {
            return $this->connection->pdo();
        }

        /**
         * @param array<array-key, mixed> $data
         * @return array<int, ParamInterface>
         * @throws QueryException
         */
        protected function params(array $data): array
        {
            $params = [];

            foreach ($data as $field => $value) {
                $params[] = $this->param((string) $field, $value);
            }

            return $params;
        }

        /**
         * @param string $field
         * @param mixed $value
         * @return ParamInterface
         * @throws QueryException
         */
        protected function param(string $field, mixed $value): ParamInterface
        {
            if ( ! $value instanceof ParamInterface) {
                throw QueryException::parameterRequired($field);
            }

            return $value;
        }

        /**
         * @param PDOStatement $statement
         * @param array<array-key, mixed> $params
         * @return void
         */
        private static function bind(PDOStatement $statement, array $params): void
        {
            $position = 1;

            foreach ($params as $param) {
                /** @var ParamInterface $param */
                $statement->bindValue($position, $param->value(), self::pdoType($param->type()));
                $position++;
            }
        }

        /** @return int
         */
        private static function pdoType(ParamType $type): int
        {
            return match ($type) {
                ParamType::Null => PDO::PARAM_NULL,
                ParamType::Bool => PDO::PARAM_BOOL,
                ParamType::Int => PDO::PARAM_INT,
                default => PDO::PARAM_STR,
            };
        }
    }
