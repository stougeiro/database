<?php declare(strict_types=1);

    namespace STDW\Database\Exception;

    use STDW\Contract\Database\Exception\DriverExceptionInterface;


    /**
     * Raised when an operation requires a capability the driver does not
     * honor (check $driver->capabilities()->supportsX() before calling).
     */
    final class UnsupportedCapabilityException extends DatabaseException implements DriverExceptionInterface
    {
        /** @param string $capability
         * @return self
         */
        public static function capability(string $capability): self
        {
            return new self(sprintf('The driver does not support the "%s" capability', $capability));
        }
    }
