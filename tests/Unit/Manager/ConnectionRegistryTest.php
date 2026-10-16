<?php declare(strict_types=1);

    use STDW\Database\Exception\ManagerException;
    use STDW\Database\Manager\ConnectionRegistry;
    use Tests\Stubs\Pdo\PdoSqliteConnectionStub;


    test('adds, gets and checks connections', function () {
        $registry = new ConnectionRegistry();
        $connection = new PdoSqliteConnectionStub();

        $registry->add('default', $connection);

        expect($registry->has('default'))->toBeTrue()
            ->and($registry->has('other'))->toBeFalse()
            ->and($registry->get('default'))->toBe($connection)
            ->and($registry->all())->toBe(['default' => $connection]);
    });

    test('replaces an existing connection', function () {
        $registry = new ConnectionRegistry();
        $first = new PdoSqliteConnectionStub();
        $second = new PdoSqliteConnectionStub();

        $registry->add('default', $first);
        $registry->add('default', $second);

        expect($registry->get('default'))->toBe($second);
    });

    test('removes connections', function () {
        $registry = new ConnectionRegistry();
        $registry->add('default', new PdoSqliteConnectionStub());

        $registry->remove('default');

        expect($registry->has('default'))->toBeFalse()
            ->and($registry->all())->toBe([]);
    });

    test('getting an unknown connection throws', function () {
        (new ConnectionRegistry())->get('missing');
    })->throws(ManagerException::class);
