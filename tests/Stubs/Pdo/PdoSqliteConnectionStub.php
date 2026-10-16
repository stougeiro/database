<?php declare(strict_types=1);

namespace Tests\Stubs\Pdo;

use STDW\Database\Driver\Pdo\PdoConnection;

class PdoSqliteConnectionStub extends PdoConnection
{
    protected function dsn(): string
    {
        return 'sqlite::memory:';
    }
}
