<?php declare(strict_types=1);

    use STDW\Contract\Database\QueryTree\Types\ConditionOperator;
    use STDW\Database\Exception\QueryTreeException;
    use STDW\Database\Param\Param;
    use STDW\Database\QueryTree\ConditionNode;


    test('exposes column, operator and a single value', function () {
        $value = Param::int(18);
        $node = new ConditionNode('age', ConditionOperator::Gte, $value);

        expect($node->column())->toBe('age')
            ->and($node->operator())->toBe(ConditionOperator::Gte)
            ->and($node->value())->toBe($value);
    });

    test('exposes a list value for in', function () {
        $values = [Param::int(1), Param::int(2)];
        $node = new ConditionNode('id', ConditionOperator::In, $values);

        expect($node->value())->toBe($values);
    });

    test('requires exactly two values for between', function () {
        new ConditionNode('age', ConditionOperator::Between, [Param::int(18)]);
    })->throws(QueryTreeException::class);

    test('requires a non-empty list for in', function () {
        new ConditionNode('id', ConditionOperator::In, []);
    })->throws(QueryTreeException::class);

    test('rejects a list for single operators', function () {
        new ConditionNode('id', ConditionOperator::Eq, [Param::int(1)]);
    })->throws(QueryTreeException::class);

    test('rejects a single value for in', function () {
        new ConditionNode('id', ConditionOperator::In, Param::int(1));
    })->throws(QueryTreeException::class);

    test('normalizes null operators to an empty list', function () {
        $node = new ConditionNode('deleted_at', ConditionOperator::IsNull, []);

        expect($node->value())->toBe([]);
    });

    test('rejects non-param list entries', function () {
        /** @var mixed $bypass */
        $bypass = [1];
        new ConditionNode('id', ConditionOperator::In, $bypass);
    })->throws(QueryTreeException::class);

    test('rejects an empty column', function () {
        new ConditionNode('  ', ConditionOperator::Eq, Param::int(1));
    })->throws(QueryTreeException::class);
