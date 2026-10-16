<?php declare(strict_types=1);

    namespace STDW\Database\Exception;

    use STDW\Contract\Database\Exception\ParamExceptionInterface;


    /**
     * Invalid typed value: a value that cannot be represented by any
     * ParamType storage primitive.
     */
    final class ParamException extends DatabaseException implements ParamExceptionInterface
    {
        /**
         * @param string $type
         * @return self
         */
        public static function invalidValue(string $type): self
        {
            return new self(sprintf('Value of type "%s" cannot be represented as a typed database param', $type));
        }
    }
