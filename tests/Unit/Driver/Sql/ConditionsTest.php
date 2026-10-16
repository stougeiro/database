<?php declare(strict_types=1);

    use STDW\Database\Driver\Sql\Conditions;
    use STDW\Database\Exception\QueryException;
    use STDW\Database\Param\Param;


    $quote = fn (string $identifier): string => '"' . $identifier . '"';


    test('compiles an empty filter into an empty fragment', function () use ($quote) {
        $compiled = Conditions::compile([], $quote);

        expect($compiled['sql'])->toBe('')
            ->and($compiled['params'])->toBe([]);
    });

    test('compiles a predicate with the default EQ', function () use ($quote) {
        $compiled = Conditions::compile(['status' => Param::str('active')], $quote);

        expect($compiled['sql'])->toBe('"status" = ?')
            ->and($compiled['params'])->toHaveCount(1);
    });

    test('compiles every comparison operator', function () use ($quote) {
        $cases = [
            'age EQ' => '"age" = ?',
            'age NEQ' => '"age" <> ?',
            'age GT' => '"age" > ?',
            'age GTE' => '"age" >= ?',
            'age LT' => '"age" < ?',
            'age LTE' => '"age" <= ?',
            'name LIKE' => '"name" LIKE ?',
            'name NOT_LIKE' => '"name" NOT LIKE ?',
        ];

        foreach ($cases as $key => $sql) {
            $compiled = Conditions::compile([$key => Param::str('x')], $quote);

            expect($compiled['sql'])->toBe($sql);
        }
    });

    test('compiles in, not_in, between and null operators', function () use ($quote) {
        expect(Conditions::compile(['id IN' => [Param::int(1), Param::int(2)]], $quote)['sql'])
            ->toBe('"id" IN (?, ?)')
            ->and(Conditions::compile(['id NOT_IN' => [Param::int(1)]], $quote)['sql'])
            ->toBe('"id" NOT IN (?)')
            ->and(Conditions::compile(['age BETWEEN' => [Param::int(18), Param::int(30)]], $quote)['sql'])
            ->toBe('"age" BETWEEN ? AND ?')
            ->and(Conditions::compile(['age NOT_BETWEEN' => [Param::int(1), Param::int(2)]], $quote)['sql'])
            ->toBe('"age" NOT BETWEEN ? AND ?')
            ->and(Conditions::compile(['deleted_at IS_NULL' => null], $quote)['sql'])
            ->toBe('"deleted_at" IS NULL')
            ->and(Conditions::compile(['deleted_at IS_NOT_NULL' => null], $quote)['sql'])
            ->toBe('"deleted_at" IS NOT NULL');
    });

    test('an empty IN never matches and an empty NOT IN matches all', function () use ($quote) {
        expect(Conditions::compile(['id IN' => []], $quote)['sql'])->toBe('0 = 1')
            ->and(Conditions::compile(['id NOT_IN' => []], $quote)['sql'])->toBe('1 = 1');
    });

    test('joins predicate entries with AND', function () use ($quote) {
        $compiled = Conditions::compile([
            'age GTE' => Param::int(18),
            'status' => Param::str('active'),
        ], $quote);

        expect($compiled['sql'])->toBe('"age" >= ? AND "status" = ?');
    });

    test('joins clauses left-to-right with explicit connectors', function () use ($quote) {
        $compiled = Conditions::compile([
            ['name LIKE' => Param::str('%a%')],
            'OR',
            ['name LIKE' => Param::str('%b%')],
        ], $quote);

        expect($compiled['sql'])->toBe('("name" LIKE ?) OR ("name" LIKE ?)')
            ->and($compiled['params'])->toHaveCount(2);
    });

    test('NOT negates only the next operand', function () use ($quote) {
        $compiled = Conditions::compile([
            ['active' => Param::bool(true)],
            'AND',
            'NOT',
            ['status' => Param::str('banned')],
        ], $quote);

        expect($compiled['sql'])->toBe('("active" = ?) AND (NOT ("status" = ?))');
    });

    test('nested groups compile with parentheses', function () use ($quote) {
        $compiled = Conditions::compile([
            ['age BETWEEN' => [Param::int(18), Param::int(30)], 'id IN' => [Param::int(1)]],
            'AND',
            ['status' => Param::str('active')],
        ], $quote);

        expect($compiled['sql'])->toBe('("age" BETWEEN ? AND ? AND "id" IN (?)) AND ("status" = ?)');
    });

    test('keeps params in order of appearance', function () use ($quote) {
        $compiled = Conditions::compile([
            ['age GTE' => Param::int(18)],
            'OR',
            ['name' => Param::str('x'), 'id IN' => [Param::int(1), Param::int(2)]],
        ], $quote);

        expect($compiled['params'])->toHaveCount(4)
            ->and($compiled['params'][0]->value())->toBe(18)
            ->and($compiled['params'][3]->value())->toBe(2);
    });

    test('rejects values that are not typed params', function () use ($quote) {
        Conditions::compile(['age' => 18], $quote);
    })->throws(QueryException::class);

    test('rejects unknown operator tokens', function () use ($quote) {
        Conditions::compile(['age APPROX' => Param::int(1)], $quote);
    })->throws(QueryException::class);

    test('rejects unknown logic tokens', function () use ($quote) {
        Conditions::compile([['a' => Param::int(1)], 'XOR', ['b' => Param::int(2)]], $quote);
    })->throws(QueryException::class);

    test('rejects between without exactly two values', function () use ($quote) {
        Conditions::compile(['age BETWEEN' => [Param::int(1)]], $quote);
    })->throws(QueryException::class);
