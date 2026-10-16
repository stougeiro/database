<?php declare(strict_types=1);

    use STDW\Contract\Database\QueryTree\Types\ConditionOperator;
    use STDW\Contract\Database\QueryTree\Types\LogicOperator;
    use STDW\Database\Builder\FilterBuilder;
    use STDW\Database\Exception\QueryTreeException;
    use STDW\Database\Param\Param;
    use STDW\Database\QueryTree\ConditionNode;
    use STDW\Database\QueryTree\GroupNode;
    use STDW\Database\QueryTree\LogicNode;


    test('builds an empty group for an empty filter', function () {
        $group = FilterBuilder::make([]);

        expect($group->nodes())->toBe([]);
    });

    test('builds condition nodes from a predicate', function () {
        $group = FilterBuilder::make([
            'age GTE' => Param::int(18),
            'status' => Param::str('active'),
        ]);

        $nodes = $group->nodes();

        expect($nodes)->toHaveCount(2)
            ->and($nodes[0])->toBeInstanceOf(ConditionNode::class)
            ->and($nodes[0]->operator())->toBe(ConditionOperator::Gte)
            ->and($nodes[1]->operator())->toBe(ConditionOperator::Eq);
    });

    test('parses lowercase operator tokens', function () {
        $group = FilterBuilder::make(['age gte' => Param::int(18)]);

        expect($group->nodes()[0]->operator())->toBe(ConditionOperator::Gte);
    });

    test('accepts null only for the null operators', function () {
        $group = FilterBuilder::make(['deleted_at IS_NULL' => null]);

        expect($group->nodes()[0]->operator())->toBe(ConditionOperator::IsNull);

        FilterBuilder::make(['age' => null]);
    })->throws(QueryTreeException::class);

    test('rejects unknown operator tokens', function () {
        FilterBuilder::make(['age APPROX' => Param::int(18)]);
    })->throws(QueryTreeException::class);

    test('builds logic nodes and nested groups from a clause list', function () {
        $group = FilterBuilder::make([
            ['name LIKE' => Param::str('%a%')],
            'OR',
            ['name LIKE' => Param::str('%b%')],
        ]);

        $nodes = $group->nodes();

        expect($nodes)->toHaveCount(3)
            ->and($nodes[0])->toBeInstanceOf(GroupNode::class)
            ->and($nodes[1])->toBeInstanceOf(LogicNode::class)
            ->and($nodes[1]->operator())->toBe(LogicOperator::Or);
    });

    test('builds nested predicate groups', function () {
        $group = FilterBuilder::make([
            ['age BETWEEN' => [Param::int(18), Param::int(30)], 'id IN' => [Param::int(1), Param::int(2)]],
            'AND',
            ['status' => Param::str('active')],
        ]);

        $inner = $group->nodes()[0];

        expect($inner->nodes())->toHaveCount(2);
    });

    test('rejects invalid logic tokens', function () {
        FilterBuilder::make([['a' => Param::int(1)], 'XOR', ['b' => Param::int(2)]]);
    })->throws(QueryTreeException::class);

    test('rejects clauses that are neither arrays nor tokens', function () {
        FilterBuilder::make([42]);
    })->throws(QueryTreeException::class);

    test('rejects non-param values', function () {
        FilterBuilder::make(['age' => 18]);
    })->throws(QueryTreeException::class);
