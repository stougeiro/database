<?php declare(strict_types=1);

namespace Tests\Stubs\Pdo;

use STDW\Contract\Database\Driver\DriverCapabilitiesInterface;
use STDW\Database\Driver\Sql\SqlDriver;

/**
 * Minimal concrete SQL driver used to exercise the shared SQL base with
 * MySQL-style backtick quoting on top of the in-memory SQLite connection —
 * proving the base is dialect-neutral.
 */
final class SqlDriverStub extends SqlDriver
{
    public function __construct(PdoSqliteConnectionStub $connection, ?DriverCapabilitiesInterface $capabilities = null)
    {
        parent::__construct($connection, $capabilities ?? new DriverCapabilitiesStub());
    }

    protected function quote(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
