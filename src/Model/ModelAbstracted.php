<?php declare(strict_types=1);

    namespace STDW\Database\Model;

    use STDW\Contract\Database\Driver\Capability\AggregationInterface;
    use STDW\Contract\Database\Driver\Capability\BatchInterface;
    use STDW\Contract\Database\Driver\Capability\SearchInterface;
    use STDW\Contract\Database\Driver\Capability\StreamingInterface;
    use STDW\Contract\Database\Driver\Capability\TransactionInterface;
    use STDW\Contract\Database\Driver\Capability\UpsertInterface;
    use STDW\Contract\Database\Driver\DatabaseDriverInterface;
    use STDW\Contract\Database\Manager\DatabaseManagerInterface;
    use STDW\Contract\Database\Param\ParamInterface;
    use STDW\Contract\Database\QueryTree\Types\OrderDirection;
    use STDW\Contract\Database\Resource\DatabaseResourceInterface;
    use STDW\Contract\Database\Resource\ResourceMetadataInterface;
    use STDW\Database\Builder\FilterBuilder;
    use STDW\Database\Builder\OrderBuilder;
    use STDW\Database\Builder\PaginationBuilder;
    use STDW\Database\Builder\QueryTreeTranslator;
    use STDW\Database\Exception\InvalidConfigException;
    use STDW\Database\Exception\UnsupportedCapabilityException;
    use STDW\Database\Manager\DatabaseManager;
    use STDW\Database\Param\Param;
    use STDW\Database\Resource\Resource;


    /**
     * Active-record-ish base: a named resource resolved through the manager.
     *
     * The DEV-facing surface is always simple arrays of raw scalars — values
     * are auto-wrapped into typed Params and validated through the builders
     * (fail-fast QueryTreeException) before reaching the driver, which never
     * sees the Model and never knows about the QueryTree. Capability-gated
     * operations (search/stream/upsert/batch/aggregate) throw
     * UnsupportedCapabilityException when the resolved driver does not honor
     * the capability. Transactions are a driver capability and are exposed
     * here only as gated passthroughs.
     *
     * @phpstan-type CriteriaValue ParamInterface|array<int, ParamInterface>|null
     * @phpstan-type Predicate array<string, CriteriaValue>
     * @phpstan-type Clause Predicate|'AND'|'OR'|'NOT'|array<int, Predicate|'AND'|'OR'|'NOT'|array<int, mixed>>
     * @phpstan-type Filter Predicate|array<int, Clause>
     * @phpstan-type Pagination array{limit?: int, offset?: int, page?: int, perPage?: int, cursor?: mixed}
     */
    abstract class ModelAbstracted
    {
        /** @var string Table/collection name; required per subclass.
         */
        protected string $resource = '';

        /** @var string Connection name resolved through the manager.
         */
        protected string $connection = DatabaseManager::DEFAULT;

        /** @var null|ResourceMetadataInterface Optional schema attached to the resource.
         */
        protected ?ResourceMetadataInterface $metadata = null;

        private ?DatabaseDriverInterface $driver = null;


        /**
         * @param DatabaseManagerInterface $manager
         * @throws InvalidConfigException
         */
        public function __construct(
            protected readonly DatabaseManagerInterface $manager,
        ) {
            if ($this->resource === '') {
                throw InvalidConfigException::resourceNotDeclared(static::class);
            }
        }


        /**
         * @param array<string, mixed> $data
         * @return null|array<int|string, mixed>
         */
        public function create(array $data): ?array
        {
            $result = $this->driver()->create($this->resource(), $this->params($data));

            return self::row($result->data());
        }

        /**
         * @param array<array-key, mixed> $filter
         * @return null|array<int|string, mixed>
         */
        public function read(array $filter): ?array
        {
            $result = $this->driver()->read($this->resource(), $this->filter($filter));

            return self::row($result->data());
        }

        /**
         * @param array<array-key, mixed> $filter
         * @param array<string, mixed> $order Map of column => direction.
         * @param array<string, mixed> $pagination limit/offset or page/perPage, or a cursor.
         * @return array<int, array<int|string, mixed>>
         */
        public function list(array $filter = [], array $order = [], array $pagination = []): array
        {
            $result = $this->driver()->list(
                $this->resource(),
                $this->filter($filter),
                $this->order($order),
                $this->pagination($pagination),
            );

            return self::rows($result->data());
        }

        /**
         * @param array<string, mixed> $data
         * @param array<array-key, mixed> $filter
         * @return int Affected records count.
         */
        public function update(array $data, array $filter): int
        {
            return $this->driver()->update($this->resource(), $this->params($data), $this->filter($filter))->count();
        }

        /**
         * @param array<array-key, mixed> $filter
         * @return int Affected records count.
         */
        public function delete(array $filter): int
        {
            return $this->driver()->delete($this->resource(), $this->filter($filter))->count();
        }

        /**
         * @param array<array-key, mixed> $filter
         * @return int
         */
        public function total(array $filter = []): int
        {
            return $this->driver()->total($this->resource(), $this->filter($filter));
        }


        /**
         * @param array<array-key, mixed> $filter
         * @param array<string, mixed> $order
         * @param array<string, mixed> $pagination
         * @return array<int, array<int|string, mixed>>
         * @throws UnsupportedCapabilityException
         */
        public function search(array $filter = [], array $order = [], array $pagination = []): array
        {
            $result = $this->capability(
                SearchInterface::class,
                static fn ($capabilities): bool => $capabilities->supportsSearch(),
            )->search(
                $this->resource(),
                $this->filter($filter),
                $this->order($order),
                $this->pagination($pagination),
            );

            return self::rows($result->data());
        }

        /**
         * @param array<array-key, mixed> $filter
         * @param array<string, mixed> $order
         * @param array<string, mixed> $pagination
         * @return iterable<int, mixed>
         * @throws UnsupportedCapabilityException
         */
        public function stream(array $filter = [], array $order = [], array $pagination = []): iterable
        {
            return $this->capability(
                StreamingInterface::class,
                static fn ($capabilities): bool => $capabilities->supportsStreaming(),
            )->stream(
                $this->resource(),
                $this->filter($filter),
                $this->order($order),
                $this->pagination($pagination),
            );
        }

        /**
         * @param array<string, mixed> $data
         * @param array<array-key, mixed> $filter
         * @return null|array<int|string, mixed>
         * @throws UnsupportedCapabilityException
         */
        public function upsert(array $data, array $filter): ?array
        {
            $result = $this->capability(
                UpsertInterface::class,
                static fn ($capabilities): bool => $capabilities->supportsUpsert(),
            )->upsert(
                $this->resource(),
                $this->params($data),
                $this->filter($filter),
            );

            return self::row($result->data());
        }

        /**
         * @param array<int, array<string, mixed>> $rows
         * @return int Inserted records count.
         * @throws UnsupportedCapabilityException
         */
        public function insertMany(array $rows): int
        {
            $wrapped = [];

            foreach ($rows as $row) {
                $wrapped[] = $this->params($row);
            }

            return $this->capability(
                BatchInterface::class,
                static fn ($capabilities): bool => $capabilities->supportsBatch(),
            )->insertMany($this->resource(), $wrapped)->count();
        }

        /**
         * @param array<int, array{data: array<string, mixed>, where: array<string, mixed>}> $rows
         * @return int Affected records count.
         * @throws UnsupportedCapabilityException
         */
        public function updateMany(array $rows): int
        {
            $wrapped = [];

            foreach ($rows as $row) {
                $wrapped[] = [
                    'data' => $this->params($row['data']),
                    'where' => $this->params($row['where']),
                ];
            }

            return $this->capability(
                BatchInterface::class,
                static fn ($capabilities): bool => $capabilities->supportsBatch(),
            )->updateMany($this->resource(), $wrapped)->count();
        }

        /**
         * @param array{where?: array<array-key, mixed>, groupBy?: array<int, string>, aggregate?: array<string, array{string, string}>, having?: array<array-key, mixed>, order?: array<array-key, mixed>, limit?: int, native?: array<int, mixed>} $pipeline
         * @return array<int, array<int|string, mixed>>
         * @throws UnsupportedCapabilityException
         */
        public function aggregate(array $pipeline): array
        {
            if (isset($pipeline['where'])) {
                $pipeline['where'] = $this->filter($pipeline['where']);
            }

            if (isset($pipeline['having'])) {
                $pipeline['having'] = $this->filter($pipeline['having']);
            }

            if (isset($pipeline['order'])) {
                $pipeline['order'] = $this->order($pipeline['order']);
            }

            $result = $this->capability(
                AggregationInterface::class,
                static fn ($capabilities): bool => $capabilities->supportsAggregation(),
            )->aggregate($this->resource(), $pipeline);

            return self::rows($result->data());
        }


        /**
         * @return void
         * @throws UnsupportedCapabilityException
         */
        public function begin(): void
        {
            $this->transaction()->begin();
        }

        /**
         * @return void
         * @throws UnsupportedCapabilityException
         */
        public function commit(): void
        {
            $this->transaction()->commit();
        }

        /**
         * @return void
         * @throws UnsupportedCapabilityException
         */
        public function rollback(): void
        {
            $this->transaction()->rollback();
        }

        /**
         * @return bool
         * @throws UnsupportedCapabilityException
         */
        public function inTransaction(): bool
        {
            return $this->transaction()->inTransaction();
        }


        /** The resolved driver (cached).
         *
         * @return DatabaseDriverInterface
         */
        protected function driver(): DatabaseDriverInterface
        {
            return $this->driver ??= $this->manager->driver($this->connection);
        }

        /** The resource this model maps to.
         *
         * @return DatabaseResourceInterface
         */
        protected function resource(): DatabaseResourceInterface
        {
            return new Resource($this->resource, $this->metadata);
        }


        /**
         * Wraps raw scalar values into typed Params.
         *
         * @param array<string, mixed> $data
         * @return array<string, ParamInterface>
         */
        protected function params(array $data): array
        {
            $params = [];

            foreach ($data as $field => $value) {
                $params[(string) $field] = $value instanceof ParamInterface ? $value : Param::from($value);
            }

            return $params;
        }

        /**
         * Wraps raw filter values into typed Params and validates the filter
         * through the QueryTree builders (fail-fast before the driver).
         *
         * @param array<array-key, mixed> $filter
         * @return Filter
         */
        protected function filter(array $filter): array
        {
            $wrapped = $this->wrapFilter($filter);

            FilterBuilder::make($wrapped);

            /** @var Filter $wrapped */
            return $wrapped;
        }

        /**
         * @param array<string, mixed> $order
         * @return array<string, OrderDirection>
         */
        protected function order(array $order): array
        {
            return QueryTreeTranslator::order(OrderBuilder::make($order));
        }

        /**
         * @param array<string, mixed> $pagination
         * @return Pagination
         */
        protected function pagination(array $pagination): array
        {
            /** @var Pagination $resolved */
            $resolved = QueryTreeTranslator::pagination(PaginationBuilder::make($pagination));

            return $resolved;
        }


        /**
         * @return TransactionInterface
         * @throws UnsupportedCapabilityException
         */
        private function transaction(): TransactionInterface
        {
            /** @var TransactionInterface $driver */
            $driver = $this->capability(
                TransactionInterface::class,
                static fn ($capabilities): bool => $capabilities->supportsTransactions(),
            );

            return $driver;
        }

        /**
         * @template T of object
         *
         * @param class-string<T> $interface
         * @param callable(\STDW\Contract\Database\Driver\DriverCapabilitiesInterface): bool $supports
         * @return T
         * @throws UnsupportedCapabilityException
         */
        private function capability(string $interface, callable $supports): object
        {
            $driver = $this->driver();

            if ( ! $driver instanceof $interface || ! $supports($driver->capabilities())) {
                $short = substr((string) strrchr($interface, '\\'), 1);

                throw UnsupportedCapabilityException::capability($short);
            }

            return $driver;
        }

        /**
         * @param array<array-key, mixed> $filter
         * @return array<array-key, mixed>
         */
        private function wrapFilter(array $filter): array
        {
            if ( ! array_is_list($filter)) {
                $wrapped = [];

                foreach ($filter as $key => $value) {
                    $wrapped[$key] = $this->wrapFilterValue($value);
                }

                return $wrapped;
            }

            $wrapped = [];

            foreach ($filter as $clause) {
                $wrapped[] = is_array($clause) ? $this->wrapFilter($clause) : $clause;
            }

            return $wrapped;
        }

        /**
         * @param mixed $value
         * @return mixed
         */
        private function wrapFilterValue(mixed $value): mixed
        {
            if ($value === null || $value instanceof ParamInterface) {
                return $value;
            }

            if (is_array($value)) {
                $wrapped = [];

                foreach ($value as $item) {
                    $wrapped[] = $item instanceof ParamInterface ? $item : Param::from($item);
                }

                return $wrapped;
            }

            return Param::from($value);
        }

        /**
         * @param array<array-key, mixed>|null|object $data
         * @return null|array<int|string, mixed>
         */
        private static function row(null|array|object $data): ?array
        {
            return is_array($data) ? $data : null;
        }

        /**
         * @param array<array-key, mixed>|null|object $data
         * @return array<int, array<int|string, mixed>>
         */
        private static function rows(null|array|object $data): array
        {
            if ( ! is_array($data)) {
                return [];
            }

            $rows = [];

            foreach ($data as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }

            return $rows;
        }
    }
