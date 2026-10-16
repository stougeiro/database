<?php declare(strict_types=1);

    namespace STDW\Database\Driver\Sqlite;

    use PDOException;
    use STDW\Contract\Database\Driver\Capability\AggregationInterface;
    use STDW\Contract\Database\Driver\Capability\BatchInterface;
    use STDW\Contract\Database\Driver\Capability\SearchInterface;
    use STDW\Contract\Database\Driver\Capability\StreamingInterface;
    use STDW\Contract\Database\Driver\Capability\TransactionInterface;
    use STDW\Contract\Database\Driver\Capability\UpsertInterface;
    use STDW\Contract\Database\Driver\DriverCapabilitiesInterface;
    use STDW\Contract\Database\Param\ParamInterface;
    use STDW\Contract\Database\QueryTree\Types\OrderDirection;
    use STDW\Contract\Database\Resource\DatabaseResourceInterface;
    use STDW\Contract\Database\Resource\ResourceMetadataAwareInterface;
    use STDW\Contract\Database\Result\ResultInterface;
    use STDW\Database\Connection\Sqlite\SqliteConnection;
    use STDW\Database\Driver\Capabilities;
    use STDW\Database\Driver\Sql\Conditions;
    use STDW\Database\Driver\Sql\SqlDriver;
    use STDW\Database\Exception\DriverException;
    use STDW\Database\Exception\QueryException;
    use STDW\Database\Param\Param;
    use STDW\Database\Result\Result;
    use Throwable;


    /**
     * SQLite driver (PDO).
     *
     * Honors every capability of the contract:
     * - transactions: PDO transactional control;
     * - batch: insertMany/updateMany applied independently inside a
     *   transaction when available (count() = inserted/affected total);
     * - upsert: INSERT ... ON CONFLICT (...) DO UPDATE; conflict columns come
     *   from the filter's EQ conditions, falling back to the resource
     *   metadata primary key;
     * - streaming: lazy generator over the prepared statement;
     * - aggregation: neutral pipeline (where/groupBy/aggregate/having/order/
     *   limit) compiled to GROUP BY SQL; the opaque "native" stage yields an
     *   empty result;
     * - search: FTS5 MATCH when the resource is an FTS5 virtual table
     *   (text terms come from LIKE/EQ string criteria; structured criteria
     *   are applied as plain predicates), otherwise a case-insensitive LIKE
     *   fallback across the resource columns (metadata fields, or PRAGMA
     *   table_info when metadata is absent).
     *
     * Cursor pagination (list/stream/search) maps to a rowid keyset:
     * cursor = last seen rowid; the result set is ordered by rowid ASC and
     * filtered by rowid > cursor.
     */
    final class SqliteDriver extends SqlDriver implements
        TransactionInterface,
        BatchInterface,
        SearchInterface,
        StreamingInterface,
        UpsertInterface,
        AggregationInterface
    {
        /**
         * @param SqliteConnection $connection
         * @param null|DriverCapabilitiesInterface $capabilities
         */
        public function __construct(
            SqliteConnection $connection,
            ?DriverCapabilitiesInterface $capabilities = null,
        ) {
            parent::__construct($connection, $capabilities ?? new Capabilities(
                aggregation: true,
                batch: true,
                search: true,
                streaming: true,
                transactions: true,
                upsert: true,
            ));
        }


        /** @return SqliteConnection
         */
        public function connection(): SqliteConnection
        {
            /** @var SqliteConnection $connection */
            $connection = $this->connection;

            return $connection;
        }


        /**
         * @param string $identifier
         * @return string
         */
        protected function quote(string $identifier): string
        {
            return '"' . str_replace('"', '""', $identifier) . '"';
        }

        /**
         * Keyset pagination on the SQLite rowid.
         *
         * @param mixed $cursor
         * @param array<string, OrderDirection> $order
         * @param array<int, ParamInterface> $params
         * @return null|string
         */
        protected function cursorClause(mixed $cursor, array $order, array &$params): ?string
        {
            if (is_int($cursor) || (is_string($cursor) && is_numeric($cursor))) {
                $params[] = Param::int((int) $cursor);

                return 'rowid > ?';
            }

            return null;
        }

        /**
         * @param array<string, OrderDirection> $order
         * @param array<string, mixed> $pagination
         * @return string
         */
        protected function orderSql(array $order, array $pagination = []): string
        {
            if ($order === [] && isset($pagination['cursor'])) {
                return ' ORDER BY rowid ASC';
            }

            return parent::orderSql($order, $pagination);
        }


        /**
         * @return void
         * @throws DriverException
         */
        public function begin(): void
        {
            try {
                $this->pdo()->beginTransaction();
            } catch (PDOException $exception) {
                throw DriverException::transactionFailed('begin', $exception);
            }
        }

        /**
         * @return void
         * @throws DriverException
         */
        public function commit(): void
        {
            try {
                $this->pdo()->commit();
            } catch (PDOException $exception) {
                throw DriverException::transactionFailed('commit', $exception);
            }
        }

        /**
         * @return void
         * @throws DriverException
         */
        public function rollback(): void
        {
            try {
                $this->pdo()->rollBack();
            } catch (PDOException $exception) {
                throw DriverException::transactionFailed('rollback', $exception);
            }
        }

        /** @return bool
         */
        public function inTransaction(): bool
        {
            return $this->pdo()->inTransaction();
        }


        /**
         * @param DatabaseResourceInterface $resource
         * @param array<int, array<string, ParamInterface>> $rows
         * @return ResultInterface
         * @throws QueryException
         */
        public function insertMany(DatabaseResourceInterface $resource, array $rows): ResultInterface
        {
            $total = 0;
            $started = $this->startTransaction();

            try {
                foreach ($rows as $row) {
                    $total += $this->create($resource, $row)->count();
                }
            } catch (Throwable $throwable) {
                $this->abortTransaction($started);

                throw $throwable;
            }

            $this->commitTransaction($started);

            return Result::make(null, $total);
        }

        /**
         * @param DatabaseResourceInterface $resource
         * @param array<int, array{data: array<string, ParamInterface>, where: array<string, ParamInterface>}> $rows
         * @return ResultInterface
         * @throws QueryException
         */
        public function updateMany(DatabaseResourceInterface $resource, array $rows): ResultInterface
        {
            $total = 0;
            $started = $this->startTransaction();

            try {
                foreach ($rows as $row) {
                    $total += $this->update($resource, $row['data'], $row['where'])->count();
                }
            } catch (Throwable $throwable) {
                $this->abortTransaction($started);

                throw $throwable;
            }

            $this->commitTransaction($started);

            return Result::make(null, $total);
        }


        /**
         * @param DatabaseResourceInterface $resource
         * @param array<string, ParamInterface> $data
         * @param array<array-key, mixed> $filter
         * @return ResultInterface
         * @throws QueryException
         */
        public function upsert(DatabaseResourceInterface $resource, array $data, array $filter): ResultInterface
        {
            if ($data === []) {
                throw QueryException::invalid('cannot upsert a record without any data');
            }

            $conflict = self::eqColumns($filter);

            if ($conflict === [] && $resource instanceof ResourceMetadataAwareInterface) {
                $conflict = $resource->metadata()->primaryKey();
            }

            if ($conflict === []) {
                throw QueryException::invalid('upsert requires conflict columns (filter EQ conditions or a metadata primary key)');
            }

            $columns = [];
            $placeholders = [];
            $updates = [];
            $params = [];
            $record = [];

            foreach ($data as $field => $value) {
                $param = $this->param((string) $field, $value);
                $quoted = $this->quote((string) $field);

                $columns[] = $quoted;
                $placeholders[] = '?';
                $params[] = $param;
                $record[$field] = $param->value();

                if ( ! in_array($field, $conflict, true)) {
                    $updates[] = $quoted . ' = excluded.' . $quoted;
                }
            }

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s) ON CONFLICT (%s) DO UPDATE SET %s',
                $this->quote($resource->name()),
                implode(', ', $columns),
                implode(', ', $placeholders),
                implode(', ', array_map($this->quote(...), $conflict)),
                $updates === []
                    ? $this->quote($conflict[0]) . ' = ' . $this->quote($conflict[0])
                    : implode(', ', $updates),
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
         * @param array<string, OrderDirection> $order
         * @param array<string, mixed> $pagination
         * @return iterable<int, mixed>
         * @throws QueryException
         */
        public function stream(DatabaseResourceInterface $resource, array $filter = [], array $order = [], array $pagination = []): iterable
        {
            $select = $this->selectSql($resource, $filter, $order, $pagination);
            $statement = $this->execute($select['sql'], $select['params']);

            while (($row = $statement->fetch()) !== false) {
                yield $row;
            }
        }


        /**
         * @param DatabaseResourceInterface $resource
         * @param array<array-key, mixed> $filter
         * @param array<string, OrderDirection> $order
         * @param array<string, mixed> $pagination
         * @return ResultInterface
         * @throws QueryException
         */
        public function search(DatabaseResourceInterface $resource, array $filter = [], array $order = [], array $pagination = []): ResultInterface
        {
            if ( ! array_is_list($filter) && $this->isFts5($resource)) {
                return $this->searchFts($resource, $filter, $order, $pagination);
            }

            return $this->searchLike($resource, $filter, $order, $pagination);
        }


        /**
         * @param DatabaseResourceInterface $resource
         * @param array<string, mixed> $pipeline
         * @return ResultInterface
         * @throws QueryException
         */
        public function aggregate(DatabaseResourceInterface $resource, array $pipeline): ResultInterface
        {
            if (isset($pipeline['native'])) {
                return Result::make([], 0);
            }

            $specifications = $pipeline['aggregate'] ?? [];

            if ( ! is_array($specifications) || $specifications === []) {
                return Result::make([], 0);
            }

            $expressions = [];

            foreach ($specifications as $alias => $specification) {
                if (
                    ! is_array($specification)
                    || count($specification) !== 2
                    || ! isset($specification[0], $specification[1])
                    || ! is_string($specification[0])
                    || ! is_string($specification[1])
                ) {
                    return Result::make([], 0);
                }

                $function = strtolower($specification[0]);
                $input = $specification[1];

                if ( ! in_array($function, ['count', 'sum', 'avg', 'min', 'max'], true)) {
                    return Result::make([], 0);
                }

                $argument = $input === '*' ? '*' : $this->quote($input);
                $expressions[] = strtoupper($function) . '(' . $argument . ') AS ' . $this->quote((string) $alias);
            }

            $where = Conditions::compile(is_array($pipeline['where'] ?? null) ? $pipeline['where'] : [], $this->quote(...));
            $having = Conditions::compile(is_array($pipeline['having'] ?? null) ? $pipeline['having'] : [], $this->quote(...));

            $groupBy = $pipeline['groupBy'] ?? [];

            if ( ! is_array($groupBy)) {
                return Result::make([], 0);
            }

            $groupColumns = [];

            foreach ($groupBy as $column) {
                if ( ! is_string($column)) {
                    return Result::make([], 0);
                }

                $groupColumns[] = $this->quote($column);
            }

            $sql = 'SELECT ' . implode(', ', $expressions) . ' FROM ' . $this->quote($resource->name());

            if ($where['sql'] !== '') {
                $sql .= ' WHERE ' . $where['sql'];
            }

            if ($groupColumns !== []) {
                $sql .= ' GROUP BY ' . implode(', ', $groupColumns);
            }

            if ($having['sql'] !== '') {
                $sql .= ' HAVING ' . $having['sql'];
            }

            /** @var array<string, OrderDirection> $order */
            $order = is_array($pipeline['order'] ?? null) ? $pipeline['order'] : [];
            $sql .= $this->orderSql($order);

            $limit = $pipeline['limit'] ?? null;

            if (is_int($limit) && $limit >= 0) {
                $sql .= ' LIMIT ' . $limit;
            }

            $rows = $this->fetchAll($sql, [...$where['params'], ...$having['params']]);

            return Result::make($rows, count($rows));
        }


        /**
         * @param DatabaseResourceInterface $resource
         * @param array<array-key, mixed> $filter
         * @param array<string, OrderDirection> $order
         * @param array<string, mixed> $pagination
         * @return ResultInterface
         * @throws QueryException
         */
        private function searchFts(DatabaseResourceInterface $resource, array $filter, array $order, array $pagination): ResultInterface
        {
            $terms = self::textTerms($filter);

            if ($terms === []) {
                return $this->list($resource, self::nonTextFilter($filter), $order, $pagination);
            }

            $match = implode(' ', array_map(
                fn (string $term): string => '"' . str_replace('"', '""', $term) . '"',
                $terms,
            ));

            $compiled = Conditions::compile(self::nonTextFilter($filter), $this->quote(...));

            $fragments = [$this->quote($resource->name()) . ' MATCH ?'];
            $params = [Param::str($match)];

            if ($compiled['sql'] !== '') {
                $fragments[] = $compiled['sql'];
                $params = [...$params, ...$compiled['params']];
            }

            $sql = 'SELECT * FROM ' . $this->quote($resource->name()) . ' WHERE ' . implode(' AND ', $fragments);
            $sql .= $this->orderSql($order, $pagination);
            $sql .= $this->paginationSql($pagination);

            $rows = $this->fetchAll($sql, $params);

            return Result::make($rows, count($rows));
        }

        /**
         * Case-insensitive LIKE fallback across the resource columns.
         *
         * @param DatabaseResourceInterface $resource
         * @param array<array-key, mixed> $filter
         * @param array<string, OrderDirection> $order
         * @param array<string, mixed> $pagination
         * @return ResultInterface
         * @throws QueryException
         */
        private function searchLike(DatabaseResourceInterface $resource, array $filter, array $order, array $pagination): ResultInterface
        {
            $columns = $this->searchColumns($resource);
            $terms = self::textTerms($filter);

            if ($columns === [] || $terms === []) {
                return Result::make([], 0);
            }

            $groups = [];
            $params = [];

            foreach ($terms as $term) {
                $likes = [];

                foreach ($columns as $column) {
                    $likes[] = $this->quote($column) . ' LIKE ?';
                    $params[] = Param::str('%' . $term . '%');
                }

                $groups[] = '(' . implode(' OR ', $likes) . ')';
            }

            $sql = 'SELECT * FROM ' . $this->quote($resource->name()) . ' WHERE ' . implode(' AND ', $groups);
            $sql .= $this->orderSql($order, $pagination);
            $sql .= $this->paginationSql($pagination);

            $rows = $this->fetchAll($sql, $params);

            return Result::make($rows, count($rows));
        }

        /**
         * @param DatabaseResourceInterface $resource
         * @return bool
         * @throws QueryException
         */
        private function isFts5(DatabaseResourceInterface $resource): bool
        {
            $statement = $this->execute(
                "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?",
                [Param::str($resource->name())],
            );

            $row = $statement->fetch();

            if ( ! is_array($row)) {
                return false;
            }

            $sql = isset($row['sql']) && is_string($row['sql']) ? $row['sql'] : '';

            return stripos($sql, 'fts5') !== false;
        }

        /**
         * @param DatabaseResourceInterface $resource
         * @return array<int, string>
         * @throws QueryException
         */
        private function searchColumns(DatabaseResourceInterface $resource): array
        {
            if ($resource instanceof ResourceMetadataAwareInterface) {
                $fields = array_keys($resource->metadata()->fields());

                if ($fields !== []) {
                    return $fields;
                }
            }

            $statement = $this->execute('PRAGMA table_info(' . $this->quote($resource->name()) . ')');
            $columns = [];

            while (($row = $statement->fetch()) !== false) {
                if (is_array($row) && isset($row['name']) && is_string($row['name'])) {
                    $columns[] = $row['name'];
                }
            }

            return $columns;
        }

        /**
         * Text terms carried by LIKE/EQ string criteria (percent signs
         * stripped), extracted from the flattened filter.
         *
         * @param array<array-key, mixed> $filter
         * @return array<int, string>
         */
        private static function textTerms(array $filter): array
        {
            $terms = [];

            foreach (self::filterEntries($filter) as $key => $value) {
                if ( ! is_string($key) || ! $value instanceof ParamInterface || ! is_string($value->value())) {
                    continue;
                }

                $operator = self::entryOperator($key);

                if ($operator !== 'eq' && $operator !== 'like' && $operator !== 'not_like') {
                    continue;
                }

                $term = trim((string) $value->value(), " \t\n\r\0\x0B%");

                if ($term !== '') {
                    $terms[] = $term;
                }
            }

            return array_values(array_unique($terms));
        }

        /**
         * The filter entries that are not text criteria.
         *
         * @param array<array-key, mixed> $filter
         * @return array<string, mixed>
         */
        private static function nonTextFilter(array $filter): array
        {
            $remaining = [];

            foreach (self::filterEntries($filter) as $key => $value) {
                if ( ! is_string($key)) {
                    continue;
                }

                $operator = self::entryOperator($key);
                $isText = in_array($operator, ['eq', 'like', 'not_like'], true)
                    && $value instanceof ParamInterface
                    && is_string($value->value());

                if ( ! $isText) {
                    $remaining[$key] = $value;
                }
            }

            return $remaining;
        }

        /**
         * Conflict columns: the EQ conditions of the filter.
         *
         * @param array<array-key, mixed> $filter
         * @return array<int, string>
         */
        private static function eqColumns(array $filter): array
        {
            $columns = [];

            foreach (self::filterEntries($filter) as $key => $value) {
                if ( ! is_string($key) || self::entryOperator($key) !== 'eq') {
                    continue;
                }

                $columns[] = explode(' ', trim($key), 2)[0];
            }

            return array_values(array_unique($columns));
        }

        /**
         * Flattens clause lists into a single predicate map (for text-term
         * and conflict extraction; groups collapse into one level).
         *
         * @param array<array-key, mixed> $filter
         * @return array<array-key, mixed>
         */
        private static function filterEntries(array $filter): array
        {
            if ( ! array_is_list($filter)) {
                return $filter;
            }

            $entries = [];

            foreach ($filter as $clause) {
                if ( ! is_array($clause)) {
                    continue;
                }

                $entries += array_is_list($clause) ? self::filterEntries($clause) : $clause;
            }

            return $entries;
        }

        /**
         * @param string $key
         * @return string
         */
        private static function entryOperator(string $key): string
        {
            $parts = explode(' ', trim($key), 2);

            return count($parts) === 1 ? 'eq' : strtolower($parts[1]);
        }


        /**
         * Starts a transaction when the driver honors the capability and no
         * transaction is already open. Returns whether this call started it.
         *
         * @return bool
         */
        private function startTransaction(): bool
        {
            if ( ! $this->capabilities->supportsTransactions() || $this->inTransaction()) {
                return false;
            }

            $this->begin();

            return true;
        }

        /** @param bool $started
         * @return void
         */
        private function commitTransaction(bool $started): void
        {
            if ($started) {
                $this->commit();
            }
        }

        /** @param bool $started
         * @return void
         */
        private function abortTransaction(bool $started): void
        {
            if ($started && $this->inTransaction()) {
                $this->rollback();
            }
        }
    }
