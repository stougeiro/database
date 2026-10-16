<?php declare(strict_types=1);

    namespace STDW\Database\Exception;

    use STDW\Contract\Database\Exception\ConnectionExceptionInterface;
    use Throwable;


    /**
     * Lifecycle failures: opening/closing the physical link and invalid
     * connection configuration.
     */
    final class ConnectionException extends DatabaseException implements ConnectionExceptionInterface
    {
        /**
         * @param string $name
         * @return self
         */
        public static function notConfigured(string $name): self
        {
            return new self(sprintf('Connection "%s" is not configured', $name));
        }

        /**
         * @param string $target
         * @param Throwable $previous
         * @return self
         */
        public static function failed(string $target, Throwable $previous): self
        {
            return new self(
                sprintf('Failed to connect to "%s": %s', $target, $previous->getMessage()),
                (int) $previous->getCode(),
                $previous,
            );
        }
    }
