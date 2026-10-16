<?php declare(strict_types=1);

    namespace STDW\Database\QueryTree;

    use STDW\Contract\Database\Param\ParamInterface;
    use STDW\Contract\Database\QueryTree\ConditionNodeInterface;
    use STDW\Contract\Database\QueryTree\Types\ConditionOperator;
    use STDW\Database\Exception\QueryTreeException;


    /**
     * A column comparison: column + neutral operator + typed value.
     *
     * The value arity is validated at construction time: single operators
     * (eq, neq, gt, gte, lt, lte, like, not_like) require one ParamInterface;
     * list operators (in, not_in) a non-empty list; interval operators
     * (between, not_between) exactly two; null operators (is_null,
     * is_not_null) carry no value (normalized to an empty list).
     */
    final readonly class ConditionNode implements ConditionNodeInterface
    {
        /** @var array<int, ParamInterface>
         */
        private array $list;

        /**
         * @param string $column
         * @param ConditionOperator $operator
         * @param ParamInterface|array<int, ParamInterface> $value
         * @throws QueryTreeException
         */
        public function __construct(
            private string $column,
            private ConditionOperator $operator,
            ParamInterface|array $value,
        ) {
            if (trim($column) === '') {
                throw QueryTreeException::invalidCondition($column, 'column cannot be empty');
            }

            $this->list = match ($operator) {
                ConditionOperator::IsNull, ConditionOperator::IsNotNull => [],
                ConditionOperator::In, ConditionOperator::NotIn => self::assertList($column, $value, 1, null),
                ConditionOperator::Between, ConditionOperator::NotBetween => self::assertList($column, $value, 2, 2),
                default => self::assertSingle($column, $value),
            };
        }


        /** @return string
         */
        public function column(): string
        {
            return $this->column;
        }

        /** @return ConditionOperator
         */
        public function operator(): ConditionOperator
        {
            return $this->operator;
        }

        /** @return ParamInterface|array<int, ParamInterface>
         */
        public function value(): ParamInterface|array
        {
            if (count($this->list) === 1 && $this->isSingleOperator()) {
                return $this->list[0];
            }

            return $this->list;
        }


        /** @return bool
         */
        private function isSingleOperator(): bool
        {
            return match ($this->operator) {
                ConditionOperator::In, ConditionOperator::NotIn,
                ConditionOperator::Between, ConditionOperator::NotBetween,
                ConditionOperator::IsNull, ConditionOperator::IsNotNull => false,
                default => true,
            };
        }

        /**
         * @param string $column
         * @param ParamInterface|array<array-key, mixed> $value
         * @return array<int, ParamInterface>
         * @throws QueryTreeException
         */
        private static function assertList(string $column, ParamInterface|array $value, int $min, null|int $max): array
        {
            if ( ! is_array($value) || ! array_is_list($value)) {
                throw QueryTreeException::invalidCondition($column, 'operator requires a list of typed values');
            }

            if ($value === [] || count($value) < $min || ($max !== null && count($value) > $max)) {
                throw QueryTreeException::invalidCondition($column, sprintf('operator requires %s typed value(s)', $max === null ? 'at least ' . $min : (string) $max));
            }

            foreach ($value as $item) {
                if ( ! $item instanceof ParamInterface) {
                    throw QueryTreeException::invalidCondition($column, 'every list entry must be a typed Param value');
                }
            }

            /** @var array<int, ParamInterface> $value */
            return $value;
        }

        /**
         * @param string $column
         * @param ParamInterface|array<array-key, mixed> $value
         * @return array<int, ParamInterface>
         * @throws QueryTreeException
         */
        private static function assertSingle(string $column, ParamInterface|array $value): array
        {
            if ( ! $value instanceof ParamInterface) {
                throw QueryTreeException::invalidCondition($column, 'operator requires a single typed Param value');
            }

            return [$value];
        }
    }
