<?php declare(strict_types=1);

    namespace STDW\Database\Builder;

    use STDW\Contract\Database\Param\ParamInterface;
    use STDW\Contract\Database\QueryTree\ConditionNodeInterface;
    use STDW\Contract\Database\QueryTree\Types\ConditionOperator;
    use STDW\Contract\Database\QueryTree\Types\LogicOperator;
    use STDW\Database\Exception\QueryTreeException;
    use STDW\Database\QueryTree\ConditionNode;
    use STDW\Database\QueryTree\GroupNode;
    use STDW\Database\QueryTree\LogicNode;
    use ValueError;


    /**
     * Validates the canonical filter array (Predicate or Clause list) and
     * builds its QueryTree representation.
     *
     * Conventions (see the contract's DatabaseDriverInterface):
     * - key: "column OP" (space-separated, UPPERCASE token); without OP the
     *   operator is EQ;
     * - value: ParamInterface | array<int, ParamInterface> | null (only for
     *   IS_NULL / IS_NOT_NULL);
     * - shape: associative => Predicate (entries ANDed); list => sequence of
     *   clauses (default AND; "AND"/"OR"/"NOT" tokens; nested arrays are
     *   groups). Disambiguation uses array_is_list().
     *
     * Anything invalid fails fast with QueryTreeException, before the driver
     * ever sees the filter.
     */
    final class FilterBuilder
    {
        /** @var array<int, string>
         */
        private const LOGIC = ['AND', 'OR', 'NOT'];


        /**
         * @param array<array-key, mixed> $filter
         * @return GroupNode
         * @throws QueryTreeException
         */
        public static function make(array $filter): GroupNode
        {
            return array_is_list($filter)
                ? self::fromClauses($filter)
                : self::fromPredicate($filter);
        }


        /**
         * @param array<array-key, mixed> $predicate
         * @return GroupNode
         * @throws QueryTreeException
         */
        private static function fromPredicate(array $predicate): GroupNode
        {
            $nodes = [];

            foreach ($predicate as $key => $value) {
                if ( ! is_string($key)) {
                    throw QueryTreeException::invalid('predicate keys must be strings');
                }

                $nodes[] = self::condition($key, $value);
            }

            return new GroupNode($nodes);
        }

        /**
         * @param array<array-key, mixed> $clauses
         * @return GroupNode
         * @throws QueryTreeException
         */
        private static function fromClauses(array $clauses): GroupNode
        {
            $nodes = [];

            foreach ($clauses as $clause) {
                if (is_string($clause)) {
                    $nodes[] = new LogicNode(self::logic($clause));

                    continue;
                }

                if ( ! is_array($clause)) {
                    throw QueryTreeException::invalid('clause must be a predicate, a logic token or a nested group');
                }

                $nodes[] = self::make($clause);
            }

            return new GroupNode($nodes);
        }

        /**
         * @param string $key
         * @param mixed $value
         * @return ConditionNode
         * @throws QueryTreeException
         */
        private static function condition(string $key, mixed $value): ConditionNode
        {
            [$column, $operator] = self::parseKey($key);

            if ($value === null) {
                if ($operator !== ConditionOperator::IsNull && $operator !== ConditionOperator::IsNotNull) {
                    throw QueryTreeException::invalidCondition($column, 'null is only allowed for IS_NULL / IS_NOT_NULL');
                }

                /** @var ParamInterface|array<int, ParamInterface> $value */
                $value = [];
            }

            if ( ! $value instanceof ParamInterface && ! is_array($value)) {
                throw QueryTreeException::invalidCondition($column, 'value must be a typed Param or a list of them');
            }

            /** @var ParamInterface|array<int, ParamInterface> $value */
            return new ConditionNode($column, $operator, $value);
        }

        /**
         * Splits a "column OP" key into its column and the neutral operator.
         *
         * @param string $key
         * @return array{string, ConditionOperator}
         * @throws QueryTreeException
         */
        private static function parseKey(string $key): array
        {
            $parts = explode(' ', trim($key), 2);

            if (count($parts) === 1) {
                return [$parts[0], ConditionOperator::Eq];
            }

            $token = strtolower($parts[1]);

            try {
                return [$parts[0], ConditionOperator::from($token)];
            } catch (ValueError) {
                throw QueryTreeException::invalidOperator(strtoupper($token));
            }
        }

        /**
         * @param string $token
         * @return LogicOperator
         * @throws QueryTreeException
         */
        private static function logic(string $token): LogicOperator
        {
            $normalized = strtoupper(trim($token));

            if ( ! in_array($normalized, self::LOGIC, true)) {
                throw QueryTreeException::invalidLogic($token);
            }

            return LogicOperator::from(strtolower($normalized));
        }
    }
