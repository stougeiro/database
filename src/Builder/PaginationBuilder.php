<?php declare(strict_types=1);

    namespace STDW\Database\Builder;

    use STDW\Database\Exception\QueryTreeException;
    use STDW\Database\QueryTree\PaginationNode;


    /**
     * Validates the canonical pagination array
     * (limit?, offset?, page?, perPage?, cursor?) and builds its QueryTree
     * representation.
     */
    final class PaginationBuilder
    {
        /** @var array<int, string>
         */
        private const KEYS = ['limit', 'offset', 'page', 'perPage', 'cursor'];


        /**
         * @param array<array-key, mixed> $pagination
         * @return PaginationNode
         * @throws QueryTreeException
         */
        public static function make(array $pagination): PaginationNode
        {
            foreach (array_keys($pagination) as $key) {
                if ( ! is_string($key) || ! in_array($key, self::KEYS, true)) {
                    throw QueryTreeException::invalid(sprintf('unknown pagination key "%s"', (string) $key));
                }
            }

            return new PaginationNode(
                limit: self::nullableInt('limit', $pagination['limit'] ?? null, 0),
                offset: self::nullableInt('offset', $pagination['offset'] ?? null, 0),
                page: self::nullableInt('page', $pagination['page'] ?? null, 1),
                perPage: self::nullableInt('perPage', $pagination['perPage'] ?? null, 1),
                cursor: $pagination['cursor'] ?? null,
            );
        }


        /**
         * @param string $key
         * @param mixed $value
         * @param int $min
         * @return null|int
         * @throws QueryTreeException
         */
        private static function nullableInt(string $key, mixed $value, int $min): ?int
        {
            if ($value === null) {
                return null;
            }

            if ( ! is_int($value) || $value < $min) {
                throw QueryTreeException::invalid(sprintf('pagination key "%s" must be an integer >= %d', $key, $min));
            }

            return $value;
        }
    }
