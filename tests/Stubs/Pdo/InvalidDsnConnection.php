<?php declare(strict_types=1);

namespace Tests\Stubs\Pdo;

final class InvalidDsnConnection extends PdoSqliteConnectionStub
{
    protected function dsn(): string
    {
        return 'sqlite:/non/existing/directory/db.sqlite';
    }
}
