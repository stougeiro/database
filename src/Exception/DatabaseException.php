<?php declare(strict_types=1);

    namespace STDW\Database\Exception;

    use RuntimeException;
    use STDW\Contract\Database\Exception\DatabaseExceptionInterface;


    /**
     * Base exception of the package. Every concrete exception extends this
     * class and implements the marker interface of its own layer.
     */
    abstract class DatabaseException extends RuntimeException implements DatabaseExceptionInterface
    { }
