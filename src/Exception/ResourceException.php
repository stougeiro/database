<?php declare(strict_types=1);

    namespace STDW\Database\Exception;

    use STDW\Contract\Database\Exception\ResourceExceptionInterface;


    /**
     * Invalid resource declaration (e.g. an empty resource name).
     */
    final class ResourceException extends DatabaseException implements ResourceExceptionInterface
    {
        /** @return self
         */
        public static function emptyName(): self
        {
            return new self('A resource name cannot be empty');
        }
    }
