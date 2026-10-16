<?php declare(strict_types=1);

    namespace STDW\Database\QueryTree\Builder;

    use STDW\Contract\Database\QueryTree\ConditionNodeInterface;
    use STDW\Contract\Database\QueryTree\GroupNodeInterface;
    use STDW\Contract\Database\QueryTree\LogicNodeInterface;
    use STDW\Contract\Database\QueryTree\OrderNodeInterface;
    use STDW\Contract\Database\QueryTree\PaginationNodeInterface;
    use STDW\Contract\Database\QueryTree\Types\ConditionOperator;
    use STDW\Contract\Database\QueryTree\Types\OrderDirection;


    /**
     * Converts QueryTree nodes back into the canonical driver-edge arrays
     * (Filter / Order / Pagination shapes of DatabaseDriverInterface).
     *
     * The QueryTree is the builders' internal vocabulary; drivers only ever
     * receive these simple arrays. Round-trip: FilterBuilder::make() then
     * filter() yields an equivalent canonical array.
     */
    final class QueryTreeTranslator
    {
        /**
         * @param GroupNodeInterface $group
         * @return array<array-key, mixed>
         */
        public static function filter(GroupNodeInterface $group): array
        {
            $nodes = $group->nodes();

            if ($nodes === []) {
                return [];
            }

            $conditionsOnly = true;

            foreach ($nodes as $node) {
                if ( ! $node instanceof ConditionNodeInterface) {
                    $conditionsOnly = false;

                    break;
                }
            }

            if ($conditionsOnly) {
                $predicate = [];

                /** @var ConditionNodeInterface $node */
                foreach ($nodes as $node) {
                    $predicate += self::condition($node);
                }

                return $predicate;
            }

            $clauses = [];

            foreach ($nodes as $node) {
                $clauses[] = match (true) {
                    $node instanceof ConditionNodeInterface => self::condition($node),
                    $node instanceof LogicNodeInterface => strtoupper($node->operator()->value),
                    $node instanceof GroupNodeInterface => self::filter($node),
                };
            }

            return $clauses;
        }

        /**
         * @param ConditionNodeInterface $node
         * @return array<string, mixed>
         */
        public static function condition(ConditionNodeInterface $node): array
        {
            $operator = $node->operator();

            if ($operator === ConditionOperator::IsNull || $operator === ConditionOperator::IsNotNull) {
                return [$node->column() . ' ' . strtoupper($operator->value) => null];
            }

            $key = $operator === ConditionOperator::Eq
                ? $node->column()
                : $node->column() . ' ' . strtoupper($operator->value);

            return [$key => $node->value()];
        }

        /**
         * @param array<int, OrderNodeInterface> $nodes
         * @return array<string, OrderDirection>
         */
        public static function order(array $nodes): array
        {
            $order = [];

            foreach ($nodes as $node) {
                $order[$node->column()] = $node->direction();
            }

            return $order;
        }

        /**
         * @param PaginationNodeInterface $node
         * @return array<string, mixed>
         */
        public static function pagination(PaginationNodeInterface $node): array
        {
            $pagination = [];

            if ($node->cursor() !== null) {
                $pagination['cursor'] = $node->cursor();
            }

            if ($node->limit() !== null) {
                $pagination['limit'] = $node->limit();
            }

            if ($node->offset() !== null) {
                $pagination['offset'] = $node->offset();
            }

            return $pagination;
        }
    }
