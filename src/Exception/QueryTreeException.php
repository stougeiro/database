<?php declare(strict_types=1);

    namespace STDW\Database\Exception;

    use STDW\Contract\Database\Exception\QueryTreeExceptionInterface;


    /**
     * Invalid query-tree construction: unknown operator token, missing or
     * malformed values, non-Param entries.
     */
    final class QueryTreeException extends DatabaseException implements QueryTreeExceptionInterface
    {
        /** @param string $token
         * @return self
         */
        public static function invalidOperator(string $token): self
        {
            return new self(sprintf('Unknown condition operator token "%s"', $token));
        }

        /** @param string $token
         * @return self
         */
        public static function invalidLogic(string $token): self
        {
            return new self(sprintf('Unknown logic token "%s" (expected AND, OR or NOT)', $token));
        }

        /** @param string $column
         * @param string $reason
         * @return self
         */
        public static function invalidCondition(string $column, string $reason): self
        {
            return new self(sprintf('Invalid condition on column "%s": %s', $column, $reason));
        }

        /** @param string $reason
         * @return self
         */
        public static function invalid(string $reason): self
        {
            return new self(sprintf('Invalid query tree: %s', $reason));
        }
    }
