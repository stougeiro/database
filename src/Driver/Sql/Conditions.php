<?php declare(strict_types=1);

    namespace STDW\Database\Driver\Sql;

    use STDW\Contract\Database\Param\ParamInterface;
    use STDW\Contract\Database\QueryTree\Types\ConditionOperator;
    use STDW\Database\Exception\QueryException;


    /**
     * Compiles the canonical driver-edge filter (Predicate or Clause list,
     * keys of the form "column OP") into a SQL WHERE fragment plus an ordered
     * list of typed params.
     *
     * Evaluation follows the contract: clauses are joined left-to-right with
     * the pending connector (default AND); NOT negates only the next operand;
     * nested lists are groups wrapped in parentheses. Values must be typed
     * Params — anything else fails fast. An empty IN list never matches
     * ("0 = 1"); an empty NOT IN list matches everything ("1 = 1").
     *
     * Identifiers are quoted through the caller-provided quoter, so the
     * compiler stays dialect-neutral.
     */
    final class Conditions
    {
        /** @var array<string, string>
         */
        private const OPERATORS = [
            'eq' => '=',
            'neq' => '<>',
            'gt' => '>',
            'gte' => '>=',
            'lt' => '<',
            'lte' => '<=',
            'like' => 'LIKE',
            'not_like' => 'NOT LIKE',
        ];


        /**
         * @param array<array-key, mixed> $filter
         * @param callable(string): string $quote
         * @return array{sql: string, params: array<int, ParamInterface>}
         * @throws QueryException
         */
        public static function compile(array $filter, callable $quote): array
        {
            $params = [];
            $sql = array_is_list($filter)
                ? self::clauses($filter, $quote, $params)
                : self::predicate($filter, $quote, $params);

            return ['sql' => trim($sql), 'params' => $params];
        }


        /**
         * @param array<array-key, mixed> $predicate
         * @param callable(string): string $quote
         * @param array<int, ParamInterface> $params
         * @return string
         * @throws QueryException
         */
        private static function predicate(array $predicate, callable $quote, array &$params): string
        {
            $fragments = [];

            foreach ($predicate as $key => $value) {
                if ( ! is_string($key)) {
                    throw QueryException::invalid('predicate keys must be strings');
                }

                $fragments[] = self::condition($key, $value, $quote, $params);
            }

            return implode(' AND ', $fragments);
        }

        /**
         * @param array<array-key, mixed> $clauses
         * @param callable(string): string $quote
         * @param array<int, ParamInterface> $params
         * @return string
         * @throws QueryException
         */
        private static function clauses(array $clauses, callable $quote, array &$params): string
        {
            $accumulated = null;
            $pending = 'AND';
            $negate = false;

            foreach ($clauses as $clause) {
                if (is_string($clause)) {
                    $token = strtoupper(trim($clause));

                    if ($token === 'NOT') {
                        $negate = true;

                        continue;
                    }

                    if ($token === 'AND' || $token === 'OR') {
                        $pending = $token;

                        continue;
                    }

                    throw QueryException::invalid(sprintf('unknown logic token "%s"', $clause));
                }

                if ( ! is_array($clause)) {
                    throw QueryException::invalid('clause must be a predicate, a logic token or a nested group');
                }

                $fragment = array_is_list($clause)
                    ? self::clauses($clause, $quote, $params)
                    : self::predicate($clause, $quote, $params);

                if ($fragment === '') {
                    continue;
                }

                if ($negate) {
                    $fragment = 'NOT (' . $fragment . ')';
                    $negate = false;
                }

                $accumulated = $accumulated === null
                    ? $fragment
                    : '(' . $accumulated . ') ' . $pending . ' (' . $fragment . ')';

                $pending = 'AND';
            }

            return $accumulated ?? '';
        }

        /**
         * @param string $key
         * @param mixed $value
         * @param callable(string): string $quote
         * @param array<int, ParamInterface> $params
         * @return string
         * @throws QueryException
         */
        private static function condition(string $key, mixed $value, callable $quote, array &$params): string
        {
            [$column, $operator] = self::parseKey($key);
            $column = $quote($column);

            return match ($operator) {
                ConditionOperator::IsNull => $column . ' IS NULL',
                ConditionOperator::IsNotNull => $column . ' IS NOT NULL',
                ConditionOperator::In => self::in($column, $value, $params, false),
                ConditionOperator::NotIn => self::in($column, $value, $params, true),
                ConditionOperator::Between => self::between($column, $value, $params, false),
                ConditionOperator::NotBetween => self::between($column, $value, $params, true),
                default => self::comparison($column, $operator, $value, $params),
            };
        }

        /**
         * @param string $key
         * @return array{string, ConditionOperator}
         * @throws QueryException
         */
        private static function parseKey(string $key): array
        {
            $parts = explode(' ', trim($key), 2);

            if (count($parts) === 1 || $parts[1] === '') {
                return [$parts[0], ConditionOperator::Eq];
            }

            $token = strtolower($parts[1]);

            if (isset(self::OPERATORS[$token])) {
                return [$parts[0], ConditionOperator::from($token)];
            }

            return match ($token) {
                'in', 'not_in', 'between', 'not_between', 'is_null', 'is_not_null' => [$parts[0], ConditionOperator::from($token)],
                default => throw QueryException::invalid(sprintf('unknown operator token "%s"', strtoupper($token))),
            };
        }

        /**
         * @param string $column
         * @param ConditionOperator $operator
         * @param mixed $value
         * @param array<int, ParamInterface> $params
         * @return string
         * @throws QueryException
         */
        private static function comparison(string $column, ConditionOperator $operator, mixed $value, array &$params): string
        {
            $params[] = self::param($column, $value);

            $symbol = self::OPERATORS[$operator->value]
                ?? throw QueryException::invalid(sprintf('operator "%s" has no SQL comparison symbol', $operator->value));

            return $column . ' ' . $symbol . ' ?';
        }

        /**
         * @param string $column
         * @param mixed $value
         * @param array<int, ParamInterface> $params
         * @param bool $negated
         * @return string
         * @throws QueryException
         */
        private static function in(string $column, mixed $value, array &$params, bool $negated): string
        {
            if ( ! is_array($value) || ! array_is_list($value)) {
                throw QueryException::invalid(sprintf('operator IN on column %s requires a list of typed values', $column));
            }

            if ($value === []) {
                return $negated ? '1 = 1' : '0 = 1';
            }

            $placeholders = [];

            foreach ($value as $item) {
                $params[] = self::param($column, $item);
                $placeholders[] = '?';
            }

            return $column . ($negated ? ' NOT IN (' : ' IN (') . implode(', ', $placeholders) . ')';
        }

        /**
         * @param string $column
         * @param mixed $value
         * @param array<int, ParamInterface> $params
         * @param bool $negated
         * @return string
         * @throws QueryException
         */
        private static function between(string $column, mixed $value, array &$params, bool $negated): string
        {
            if ( ! is_array($value) || ! array_is_list($value) || count($value) !== 2) {
                throw QueryException::invalid(sprintf('operator BETWEEN on column %s requires exactly two typed values', $column));
            }

            $params[] = self::param($column, $value[0]);
            $params[] = self::param($column, $value[1]);

            return $column . ($negated ? ' NOT BETWEEN ? AND ?' : ' BETWEEN ? AND ?');
        }

        /**
         * @param string $column
         * @param mixed $value
         * @return ParamInterface
         * @throws QueryException
         */
        private static function param(string $column, mixed $value): ParamInterface
        {
            if ( ! $value instanceof ParamInterface) {
                throw QueryException::parameterRequired($column);
            }

            return $value;
        }
    }
