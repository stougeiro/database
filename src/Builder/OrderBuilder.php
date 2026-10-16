<?php declare(strict_types=1);

    namespace STDW\Database\Builder;

    use STDW\Contract\Database\QueryTree\Types\OrderDirection;
    use STDW\Database\Exception\QueryTreeException;
    use STDW\Database\QueryTree\OrderNode;


    /**
     * Validates the canonical order map (column => direction) and builds its
     * QueryTree representation. Directions accept the neutral strings
     * "asc"/"desc" (any case) or the OrderDirection enum.
     */
    final class OrderBuilder
    {
        /**
         * @param array<array-key, mixed> $order
         * @return array<int, OrderNode>
         * @throws QueryTreeException
         */
        public static function make(array $order): array
        {
            if ($order === []) {
                return [];
            }

            if (array_is_list($order)) {
                throw QueryTreeException::invalid('order must be a map of column => direction');
            }

            $nodes = [];

            foreach ($order as $column => $direction) {
                if ( ! is_string($column) || trim($column) === '') {
                    throw QueryTreeException::invalid('order columns must be non-empty strings');
                }

                $nodes[] = new OrderNode($column, self::direction($column, $direction));
            }

            return $nodes;
        }


        /**
         * @param string $column
         * @param mixed $direction
         * @return OrderDirection
         * @throws QueryTreeException
         */
        private static function direction(string $column, mixed $direction): OrderDirection
        {
            if ($direction instanceof OrderDirection) {
                return $direction;
            }

            if (is_string($direction)) {
                $resolved = OrderDirection::tryFrom(strtolower(trim($direction)));

                if ($resolved !== null) {
                    return $resolved;
                }
            }

            throw QueryTreeException::invalid(sprintf('order direction of column "%s" must be "asc" or "desc"', $column));
        }
    }
