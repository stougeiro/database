<?php declare(strict_types=1);

    namespace STDW\Database\Exception;

    use STDW\Contract\Database\Exception\ConnectionExceptionInterface;


    /**
     * Invalid or incomplete connection configuration.
     */
    final class InvalidConfigException extends DatabaseException implements ConnectionExceptionInterface
    {
        /**
         * @param string $key
         * @return self
         */
        public static function invalid(string $key): self
        {
            return new self(sprintf('Invalid value for configuration key "%s"', $key));
        }

        /** @param string $key
         * @return self
         */
        public static function missing(string $key): self
        {
            return new self(sprintf('Missing required configuration key "%s"', $key));
        }

        /** @return self
         */
        public static function invalidKeys(): self
        {
            return new self('Configuration keys must be strings');
        }

        /** @param string $class
         * @return self
         */
        public static function resourceNotDeclared(string $class): self
        {
            return new self(sprintf('Class "%s" must declare a resource name', $class));
        }
    }
