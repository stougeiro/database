<?php declare(strict_types=1);

    namespace STDW\Database\Exception;

    use STDW\Contract\Database\Exception\DriverExceptionInterface;
    use Throwable;


    /**
     * Base exception for every driver-level failure.
     */
    class DriverException extends DatabaseException implements DriverExceptionInterface
    {
        /**
         * @param string $operation
         * @param Throwable $previous
         * @return self
         */
        public static function transactionFailed(string $operation, Throwable $previous): self
        {
            return new self(
                sprintf('Transaction %s failed: %s', $operation, $previous->getMessage()),
                (int) $previous->getCode(),
                $previous,
            );
        }
    }
