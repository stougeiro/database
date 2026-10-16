<?php declare(strict_types=1);

    namespace STDW\Database\Exception;

    use Throwable;


    /**
     * Failures while compiling or executing a query (invalid filter shape,
     * missing Param, engine rejection).
     */
    final class QueryException extends DriverException
    {
        /**
         * @param string $reason
         * @return self
         */
        public static function invalid(string $reason): self
        {
            return new self(sprintf('Invalid query: %s', $reason));
        }

        /**
         * @param string $column
         * @return self
         */
        public static function parameterRequired(string $column): self
        {
            return new self(sprintf('A typed Param value is required for column "%s"', $column));
        }

        /**
         * @param string $sql
         * @param Throwable $previous
         * @return self
         */
        public static function executionFailed(string $sql, Throwable $previous): self
        {
            return new self(
                sprintf(
                    'Query execution failed (%s): %s',
                    mb_substr($sql, 0, 200),
                    $previous->getMessage(),
                ),
                (int) $previous->getCode(),
                $previous,
            );
        }
    }
